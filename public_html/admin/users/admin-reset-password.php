<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/user_actions_config.php';
require_once __DIR__ . '/../../../config/auth/admin_functions.php';

use Carbon\Carbon;

// 1. Security Check
startSession();
validateAdminRole();

// 2. CSRF Validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid CSRF token");
}

// 3. Get Input Data
$user_id = (int)$_POST['user_id'];
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

try {
    // 4. Database Connection
    $pdo = getPDOConnection($config, $env);

    // 5. Validate User Ownership
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND email = ?");
    $stmt->execute([$user_id, $email]);

    if (!$stmt->fetch()) {
        throw new Exception("Invalid user data");
    }

    // 6. Reuse Existing Functions
    $resetHash = generateActivationCode($email);
    $expiresAt = Carbon::now('UTC')->addHour()->format('Y-m-d H:i:s');

    // 7. Save to Database
    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("INSERT INTO password_resets (user_id, hash, expires_at) VALUES (?, ?, ?)")
        ->execute([$user_id, $resetHash, $expiresAt]);

    // 8. Send Email
    $resetLink = generateResetPasswordLink($resetHash);
    sendResetPasswordEmail($email, $resetLink);

    // 9. Log Activity
    error_log("Admin {$_SESSION['user_id']} reset password for {$email}");

    // 10. Redirect with Success
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'message' => "Reset password link sent to {$email}"
    ];
} catch (Exception $e) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => "Failed: " . $e->getMessage()
    ];
}

header("Location: " . getBaseUrl($config, $env) . "admin/manage_users.php");
exit();
