<?php

/**
 * Login Page
 */

$pageTitle = 'Login';
require_once '../config/database.php';
require_once '../includes/functions.php';

// If already logged in, redirect
if (isAuthenticated()) {
    header('Location: ' . pageUrl('index.php'));
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeString($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } else {
        require_once '../models/User.php';
        $user = new User($conn);
        $result = $user->login($email, $password);

        if ($result['success']) {
            $sessionToken = generateSessionToken();
            createUserSession($conn, $result['user']['id'], $sessionToken);

            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['user_email'] = $result['user']['email'];
            $_SESSION['user_name'] = $result['user']['name'];
            $_SESSION['user_role'] = $result['user']['role'];
            $_SESSION['user_token'] = $sessionToken;

            header('Location: ' . pageUrl('index.php'));
            exit();
        } else {
            $error = $result['message'];
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

        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }

        .password-field {
            position: relative;
        }

        .password-field input {
            padding-right: 3.1rem;
        }

        .password-toggle {
            position: absolute;
            right: 0.55rem;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #1f6f78;
            cursor: pointer;
            display: grid;
            place-items: right;
            width: 2.1rem;
            height: 2.1rem;
            padding: 0;
            border-radius: 999px;
        }

        .password-toggle:hover {
            background: #eaf4f6;
        }

        .password-toggle svg {
            width: 1.2rem;
            height: 1.2rem;
            stroke: currentColor;
        }

        .password-toggle .icon-eye-off {
            display: none;
        }

        .password-toggle.is-visible .icon-eye {
            display: none;
        }

        .password-toggle.is-visible .icon-eye-off {
            display: block;
        }

        .password-toggle:focus {
            outline: 2px solid rgba(31, 111, 120, 0.25);
        }

        .form-group button:not(.password-toggle) {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 6px;
        }

        .auth-link {
            text-align: center;
            margin-top: 1rem;
        }

        .auth-link a {
            color: #3498db;
            text-decoration: none;
        }

        .auth-link a:hover {
            text-decoration: underline;
        }

        .social-auth {
            margin-top: 1.5rem;
        }

        .social-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0.25rem 0 1rem;
        }

        .social-divider::before,
        .social-divider::after {
            content: "";
            height: 1px;
            background: #e5edf2;
            flex: 1;
        }

        .social-auth-grid {
            display: grid;
            gap: 0.75rem;
        }

        .social-auth-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            min-height: 48px;
            border: 1px solid #d9e2e8;
            border-radius: 8px;
            color: #2c3e50;
            background: #ffffff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
        }

        .social-auth-btn:hover {
            color: #2c3e50;
            border-color: #9fb3c1;
            box-shadow: 0 6px 16px rgba(44, 62, 80, 0.08);
            text-decoration: none;
            transform: translateY(-1px);
        }

        .social-auth-disabled,
        .social-auth-disabled:hover {
            cursor: not-allowed;
            opacity: 0.62;
            box-shadow: none;
            transform: none;
            background: #f8fafb;
            color: #6b7c86;
        }

        .social-auth-mark {
            width: 24px;
            height: 24px;
            display: grid;
            place-items: center;
        }

        .social-auth-mark svg {
            width: 22px;
            height: 22px;
            display: block;
        }
    </style>

    <h1>Welcome Back</h1>
    <p class="auth-subtitle">Log in to manage your CampusNest account.</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-field">
                <input type="password" id="password" name="password" required>
                <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password">
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9.9 4.2A10.5 10.5 0 0 1 12 4c6.5 0 10 8 10 8a18.1 18.1 0 0 1-3.1 4.5" />
                        <path d="M14.1 14.1A3 3 0 0 1 9.9 9.9" />
                        <path d="M3 3l18 18" />
                        <path d="M6.6 6.6C3.7 8.6 2 12 2 12s3.5 8 10 8a10.3 10.3 0 0 0 4.1-.8" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="auth-link" style="text-align: right; margin-top: -0.35rem; margin-bottom: 1rem;">
            <a href="<?php echo pageUrl('forgot-password.php'); ?>">Forgot password?</a>
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Login</button>
        </div>
    </form>

    <?php $authMode = 'login';
    require '../templates/social-auth-buttons.php'; ?>

    <div class="auth-link">
        Don't have an account? <a href="<?php echo pageUrl('register.php'); ?>">Register here</a>
    </div>
</div>

<script>
    document.querySelectorAll('[data-password-toggle]').forEach(function(button) {
        button.addEventListener('click', function() {
            const input = document.getElementById(button.dataset.passwordToggle);
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            button.classList.toggle('is-visible', isHidden);
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
</script>

<?php require_once '../templates/footer.php'; ?>
