<?php
// api/tg_quiz/bot.php
// Telegram Bot Token Management API
// Admin-only — validates session on every request

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

// Admin auth check
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// CSRF token validation for state-changing requests
function validateCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }
}

// AES-256-CBC encryption for bot token
function encryptToken(string $token): string {
    $key = defined('TG_ENCRYPTION_KEY') ? TG_ENCRYPTION_KEY : (getenv('TG_ENCRYPTION_KEY') ?: 'default-32-char-key-change-this!!');
    $key = substr(hash('sha256', $key, true), 0, 32);
    $iv  = random_bytes(16);
    $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptToken(string $encrypted): string {
    $key = defined('TG_ENCRYPTION_KEY') ? TG_ENCRYPTION_KEY : (getenv('TG_ENCRYPTION_KEY') ?: 'default-32-char-key-change-this!!');
    $key = substr(hash('sha256', $key, true), 0, 32);
    $data = base64_decode($encrypted);
    $iv   = substr($data, 0, 16);
    $cipher = substr($data, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv) ?: '';
}

// Call Telegram Bot API safely (token never logged)
function callTelegramAPI(string $token, string $method, array $params = []): array {
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['ok' => false, 'description' => 'cURL error: ' . $error];
    }
    return json_decode($response, true) ?? ['ok' => false, 'description' => 'Invalid JSON response'];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$admin_id = (int)$_SESSION['admin_id'];

switch ($action) {

    // --------------------------------------------------------
    // GET status — return current bot info
    // --------------------------------------------------------
    case 'status':
        $stmt = $pdo->prepare("SELECT id, bot_name, bot_username, bot_id, is_active, last_verified_at FROM tg_bots WHERE admin_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$admin_id]);
        $bot = $stmt->fetch();
        echo json_encode(['success' => true, 'bot' => $bot ?: null]);
        break;

    // --------------------------------------------------------
    // POST connect — save encrypted bot token
    // --------------------------------------------------------
    case 'connect':
        validateCsrfToken();
        $raw_token = trim($_POST['bot_token'] ?? '');
        if (empty($raw_token)) {
            echo json_encode(['success' => false, 'message' => 'Bot token required']);
            break;
        }
        // Basic format validation
        if (!preg_match('/^\d+:[A-Za-z0-9_-]{35,}$/', $raw_token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid bot token format']);
            break;
        }

        // Test the token with Telegram API
        $result = callTelegramAPI($raw_token, 'getMe');
        if (!$result['ok']) {
            echo json_encode(['success' => false, 'message' => 'Telegram rejected token: ' . ($result['description'] ?? 'Unknown error')]);
            break;
        }

        $bot_info = $result['result'];
        $encrypted = encryptToken($raw_token);

        // Upsert bot record (one bot per admin)
        $stmt = $pdo->prepare("SELECT id FROM tg_bots WHERE admin_id = ? LIMIT 1");
        $stmt->execute([$admin_id]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $upd = $pdo->prepare("UPDATE tg_bots SET bot_token_encrypted=?, bot_name=?, bot_username=?, bot_id=?, is_active=1, last_verified_at=NOW(), updated_at=NOW() WHERE admin_id=?");
            $upd->execute([$encrypted, $bot_info['first_name'], $bot_info['username'], $bot_info['id'], $admin_id]);
        } else {
            $ins = $pdo->prepare("INSERT INTO tg_bots (admin_id, bot_token_encrypted, bot_name, bot_username, bot_id, last_verified_at) VALUES (?,?,?,?,?,NOW())");
            $ins->execute([$admin_id, $encrypted, $bot_info['first_name'], $bot_info['username'], $bot_info['id']]);
        }

        logActivity("Telegram bot connected: @{$bot_info['username']}", 'admin', null, $admin_id);
        echo json_encode([
            'success'      => true,
            'message'      => 'Bot connected successfully!',
            'bot_name'     => $bot_info['first_name'],
            'bot_username' => $bot_info['username'],
            'bot_id'       => $bot_info['id'],
        ]);
        break;

    // --------------------------------------------------------
    // POST test — test existing bot connection
    // --------------------------------------------------------
    case 'test':
        validateCsrfToken();
        $stmt = $pdo->prepare("SELECT bot_token_encrypted FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$admin_id]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No bot configured']);
            break;
        }
        $token  = decryptToken($row['bot_token_encrypted']);
        $result = callTelegramAPI($token, 'getMe');
        if ($result['ok']) {
            // Update verification timestamp
            $pdo->prepare("UPDATE tg_bots SET last_verified_at=NOW() WHERE admin_id=?")->execute([$admin_id]);
            echo json_encode(['success' => true, 'message' => '✅ Bot is alive and responding!', 'bot' => $result['result']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bot not responding: ' . ($result['description'] ?? 'Error')]);
        }
        break;

    // --------------------------------------------------------
    // POST disconnect — remove bot
    // --------------------------------------------------------
    case 'disconnect':
        validateCsrfToken();
        $pdo->prepare("UPDATE tg_bots SET is_active=0 WHERE admin_id=?")->execute([$admin_id]);
        echo json_encode(['success' => true, 'message' => 'Bot disconnected']);
        break;

    // --------------------------------------------------------
    // GET csrf — issue a CSRF token
    // --------------------------------------------------------
    case 'csrf':
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        echo json_encode(['token' => $_SESSION['csrf_token']]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
