#!/usr/bin/env python3
"""
Telegram Bulk Quiz Worker
File: python/tg_quiz_worker.py

This background worker:
1. Polls the database every few seconds
2. Finds RUNNING quiz sessions where next_question_at <= NOW()
3. Sends the next question to Telegram as a Quiz Poll
4. Updates session state in DB
5. Handles errors, retries, and rate limits

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

import pymysql
import requests
from dotenv import load_dotenv
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend
import base64
import hashlib

# ─── Setup ───────────────────────────────────────────────────────────────────

# Load .env from the python/ directory
env_path = Path(__file__).parent / '.env'
load_dotenv(dotenv_path=env_path)

# Logging — NEVER log bot tokens
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler(Path(__file__).parent / 'worker.log', encoding='utf-8'),
    ]
)
log = logging.getLogger('tg_quiz_worker')

# ─── Config ──────────────────────────────────────────────────────────────────

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
POLL_INTERVAL      = int(os.getenv('POLL_INTERVAL_SECONDS', '5'))   # How often worker checks DB
MAX_RETRIES        = int(os.getenv('MAX_RETRIES', '3'))
RETRY_DELAY        = int(os.getenv('RETRY_DELAY_SECONDS', '10'))
TELEGRAM_API_BASE  = 'https://api.telegram.org/bot{token}/{method}'
RATE_LIMIT_DELAY   = 1.1  # seconds between Telegram API calls (respects 1 msg/sec limit)

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

# ─── Database ────────────────────────────────────────────────────────────────

def get_db_connection():
    """Get a fresh DB connection."""
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

# ─── Telegram API ────────────────────────────────────────────────────────────

def send_quiz_poll(token: str, chat_id: int, question: dict) -> dict:
    """
    Send a Telegram Quiz Poll.
    Returns the Telegram API response dict.
    Token is NEVER logged.
    """
    # Build options list (filter empty options)
    options = [question['option_a'], question['option_b']]
    if question.get('option_c'): options.append(question['option_c'])
    if question.get('option_d'): options.append(question['option_d'])

    # Map correct_answer letter to 0-indexed position
    answer_map = {'A': 0, 'B': 1, 'C': 2, 'D': 3}
    correct_idx = answer_map.get(question['correct_answer'].upper(), 0)
    # Clamp to valid range
    correct_idx = min(correct_idx, len(options) - 1)

    payload = {
        'chat_id':              chat_id,
        'question':             question['question_text'][:300],  # Telegram limit
        'options':              options,
        'type':                 'quiz',
        'correct_option_id':    correct_idx,
        'is_anonymous':         True,
        'allows_multiple_answers': False,
    }

    if question.get('explanation'):
        payload['explanation'] = question['explanation'][:200]  # Telegram limit

    url = TELEGRAM_API_BASE.format(token=token, method='sendPoll')
    try:
        resp = requests.post(url, json=payload, timeout=15)
        return resp.json()
    except requests.RequestException as e:
        return {'ok': False, 'description': str(e)}

# ─── Main Loop ───────────────────────────────────────────────────────────────

def process_session(conn, session: dict):
    """Process one quiz session — send the next question."""
    session_id   = session['id']
    current_q    = session['current_question']   # 0-indexed index into question_ids_json
    total_q      = session['total_questions']
    timer        = session['timer_seconds']
    chat_id      = session['chat_id']
    retry_count  = session['retry_count'] or 0

    # Check if all questions sent
    if current_q >= total_q:
        mark_session_completed(conn, session_id)
        return

    # Decrypt bot token
    token = decrypt_token(session['bot_token_encrypted'])
    if not token:
        mark_session_failed(conn, session_id, "Could not decrypt bot token")
        return

    # Get the question ID list
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
        # Skip this question
        with conn.cursor() as cur:
            cur.execute("UPDATE tg_quiz_sessions SET current_question=current_question+1, updated_at=NOW() WHERE id=%s", (session_id,))
            cur.execute("INSERT INTO tg_quiz_logs (session_id, question_id, question_number, status) VALUES (%s, %s, %s, 'SKIPPED')", (session_id, question_id, question_num))
        conn.commit()
        return

    log.info(f"Session #{session_id}: Sending Q{question_num}/{total_q} to chat {chat_id}")

    result = send_quiz_poll(token, int(chat_id), question)

    if result.get('ok'):
        poll_id = str(result.get('result', {}).get('poll', {}).get('id', ''))
        mark_question_sent(conn, session_id, question_id, question_num, timer, poll_id)
        log.info(f"Session #{session_id}: Q{question_num} sent ✅. Next in {timer}s")
        # Rate limit respect
        time.sleep(RATE_LIMIT_DELAY)
    else:
        error_desc = result.get('description', 'Unknown error')
        new_retry  = retry_count + 1

        # Handle rate limit (Telegram 429)
        if '429' in str(result) or 'Too Many Requests' in error_desc:
            retry_after = result.get('parameters', {}).get('retry_after', 30)
            log.warning(f"Session #{session_id}: Rate limited. Waiting {retry_after}s")
            # Set next_question_at to retry_after seconds from now
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
            # Skip this question and continue
            next_at = datetime.now() + timedelta(seconds=timer)
            with conn.cursor() as cur:
                cur.execute("""
                    UPDATE tg_quiz_sessions
                    SET current_question=current_question+1, retry_count=0, next_question_at=%s, updated_at=NOW()
                    WHERE id=%s
                """, (next_at, session_id))
            conn.commit()
        else:
            # Schedule retry in RETRY_DELAY seconds
            next_at = datetime.now() + timedelta(seconds=RETRY_DELAY)
            with conn.cursor() as cur:
                cur.execute("UPDATE tg_quiz_sessions SET next_question_at=%s, updated_at=NOW() WHERE id=%s", (next_at, session_id))
            conn.commit()


def main():
    log.info("=" * 60)
    log.info("Telegram Quiz Worker started")
    log.info(f"DB: {DB_CONFIG['host']}/{DB_CONFIG['db']}")
    log.info(f"Poll interval: {POLL_INTERVAL}s | Max retries: {MAX_RETRIES}")
    log.info("=" * 60)

    conn = None
    consecutive_errors = 0

    while True:
        try:
            # Reconnect if needed
            if conn is None or not conn.open:
                log.info("Connecting to database...")
                conn = get_db_connection()
                log.info("Database connected ✅")
                consecutive_errors = 0

            sessions = get_active_sessions(conn)

            if sessions:
                log.info(f"Found {len(sessions)} active session(s)")
                for session in sessions:
                    try:
                        process_session(conn, session)
                    except Exception as e:
                        log.error(f"Error processing session #{session.get('id')}: {e}")
                        log.debug(traceback.format_exc())
                        # Try to rollback
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


if __name__ == '__main__':
    main()
