#!/usr/bin/env python3
"""
Telegram Bulk Quiz Worker (Dual Mode)
File: python/tg_quiz_worker.py

MODES:
  1. Direct DB Mode  — connects to MySQL directly (for local/VPS use)
  2. HTTP Sync API Mode — communicates via HTTPS endpoint (for online hosting)

Set SYNC_API_URL in .env to enable HTTP mode.
If SYNC_API_URL is empty/unset, Direct DB mode is used.

Usage:
    python tg_quiz_worker.py

Requirements:
    pip install -r requirements.txt
"""

import os
import sys
import time
import json
import logging
import traceback
from datetime import datetime, timedelta
from pathlib import Path

import requests
from dotenv import load_dotenv
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend
import base64
import hashlib

# ─── Setup ───────────────────────────────────────────────────────────────────

env_path = Path(__file__).parent / '.env'
load_dotenv(dotenv_path=env_path)

# Logging — NEVER log bot tokens
log_file = Path(__file__).parent / 'worker.log'
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler(log_file, encoding='utf-8'),
    ]
)
log = logging.getLogger('tg_quiz_worker')

# ─── Config ──────────────────────────────────────────────────────────────────

# Mode detection
SYNC_API_URL    = os.getenv('SYNC_API_URL', '').strip()
WORKER_SECRET   = os.getenv('WORKER_SECRET', '').strip()
USE_API_MODE    = bool(SYNC_API_URL)

# DB config (only used in Direct DB mode)
DB_CONFIG = {
    'host':    os.getenv('DB_HOST', 'localhost'),
    'user':    os.getenv('DB_USER', 'root'),
    'passwd':  os.getenv('DB_PASS', ''),
    'db':      os.getenv('DB_NAME', 'Vishwkarma'),
    'charset': 'utf8mb4',
    'autocommit': False,
    'connect_timeout': 10,
}

ENCRYPTION_KEY_RAW = os.getenv('TG_ENCRYPTION_KEY', 'default-32-char-key-change-this!!')
POLL_INTERVAL      = int(os.getenv('POLL_INTERVAL_SECONDS', '5'))
MAX_RETRIES        = int(os.getenv('MAX_RETRIES', '3'))
RETRY_DELAY        = int(os.getenv('RETRY_DELAY_SECONDS', '10'))
TELEGRAM_API_BASE  = 'https://api.telegram.org/bot{token}/{method}'
RATE_LIMIT_DELAY   = 1.1  # seconds between Telegram API calls

# ─── Encryption (must match PHP implementation) ──────────────────────────────

def get_encryption_key() -> bytes:
    """Derive 32-byte AES key from raw key string (SHA-256 first 32 bytes)."""
    return hashlib.sha256(ENCRYPTION_KEY_RAW.encode()).digest()[:32]

def decrypt_token(encrypted_b64: str) -> str:
    """Decrypt AES-256-CBC encrypted bot token. Matches PHP openssl_decrypt."""
    try:
        data   = base64.b64decode(encrypted_b64)
        iv     = data[:16]
        cipher_text = data[16:]
        key    = get_encryption_key()
        cipher = Cipher(algorithms.AES(key), modes.CBC(iv), backend=default_backend())
        decryptor = cipher.decryptor()
        padded = decryptor.update(cipher_text) + decryptor.finalize()
        # Remove PKCS7 padding
        pad_len = padded[-1]
        return padded[:-pad_len].decode('utf-8')
    except Exception as e:
        log.error(f"Token decryption failed: {e}")
        return ''

# ─── Telegram API ────────────────────────────────────────────────────────────

def send_quiz_poll(token: str, chat_id: int, question: dict, timer_seconds: int = 0) -> dict:
    """
    Send a Telegram Quiz Poll.
    Returns the Telegram API response dict.
    Token is NEVER logged.
    """
    option_letters = [
        letter for letter, key in (
            ('A', 'option_a'), ('B', 'option_b'), ('C', 'option_c'), ('D', 'option_d')
        ) if question.get(key)
    ]
    options = [question[f'option_{letter.lower()}'] for letter in option_letters]
    correct_letter = question['correct_answer'].upper()
    correct_idx = option_letters.index(correct_letter) if correct_letter in option_letters else 0

    payload = {
        'chat_id':              chat_id,
        'question':             question['question_text'][:300],
        'options':              options,
        'type':                 'quiz',
        'correct_option_id':    correct_idx,
        'is_anonymous':         False,
        'allows_multiple_answers': False,
    }

    if timer_seconds > 0:
        payload['open_period'] = max(5, min(600, int(timer_seconds)))

    if question.get('explanation'):
        payload['explanation'] = question['explanation'][:200]

    url = TELEGRAM_API_BASE.format(token=token, method='sendPoll')
    try:
        resp = requests.post(url, json=payload, timeout=15)
        return resp.json()
    except requests.RequestException as e:
        return {'ok': False, 'description': str(e)}


# ═══════════════════════════════════════════════════════════════════════════════
#  MODE 2: HTTP SYNC API CLIENT
# ═══════════════════════════════════════════════════════════════════════════════

class SyncAPIClient:
    """HTTP client for the worker_sync.php endpoint on the online server."""

    def __init__(self, base_url: str, secret: str):
        self.base_url = base_url.rstrip('/')
        self.secret = secret
        self.http = requests.Session()
        self.http.headers['X-Worker-Secret'] = secret
        self.http.headers['User-Agent'] = 'TG-Quiz-Worker/2.0'

    def _get(self, action: str, params: dict = None) -> dict:
        url = f'{self.base_url}?action={action}'
        if params:
            for k, v in params.items():
                url += f'&{k}={v}'
        resp = self.http.get(url, timeout=20)
        resp.raise_for_status()
        return resp.json()

    def _post(self, action: str, data: dict = None) -> dict:
        url = f'{self.base_url}?action={action}'
        resp = self.http.post(url, data=data or {}, timeout=20)
        resp.raise_for_status()
        return resp.json()

    def heartbeat(self) -> dict:
        return self._get('heartbeat')

    def sync_poll_answers(self) -> dict:
        return self._get('sync_poll_answers')

    def get_pending_tasks(self) -> list:
        result = self._get('get_pending_tasks')
        if result.get('success'):
            return result.get('tasks', [])
        return []

    def report_sent(self, session_id: int, question_id: int, question_number: int,
                    timer: int, poll_id: str = '') -> dict:
        return self._post('report_sent', {
            'session_id':      session_id,
            'question_id':     question_id,
            'question_number': question_number,
            'timer_seconds':   timer,
            'poll_id':         poll_id,
        })

    def report_failed(self, session_id: int, question_id: int, question_number: int,
                      error: str, retry_count: int, timer: int) -> dict:
        return self._post('report_failed', {
            'session_id':      session_id,
            'question_id':     question_id,
            'question_number': question_number,
            'error_message':   error[:500],
            'retry_count':     retry_count,
            'max_retries':     MAX_RETRIES,
            'timer_seconds':   timer,
            'retry_delay':     RETRY_DELAY,
        })

    def mark_completed(self, session_id: int) -> dict:
        return self._post('mark_completed', {'session_id': session_id})

    def mark_failed(self, session_id: int, error: str) -> dict:
        return self._post('mark_failed', {
            'session_id':    session_id,
            'error_message': error[:500],
        })

    def report_rate_limit(self, session_id: int, retry_after: int) -> dict:
        return self._post('report_rate_limit', {
            'session_id':  session_id,
            'retry_after': retry_after,
        })

    def skip_question(self, session_id: int, question_id: int, question_number: int) -> dict:
        return self._post('skip_question', {
            'session_id':      session_id,
            'question_id':     question_id,
            'question_number': question_number,
        })


def process_task_api(client: SyncAPIClient, task: dict):
    """Process one task from the HTTP Sync API."""
    session_id   = task['session_id']
    current_q    = task['current_question']
    total_q      = task['total_questions']
    timer        = task['timer_seconds']
    chat_id      = task['chat_id']
    retry_count  = task.get('retry_count', 0) or 0
    question     = task.get('question')
    question_id  = task['question_id']
    question_num = task['question_number']

    # All questions sent?
    if current_q >= total_q:
        client.mark_completed(session_id)
        log.info(f"Session #{session_id} COMPLETED ✅")
        return

    # Decrypt bot token
    token = decrypt_token(task['bot_token_encrypted'])
    if not token:
        client.mark_failed(session_id, "Could not decrypt bot token")
        log.error(f"Session #{session_id}: Token decryption failed")
        return

    # Question missing?
    if not question:
        log.warning(f"Session #{session_id}: Q{question_num} (ID {question_id}) not found, skipping")
        client.skip_question(session_id, question_id, question_num)
        return

    log.info(f"Session #{session_id}: Sending Q{question_num}/{total_q} to chat {chat_id}")

    # Send to Telegram
    result = send_quiz_poll(token, int(chat_id), question, timer)

    if result.get('ok'):
        poll_id = str(result.get('result', {}).get('poll', {}).get('id', ''))
        resp = client.report_sent(session_id, question_id, question_num, timer, poll_id)

        # Check if session was paused/stopped while we were sending
        if resp.get('action') == 'stop':
            log.info(f"Session #{session_id}: Stopped by admin during send")
            return

        log.info(f"Session #{session_id}: Q{question_num} sent ✅. Next in {timer}s")
        time.sleep(RATE_LIMIT_DELAY)
    else:
        error_desc = result.get('description', 'Unknown error')
        new_retry  = retry_count + 1

        # Handle Telegram rate limit (429)
        if '429' in str(result) or 'Too Many Requests' in error_desc:
            retry_after = result.get('parameters', {}).get('retry_after', 30)
            log.warning(f"Session #{session_id}: Rate limited. Waiting {retry_after}s")
            client.report_rate_limit(session_id, int(retry_after))
            time.sleep(int(retry_after))
            return

        log.error(f"Session #{session_id}: Q{question_num} failed (retry {new_retry}/{MAX_RETRIES}): {error_desc}")
        client.report_failed(session_id, question_id, question_num, error_desc, new_retry, timer)


def main_api_mode():
    """Main loop using HTTP Sync API mode (for online hosting)."""
    log.info("=" * 60)
    log.info("Telegram Quiz Worker started — HTTP Sync API Mode")
    log.info(f"Sync API URL: {SYNC_API_URL}")
    log.info(f"Poll interval: {POLL_INTERVAL}s | Max retries: {MAX_RETRIES}")
    log.info("=" * 60)

    if not WORKER_SECRET:
        log.error("WORKER_SECRET is not set in .env — cannot authenticate with server")
        sys.exit(1)

    client = SyncAPIClient(SYNC_API_URL, WORKER_SECRET)

    # Test connection
    try:
        hb = client.heartbeat()
        if hb.get('success'):
            log.info(f"API connected ✅ Server time: {hb.get('server_time')}")
        else:
            log.error(f"API heartbeat failed: {hb.get('message')}")
            sys.exit(1)
    except Exception as e:
        log.error(f"Cannot connect to Sync API: {e}")
        log.error("Check SYNC_API_URL and WORKER_SECRET in .env")
        sys.exit(1)

    consecutive_errors = 0

    while True:
        try:
            sync_result = client.sync_poll_answers()
            if not sync_result.get('success'):
                log.warning(f"Could not sync Telegram poll answers: {sync_result.get('message', 'Unknown error')}")

            tasks = client.get_pending_tasks()

            if tasks:
                log.info(f"Found {len(tasks)} pending task(s)")
                for task in tasks:
                    try:
                        process_task_api(client, task)
                    except Exception as e:
                        log.error(f"Error processing session #{task.get('session_id')}: {e}")
                        log.debug(traceback.format_exc())

            consecutive_errors = 0
            time.sleep(POLL_INTERVAL)

        except requests.RequestException as e:
            consecutive_errors += 1
            log.error(f"API connection error (attempt {consecutive_errors}): {e}")
            sleep_time = min(60, POLL_INTERVAL * consecutive_errors * 2)
            log.info(f"Retrying in {sleep_time}s...")
            time.sleep(sleep_time)

        except KeyboardInterrupt:
            log.info("Worker stopped by user (Ctrl+C)")
            sys.exit(0)

        except Exception as e:
            log.error(f"Unexpected error: {e}")
            log.debug(traceback.format_exc())
            time.sleep(POLL_INTERVAL)


# ═══════════════════════════════════════════════════════════════════════════════
#  MODE 1: DIRECT DATABASE (original behavior — for local/VPS use)
# ═══════════════════════════════════════════════════════════════════════════════

def get_db_connection():
    """Get a fresh DB connection (Direct DB mode only)."""
    import pymysql
    return pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)

def get_active_sessions(conn) -> list:
    """Fetch all RUNNING sessions where next_question_at <= NOW()."""
    with conn.cursor() as cur:
        cur.execute("""
            SELECT s.*, b.bot_token_encrypted
            FROM tg_quiz_sessions s
            JOIN tg_bots b ON b.admin_id = s.admin_id AND b.is_active = 1
            WHERE s.status = 'RUNNING'
              AND s.next_question_at <= NOW()
              AND s.current_question < s.total_questions
            ORDER BY s.next_question_at ASC
        """)
        return cur.fetchall()

def sync_poll_answers_db(conn):
    """Fetch non-anonymous poll votes and persist the latest answer per user."""
    with conn.cursor() as cur:
        cur.execute("""
            SELECT DISTINCT b.id, b.bot_token_encrypted, b.last_update_id
            FROM tg_bots b
            JOIN tg_quiz_sessions s ON s.admin_id = b.admin_id
            WHERE b.is_active = 1 AND s.status = 'RUNNING'
        """)
        bots = cur.fetchall()

    all_caught_up = True
    for bot in bots:
        token = decrypt_token(bot['bot_token_encrypted'])
        if not token:
            log.error(f"Could not decrypt token for bot #{bot['id']} while syncing poll answers")
            all_caught_up = False
            continue

        try:
            response = requests.post(
                TELEGRAM_API_BASE.format(token=token, method='getUpdates'),
                json={
                    'offset': int(bot['last_update_id'] or 0) + 1,
                    'limit': 100,
                    'timeout': 0,
                    'allowed_updates': ['poll_answer'],
                },
                timeout=15,
            )
            result = response.json()
        except (requests.RequestException, ValueError) as exc:
            log.error(f"Could not retrieve poll answers for bot #{bot['id']}: {exc}")
            all_caught_up = False
            continue

        if not result.get('ok'):
            log.error(f"Telegram getUpdates failed for bot #{bot['id']}: {result.get('description', 'Unknown error')}")
            all_caught_up = False
            continue

        updates = result.get('result', [])
        if len(updates) >= 100:
            all_caught_up = False

        for update in updates:
            try:
                poll_answer = update.get('poll_answer')
                if poll_answer:
                    options = poll_answer.get('option_ids') or []
                    user = poll_answer.get('user') or {}
                    poll_id = str(poll_answer.get('poll_id', ''))
                    user_id = int(user.get('id', 0))
                    if poll_id and user_id:
                        with conn.cursor() as cur:
                            cur.execute("""
                                SELECT l.session_id, l.question_id, l.question_number, q.correct_answer,
                                       q.option_a, q.option_b, q.option_c, q.option_d
                                FROM tg_quiz_logs l
                                JOIN tg_quiz_sessions s ON s.id = l.session_id
                                JOIN tg_quiz_questions q ON q.id = l.question_id
                                WHERE l.telegram_poll_id = %s AND l.status = 'SENT'
                                  AND s.status = 'RUNNING' AND s.admin_id = (
                                      SELECT admin_id FROM tg_bots WHERE id = %s
                                  )
                                LIMIT 1
                            """, (poll_id, bot['id']))
                            question = cur.fetchone()

                        if question and not options:
                            with conn.cursor() as cur:
                                cur.execute(
                                    "DELETE FROM tg_quiz_responses WHERE telegram_poll_id=%s AND user_id=%s",
                                    (poll_id, user_id),
                                )
                        elif question and options:
                            selected = int(options[0])
                            letters = [
                                letter for letter, key in (
                                    ('A', 'option_a'), ('B', 'option_b'),
                                    ('C', 'option_c'), ('D', 'option_d')
                                ) if question.get(key)
                            ]
                            correct_letter = str(question.get('correct_answer', 'A')).upper()
                            correct_option = letters.index(correct_letter) if correct_letter in letters else 0
                            with conn.cursor() as cur:
                                cur.execute("""
                                    INSERT INTO tg_quiz_responses
                                        (session_id, question_id, question_number, telegram_poll_id,
                                         user_id, username, first_name, last_name, selected_option_id, is_correct)
                                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                                    ON DUPLICATE KEY UPDATE
                                        question_id=VALUES(question_id),
                                        question_number=VALUES(question_number),
                                        username=VALUES(username),
                                        first_name=VALUES(first_name),
                                        last_name=VALUES(last_name),
                                        selected_option_id=VALUES(selected_option_id),
                                        is_correct=VALUES(is_correct),
                                        answered_at=NOW()
                                """, (
                                    question['session_id'], question['question_id'], question['question_number'],
                                    poll_id, user_id, user.get('username'), user.get('first_name'),
                                    user.get('last_name'), selected, int(selected == correct_option),
                                ))

                with conn.cursor() as cur:
                    cur.execute("UPDATE tg_bots SET last_update_id=%s WHERE id=%s", (int(update['update_id']), bot['id']))
                conn.commit()
            except Exception:
                conn.rollback()
                all_caught_up = False
                log.exception(f"Could not save Telegram update for bot #{bot['id']}")
                break

    if all_caught_up:
        with conn.cursor() as cur:
            cur.execute("""
                UPDATE tg_quiz_sessions
                SET status='COMPLETED', end_time=NOW(), updated_at=NOW()
                WHERE status='RUNNING' AND current_question >= total_questions
                  AND next_question_at <= NOW()
            """)
        conn.commit()

def get_question(conn, question_id: int) -> dict:
    """Fetch a question by ID."""
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM tg_quiz_questions WHERE id = %s", (question_id,))
        return cur.fetchone() or {}

def mark_question_sent(conn, session_id: int, question_id: int, question_num: int,
                        timer: int, poll_id: str = None):
    """Update session state after successfully sending a question."""
    next_at = datetime.now() + timedelta(seconds=timer)
    with conn.cursor() as cur:
        cur.execute("""
            UPDATE tg_quiz_sessions
            SET current_question   = current_question + 1,
                questions_sent     = questions_sent + 1,
                last_question_sent_at = NOW(),
                next_question_at   = %s,
                last_telegram_message_id = %s,
                retry_count        = 0,
                updated_at         = NOW()
            WHERE id = %s
        """, (next_at, poll_id, session_id))
        cur.execute("""
            INSERT INTO tg_quiz_logs (session_id, question_id, question_number, status, telegram_poll_id, sent_at)
            VALUES (%s, %s, %s, 'SENT', %s, NOW())
        """, (session_id, question_id, question_num, poll_id))
    conn.commit()

def mark_question_failed(conn, session_id: int, question_id: int, question_num: int,
                          error: str, retry_count: int):
    """Log a failed question send."""
    with conn.cursor() as cur:
        cur.execute("""
            UPDATE tg_quiz_sessions
            SET retry_count = %s, error_message = %s, updated_at = NOW()
            WHERE id = %s
        """, (retry_count, error[:500], session_id))
        cur.execute("""
            INSERT INTO tg_quiz_logs (session_id, question_id, question_number, status, error_message, retry_count)
            VALUES (%s, %s, %s, 'FAILED', %s, %s)
        """, (session_id, question_id, question_num, error[:500], retry_count))
    conn.commit()

def mark_session_completed(conn, session_id: int):
    """Mark session as COMPLETED."""
    with conn.cursor() as cur:
        cur.execute("""
            UPDATE tg_quiz_sessions
            SET status = 'COMPLETED', end_time = NOW(), updated_at = NOW()
            WHERE id = %s
        """, (session_id,))
    conn.commit()
    log.info(f"Session #{session_id} COMPLETED ✅")

def mark_session_failed(conn, session_id: int, error: str):
    """Mark session as FAILED after too many retries."""
    with conn.cursor() as cur:
        cur.execute("""
            UPDATE tg_quiz_sessions
            SET status = 'FAILED', end_time = NOW(), error_message = %s, updated_at = NOW()
            WHERE id = %s
        """, (error[:500], session_id))
    conn.commit()
    log.error(f"Session #{session_id} FAILED: {error}")


def process_session(conn, session: dict):
    """Process one quiz session — send the next question (Direct DB mode)."""
    session_id   = session['id']
    current_q    = session['current_question']
    total_q      = session['total_questions']
    timer        = session['timer_seconds']
    chat_id      = session['chat_id']
    retry_count  = session['retry_count'] or 0

    if current_q >= total_q:
        mark_session_completed(conn, session_id)
        return

    token = decrypt_token(session['bot_token_encrypted'])
    if not token:
        mark_session_failed(conn, session_id, "Could not decrypt bot token")
        return

    try:
        question_ids = json.loads(session['question_ids_json'])
    except (json.JSONDecodeError, TypeError):
        mark_session_failed(conn, session_id, "Invalid question_ids_json")
        return

    if current_q >= len(question_ids):
        mark_session_completed(conn, session_id)
        return

    question_id  = question_ids[current_q]
    question_num = current_q + 1
    question     = get_question(conn, question_id)

    if not question:
        log.warning(f"Session #{session_id}: Question ID {question_id} not found, skipping")
        with conn.cursor() as cur:
            cur.execute("UPDATE tg_quiz_sessions SET current_question=current_question+1, updated_at=NOW() WHERE id=%s", (session_id,))
            cur.execute("INSERT INTO tg_quiz_logs (session_id, question_id, question_number, status) VALUES (%s, %s, %s, 'SKIPPED')", (session_id, question_id, question_num))
        conn.commit()
        return

    log.info(f"Session #{session_id}: Sending Q{question_num}/{total_q} to chat {chat_id}")

    result = send_quiz_poll(token, int(chat_id), question, timer)

    if result.get('ok'):
        poll_id = str(result.get('result', {}).get('poll', {}).get('id', ''))
        mark_question_sent(conn, session_id, question_id, question_num, timer, poll_id)
        log.info(f"Session #{session_id}: Q{question_num} sent ✅. Next in {timer}s")
        time.sleep(RATE_LIMIT_DELAY)
    else:
        error_desc = result.get('description', 'Unknown error')
        new_retry  = retry_count + 1

        if '429' in str(result) or 'Too Many Requests' in error_desc:
            retry_after = result.get('parameters', {}).get('retry_after', 30)
            log.warning(f"Session #{session_id}: Rate limited. Waiting {retry_after}s")
            with conn.cursor() as cur:
                next_at = datetime.now() + timedelta(seconds=int(retry_after))
                cur.execute("UPDATE tg_quiz_sessions SET next_question_at=%s, updated_at=NOW() WHERE id=%s", (next_at, session_id))
            conn.commit()
            time.sleep(int(retry_after))
            return

        log.error(f"Session #{session_id}: Q{question_num} failed (retry {new_retry}/{MAX_RETRIES}): {error_desc}")
        mark_question_failed(conn, session_id, question_id, question_num, error_desc, new_retry)

        if new_retry >= MAX_RETRIES:
            log.warning(f"Session #{session_id}: Max retries reached for Q{question_num}. Skipping question.")
            next_at = datetime.now() + timedelta(seconds=timer)
            with conn.cursor() as cur:
                cur.execute("""
                    UPDATE tg_quiz_sessions
                    SET current_question=current_question+1, retry_count=0, next_question_at=%s, updated_at=NOW()
                    WHERE id=%s
                """, (next_at, session_id))
            conn.commit()
        else:
            next_at = datetime.now() + timedelta(seconds=RETRY_DELAY)
            with conn.cursor() as cur:
                cur.execute("UPDATE tg_quiz_sessions SET next_question_at=%s, updated_at=NOW() WHERE id=%s", (next_at, session_id))
            conn.commit()


def main_db_mode():
    """Main loop using Direct DB mode (original behavior)."""
    import pymysql

    log.info("=" * 60)
    log.info("Telegram Quiz Worker started — Direct DB Mode")
    log.info(f"DB: {DB_CONFIG['host']}/{DB_CONFIG['db']}")
    log.info(f"Poll interval: {POLL_INTERVAL}s | Max retries: {MAX_RETRIES}")
    log.info("=" * 60)

    conn = None
    consecutive_errors = 0

    while True:
        try:
            if conn is None or not conn.open:
                log.info("Connecting to database...")
                conn = get_db_connection()
                log.info("Database connected ✅")
                consecutive_errors = 0

            sessions = get_active_sessions(conn)

            sync_poll_answers_db(conn)

            if sessions:
                log.info(f"Found {len(sessions)} active session(s)")
                for session in sessions:
                    try:
                        process_session(conn, session)
                    except Exception as e:
                        log.error(f"Error processing session #{session.get('id')}: {e}")
                        log.debug(traceback.format_exc())
                        try: conn.rollback()
                        except: pass

            time.sleep(POLL_INTERVAL)

        except pymysql.OperationalError as e:
            consecutive_errors += 1
            log.error(f"DB connection error (attempt {consecutive_errors}): {e}")
            try:
                if conn: conn.close()
            except: pass
            conn = None
            sleep_time = min(30, POLL_INTERVAL * consecutive_errors)
            log.info(f"Retrying DB connection in {sleep_time}s...")
            time.sleep(sleep_time)

        except KeyboardInterrupt:
            log.info("Worker stopped by user (Ctrl+C)")
            if conn:
                try: conn.close()
                except: pass
            sys.exit(0)

        except Exception as e:
            log.error(f"Unexpected error: {e}")
            log.debug(traceback.format_exc())
            time.sleep(POLL_INTERVAL)


# ═══════════════════════════════════════════════════════════════════════════════
#  ENTRY POINT
# ═══════════════════════════════════════════════════════════════════════════════

def main():
    if USE_API_MODE:
        main_api_mode()
    else:
        main_db_mode()


if __name__ == '__main__':
    main()
