<?php
// includes/error_handler.php

// Ensure logs directory exists
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log file path
define('ERROR_LOG_FILE', $log_dir . '/error.log');

/**
 * Custom Error Handler
 * Converts all errors into ErrorExceptions so they can be caught globally.
 */
function custom_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}

/**
 * Global Exception Handler
 * Logs the error securely and prevents the site from crashing with raw output.
 */
function global_exception_handler($exception) {
    // 1. Log the error securely to the backend file
    $log_message = sprintf(
        "[%s] Exception: %s in %s on line %d\nStack trace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    error_log($log_message, 3, ERROR_LOG_FILE);

    // 2. Clear any output buffers to prevent partial page rendering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // 3. Display a friendly, generic error page to the user
    http_response_code(500);
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Oops! Something went wrong</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
        <div class="text-center p-5 bg-white shadow rounded">
            <h1 class="display-1 fw-bold text-warning">500</h1>
            <h3 class="mb-4">Internal Server Error</h3>
            <p class="text-muted mb-4">We encountered an unexpected condition that prevented us from fulfilling the request. <br>Our engineers have been notified and are working to resolve the issue.</p>
            <a href="/vishwkarma/index.php" class="btn btn-warning fw-bold">Return to Homepage</a>
        </div>
    </body>
    </html>';
    
    // Stop script execution
    exit;
}

// Register the handlers
set_error_handler('custom_error_handler');
set_exception_handler('global_exception_handler');

// Disable raw display of errors to the screen
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
?>
