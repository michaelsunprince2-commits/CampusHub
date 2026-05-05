<?php

/**
 * Forgot Password Page
 */

$pageTitle = 'Forgot Password';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/User.php';
require_once '../config/mail.php';

if (isAuthenticated()) {
    header('Location: ' . pageUrl('index.php'));
    exit();
}

$error = '';
$success = '';
$devResetUrl = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeString($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email is required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } else {
        $user = new User($conn);
        $reset = $user->createPasswordResetToken($email);
        $success = 'If that email exists, a password reset link has been sent.';

        if ($reset) {
            $resetUrl = pageUrl('reset-password.php?token=' . urlencode($reset['token']));
            $firstName = trim($reset['first_name'] ?: 'there');
            $subject = 'Reset your CampusNest password';
            $textBody = "Hi {$firstName},\n\nUse this link to reset your CampusNest password:\n{$resetUrl}\n\nThis link expires in 1 hour.";
            $htmlBody = '<p>Hi ' . htmlspecialchars($firstName) . ',</p>' .
                '<p>Use the button below to reset your CampusNest password. This link expires in 1 hour.</p>' .
                '<p><a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;padding:12px 18px;background:#1f6f78;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:700;">Reset Password</a></p>' .
                '<p>If the button does not work, open this link:<br>' . htmlspecialchars($resetUrl) . '</p>';

            if (!sendAppMail($reset['email'], $subject, $htmlBody, $textBody) && ENVIRONMENT === 'development') {
                $devResetUrl = $resetUrl;
            }
        }
    }
}

require_once '../templates/header.php';
?>

<div class="auth-container" style="max-width: 440px; margin: 2.5rem auto;">
    <style>
        .auth-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid #e5edf2;
            box-shadow: 0 8px 24px rgba(44, 62, 80, 0.08);
        }

        .auth-container h1 {
            text-align: center;
            margin-bottom: 0.35rem;
        }

        .auth-subtitle {
            text-align: center;
            color: #657786;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid #d9e2e8;
            border-radius: 6px;
            font-size: 1rem;
            background: #fbfcfd;
        }

        .form-group button {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 6px;
        }

        .auth-link {
            text-align: center;
            margin-top: 1rem;
        }

        .auth-link a,
        .dev-link a {
            color: #3498db;
            text-decoration: none;
            overflow-wrap: anywhere;
        }

        .auth-link a:hover,
        .dev-link a:hover {
            text-decoration: underline;
        }

        .dev-link {
            margin-top: 1rem;
            padding: 0.9rem;
            background: #fff9e8;
            border: 1px solid #f3dd9a;
            border-radius: 8px;
            color: #6f5200;
            font-size: 0.92rem;
            line-height: 1.45;
        }
    </style>

    <h1>Reset Password</h1>
    <p class="auth-subtitle">Enter your email and we will send a reset link.</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Send Reset Link</button>
        </div>
    </form>

    <?php if ($devResetUrl): ?>
        <div class="dev-link">
            Email sending is not configured locally. Development reset link:
            <br><a href="<?php echo htmlspecialchars($devResetUrl); ?>"><?php echo htmlspecialchars($devResetUrl); ?></a>
        </div>
    <?php endif; ?>

    <div class="auth-link">
        Remembered your password? <a href="<?php echo pageUrl('login.php'); ?>">Back to login</a>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
