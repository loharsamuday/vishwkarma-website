<?php
$page_title = "1-Click Backup / Live Deploy";
require_once '../includes/db.php';
require_once '../includes/session.php';

// Only admins can access this
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';
$messageType = '';

// Helper to execute commands and return output safely
function runGitCommand($command) {
    // Escaping is dangerous with tokens, so we use direct passthru or shell_exec securely
    $output = [];
    $return_var = 0;
    // 2>&1 to capture stderr as well
    exec($command . ' 2>&1', $output, $return_var);
    return [
        'output' => implode("\n", $output),
        'status' => $return_var
    ];
}

// Helper to mask token in URL
function maskTokenInUrl($url) {
    if (empty($url)) return '';
    return preg_replace('/https:\/\/([^\:]+):?([^@]*)@/', 'https://***:***@', $url);
}
function maskTokenInString($string, $token) {
    if (empty($token)) return $string;
    return str_replace($token, '***HIDDEN_TOKEN***', $string);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        // Save Settings
        $repo_url = trim($_POST['git_repo_url'] ?? '');
        $git_token = trim($_POST['git_token'] ?? '');
        
        // Save to settings table
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(['git_repo_url', $repo_url, $repo_url]);
        $stmt->execute(['git_token', $git_token, $git_token]);
        
        setFlashMessage('success', "Settings saved successfully!");
        header("Location: git_manager.php");
        exit();
    } 
    elseif (isset($_POST['push_code'])) {
        // Run Git Push
        $commit_msg = trim($_POST['commit_message'] ?? 'Auto update from Admin Panel');
        if (empty($commit_msg)) {
            $commit_msg = 'Auto update from Admin Panel';
        }

        // Fetch settings
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('git_repo_url', 'git_token')");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $repo_url = $settings['git_repo_url'] ?? '';
        $token = $settings['git_token'] ?? '';

        if (empty($repo_url) || empty($token)) {
            setFlashMessage('danger', "Please configure your GitHub Repository URL and Token first.");
            header("Location: git_manager.php");
            exit();
        } else {
            // Construct Auth URL (e.g., https://TOKEN@github.com/user/repo.git)
            $url_parts = parse_url($repo_url);
            if (!isset($url_parts['host'])) {
                setFlashMessage('danger', "Invalid GitHub Repository URL.");
                header("Location: git_manager.php");
                exit();
            } else {
                $host = $url_parts['host'];
                $path = $url_parts['path'] ?? '';
                // Remove leading slash if any
                $path = ltrim($path, '/');
                $auth_url = "https://{$token}@{$host}/{$path}";
                
                // Change to project root directory
                chdir(dirname(__DIR__));
                
                $log = "";
                
                // 1. Init if needed
                $res = runGitCommand('git init');
                $log .= "> git init\n" . $res['output'] . "\n\n";
                
                // 2. Config (Safe defaults so it doesn't fail on new servers)
                runGitCommand('git config --global user.name "Vishwkarma Admin"');
                runGitCommand('git config --global user.email "admin@vishwkarma.com"');
                // Allow long paths on windows
                runGitCommand('git config --system core.longpaths true');

                // 3. Add remote
                runGitCommand('git remote remove origin'); // Remove old if exists
                $res = runGitCommand('git remote add origin ' . escapeshellarg($auth_url));
                $log .= "> git remote add origin ***\n" . maskTokenInString($res['output'], $token) . "\n\n";
                
                // 4. Stage files
                $res = runGitCommand('git add .');
                $log .= "> git add .\n" . $res['output'] . "\n\n";
                
                // 5. Commit
                $res = runGitCommand('git commit -m ' . escapeshellarg($commit_msg));
                $log .= "> git commit -m \"{$commit_msg}\"\n" . $res['output'] . "\n\n";
                
                // 6. Branch (ensure main)
                runGitCommand('git branch -M main');
                
                // 7. Push
                $res = runGitCommand('git push -u origin main');
                $log .= "> git push -u origin main\n" . maskTokenInString($res['output'], $token) . "\n\n";

                if ($res['status'] === 0) {
                    setFlashMessage('success', "Code successfully pushed to GitHub! If you configured GitHub Actions, it will deploy to InfinityFree within a minute.");
                } else {
                    setFlashMessage('danger', "An error occurred while pushing. See logs below.");
                }
                $_SESSION['push_log'] = $log;
                header("Location: git_manager.php");
                exit();
            }
        }
    }
}

// Retrieve push log from session if exists
$pushLog = $_SESSION['push_log'] ?? null;
unset($_SESSION['push_log']);

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('git_repo_url', 'git_token')");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$current_repo = $settings['git_repo_url'] ?? '';
$current_token = $settings['git_token'] ?? '';

require_once 'includes/header.php';
?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
            <div class="d-flex align-items-center">
                <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h3 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-cloud-arrow-up me-2"></i> 1-Click Backup & Live Deploy</h3>
                    <p class="text-muted mb-0 small">Push your codebase to GitHub and trigger automatic deployment to InfinityFree.</p>
                </div>
            </div>
            <div>
                <a href="../" target="_blank" class="btn btn-sm btn-outline-primary">View Website</a>
            </div>
        </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-4">
        <!-- Push Card -->
        <div class="col-xl-8 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pb-3 pt-4">
                    <h5 class="fw-bold mb-0"><i class="fa-brands fa-github text-dark me-2"></i> Deploy to Live Server</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($current_repo) || empty($current_token)): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i> You must configure your GitHub Repository URL and Token first.
                        </div>
                    <?php else: ?>
                        <form method="POST" onsubmit="
                            document.getElementById('pushBtn').innerHTML = '<i class=\'fa-solid fa-circle-notch fa-spin me-2\'></i> Pushing... Please wait'; 
                            document.getElementById('pushBtn').classList.add('disabled'); 
                            // We don't use .disabled=true because that prevents the button's name from being sent in the POST request.
                        ">
                            <input type="hidden" name="push_code" value="1">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Commit Message (What did you change?)</label>
                                <input type="text" name="commit_message" class="form-control form-control-lg" placeholder="e.g., Updated homepage banners and about section" required>
                                <div class="form-text">This helps you track changes in your GitHub history.</div>
                            </div>
                            
                            <div class="alert alert-info border-info bg-info bg-opacity-10 mb-4">
                                <h6 class="fw-bold text-info-emphasis"><i class="fa-solid fa-info-circle me-2"></i> What happens when I click Push?</h6>
                                <ul class="mb-0 text-info-emphasis small">
                                    <li>All modified, deleted, and new files in this local server will be packaged.</li>
                                    <li>The package will be securely uploaded to <strong><?= htmlspecialchars($current_repo) ?></strong>.</li>
                                    <li>If you have GitHub Actions configured, GitHub will automatically sync these files to InfinityFree via FTP.</li>
                                </ul>
                            </div>

                            <button type="submit" name="push_code" id="pushBtn" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">
                                <i class="fa-solid fa-rocket me-2"></i> Push & Deploy Now
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Logs Section -->
                    <?php if (isset($pushLog)): ?>
                        <div class="mt-5">
                            <h6 class="fw-bold mb-3"><i class="fa-solid fa-terminal me-2"></i> Execution Logs</h6>
                            <div class="bg-dark text-success p-3 rounded" style="font-family: monospace; font-size: 0.85rem; max-height: 400px; overflow-y: auto; white-space: pre-wrap;"><?= htmlspecialchars($pushLog) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Configuration Card -->
        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pb-3 pt-4">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-gear text-secondary me-2"></i> Configuration</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">GitHub Repository URL</label>
                            <input type="url" name="git_repo_url" class="form-control" value="<?= htmlspecialchars($current_repo) ?>" placeholder="https://github.com/username/repo.git" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Personal Access Token (PAT)</label>
                            <input type="password" name="git_token" class="form-control" value="<?= htmlspecialchars($current_token) ?>" placeholder="ghp_xxxxxxxxxxxx" required>
                            <div class="form-text">
                                Your token is stored securely in the database. <a href="https://github.com/settings/tokens" target="_blank">Generate a token here</a> (Requires 'repo' scope).
                            </div>
                        </div>
                        <button type="submit" name="save_settings" class="btn btn-dark w-100 fw-bold">
                            <i class="fa-solid fa-save me-2"></i> Save Configuration
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>
