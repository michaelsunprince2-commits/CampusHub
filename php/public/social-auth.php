<?php

/**
 * Social Authentication Entry Point
 */

$pageTitle = 'Social Sign In';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/User.php';

if (isAuthenticated()) {
    header('Location: ' . pageUrl('index.php'));
    exit();
}

$oauthConfig = require '../config/oauth.php';

$providers = [
    'google' => [
        'name' => 'Google',
        'client_id' => $oauthConfig['google']['client_id'] ?? '',
        'client_secret' => $oauthConfig['google']['client_secret'] ?? '',
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'userinfo_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
        'scopes' => 'openid email profile',
    ],
    'apple' => [
        'name' => 'Apple ID',
        'client_id' => $oauthConfig['apple']['client_id'] ?? '',
        'client_secret' => $oauthConfig['apple']['client_secret'] ?? '',
        'implemented' => false,
    ],
    'github' => [
        'name' => 'GitHub',
        'client_id' => $oauthConfig['github']['client_id'] ?? '',
        'client_secret' => $oauthConfig['github']['client_secret'] ?? '',
        'auth_url' => 'https://github.com/login/oauth/authorize',
        'token_url' => 'https://github.com/login/oauth/access_token',
        'userinfo_url' => 'https://api.github.com/user',
        'emails_url' => 'https://api.github.com/user/emails',
        'scopes' => 'read:user user:email',
        'implemented' => true,
    ],
];

function httpPostForm($url, $fields, $headers = [])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        throw new Exception($error ?: 'OAuth token request failed');
    }

    return json_decode($response, true);
}

function httpGetJson($url, $accessToken)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
            'User-Agent: CampusNest-OAuth',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        throw new Exception($error ?: 'OAuth profile request failed');
    }

    return json_decode($response, true);
}

function getPrimaryGithubEmail($emails)
{
    if (!is_array($emails)) {
        return '';
    }

    foreach ($emails as $email) {
        if (!empty($email['primary']) && !empty($email['verified']) && !empty($email['email'])) {
            return strtolower($email['email']);
        }
    }

    foreach ($emails as $email) {
        if (!empty($email['verified']) && !empty($email['email'])) {
            return strtolower($email['email']);
        }
    }

    return '';
}

function startLocalSession($conn, $user)
{
    $sessionToken = generateSessionToken();
    createUserSession($conn, $user['id'], $sessionToken);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_token'] = $sessionToken;
}

function findUserByEmail($conn, $email)
{
    $stmt = $conn->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function createOAuthUser($conn, $email, $firstName, $lastName, $role = 'student')
{
    $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
    $stmt = $conn->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, role, is_verified, verification_date)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->bind_param("sssss", $email, $passwordHash, $firstName, $lastName, $role);

    if (!$stmt->execute()) {
        throw new Exception('Could not create OAuth user account');
    }

    return [
        'id' => $conn->insert_id,
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'role' => $role,
    ];
}

$providerKey = strtolower($_GET['provider'] ?? '');
$mode = strtolower($_GET['mode'] ?? ($_SESSION['oauth_mode'] ?? 'login'));

if (!isset($providers[$providerKey])) {
    $providerKey = '';
}

if (!in_array($mode, ['login', 'register'], true)) {
    $mode = 'login';
}

$provider = $providerKey ? $providers[$providerKey] : null;
$clientId = $provider['client_id'] ?? '';
$clientSecret = $provider['client_secret'] ?? '';
$isConfigured = !empty($clientId) && !empty($clientSecret);
$isImplemented = $providerKey === 'google' || !empty($provider['implemented']);
$redirectUri = pageUrl('social-auth.php?provider=' . $providerKey);
$error = '';

if (in_array($providerKey, ['google', 'github'], true) && $isConfigured) {
    try {
        if (!empty($_GET['error'])) {
            throw new Exception($provider['name'] . ' sign-in was cancelled or denied.');
        }

        if (!empty($_GET['code'])) {
            if (empty($_GET['state']) || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $_GET['state'])) {
                throw new Exception('Invalid OAuth state. Please try again.');
            }

            $redirectUri = $_SESSION['oauth_redirect_uri'] ?? $redirectUri;
            $tokenFields = [
                'code' => $_GET['code'],
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
            ];

            if ($providerKey === 'google') {
                $tokenFields['grant_type'] = 'authorization_code';
            }

            $token = httpPostForm(
                $provider['token_url'],
                $tokenFields,
                $providerKey === 'github' ? ['Accept: application/json'] : []
            );

            if (empty($token['access_token'])) {
                throw new Exception($provider['name'] . ' did not return an access token.');
            }

            $remoteUser = httpGetJson($provider['userinfo_url'], $token['access_token']);

            if ($providerKey === 'github') {
                $email = strtolower($remoteUser['email'] ?? '');
                if ($email === '') {
                    $email = getPrimaryGithubEmail(httpGetJson($provider['emails_url'], $token['access_token']));
                }

                if ($email === '') {
                    throw new Exception('GitHub did not return a verified email address.');
                }

                $nameParts = preg_split('/\s+/', trim($remoteUser['name'] ?? $remoteUser['login'] ?? 'GitHub User'), 2);
                $firstName = $nameParts[0] ?? 'GitHub';
                $lastName = $nameParts[1] ?? '';
            } else {
                if (empty($remoteUser['email'])) {
                    throw new Exception('Google did not return an email address.');
                }

                if (isset($remoteUser['email_verified']) && !$remoteUser['email_verified']) {
                    throw new Exception('Please verify your Google email before signing in.');
                }

                $email = strtolower($remoteUser['email']);
                $firstName = $remoteUser['given_name'] ?? strtok($remoteUser['name'] ?? 'Google', ' ');
                $lastName = $remoteUser['family_name'] ?? '';
            }

            $localUser = findUserByEmail($conn, $email);
            if (!$localUser) {
                $localUser = createOAuthUser($conn, $email, $firstName, $lastName, 'student');
            }

            unset($_SESSION['oauth_state'], $_SESSION['oauth_redirect_uri'], $_SESSION['oauth_mode']);
            startLocalSession($conn, $localUser);

            header('Location: ' . pageUrl('index.php'));
            exit();
        }

        $_SESSION['oauth_state'] = bin2hex(random_bytes(32));
        $_SESSION['oauth_redirect_uri'] = $redirectUri;
        $_SESSION['oauth_mode'] = $mode;

        $authParams = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $provider['scopes'],
            'state' => $_SESSION['oauth_state'],
        ];

        if ($providerKey === 'google') {
            $authParams += [
            'access_type' => 'online',
            'prompt' => 'select_account',
            ];
        }

        $authUrl = $provider['auth_url'] . '?' . http_build_query($authParams);

        header('Location: ' . $authUrl);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once '../templates/header.php';
?>

<style>
    .social-auth-page {
        max-width: 560px;
        margin: 2rem auto;
        background: #ffffff;
        border: 1px solid #e5edf2;
        border-radius: 8px;
        padding: 2rem;
        box-shadow: 0 6px 18px rgba(44, 62, 80, 0.08);
    }

    .social-auth-page h1 {
        margin-bottom: 0.75rem;
    }

    .social-auth-page p {
        color: #657786;
    }

    .setup-list {
        margin: 1rem 0 1.5rem;
        padding-left: 1.25rem;
        color: #2c3e50;
    }

    .setup-list li {
        margin-bottom: 0.5rem;
    }

    .auth-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
</style>

<div class="social-auth-page">
    <?php if (!$provider): ?>
        <div class="alert alert-error">Unknown social sign-in provider.</div>
        <div class="auth-actions">
            <a href="<?php echo pageUrl('login.php'); ?>" class="btn">Back to Login</a>
            <a href="<?php echo pageUrl('register.php'); ?>" class="btn btn-success">Create Account</a>
        </div>
    <?php elseif ($error): ?>
        <h1><?php echo htmlspecialchars($provider['name']); ?> Sign In</h1>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <div class="auth-actions">
            <a href="<?php echo pageUrl('login.php'); ?>" class="btn">Back to Login</a>
            <a href="<?php echo pageUrl('register.php'); ?>" class="btn btn-success">Create Account</a>
        </div>
    <?php elseif (!$isImplemented): ?>
        <h1><?php echo htmlspecialchars($provider['name']); ?> Coming Soon</h1>
        <div class="alert alert-info">
            <?php if ($providerKey === 'apple'): ?>
                Apple Sign In for websites requires an HTTPS domain, an Apple Developer account, a Services ID, and a generated client secret JWT. It cannot be completed on localhost.
            <?php else: ?>
                <?php echo htmlspecialchars($provider['name']); ?> sign-in is shown as an upcoming option, but it has not been implemented yet.
            <?php endif; ?>
        </div>
        <p>Please continue with email login or Google if Google OAuth has been configured.</p>
        <div class="auth-actions">
            <a href="<?php echo pageUrl('login.php'); ?>" class="btn">Use Email Login</a>
            <a href="<?php echo pageUrl('register.php'); ?>" class="btn btn-success">Create Account</a>
        </div>
    <?php elseif (!$isConfigured): ?>
        <h1><?php echo htmlspecialchars($provider['name']); ?> <?php echo $mode === 'register' ? 'Sign Up' : 'Login'; ?></h1>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($provider['name']); ?> sign-in is visible now, but OAuth credentials have not been configured yet.
        </div>
        <p>For Google, create an OAuth Client ID in Google Cloud Console and add this exact redirect URI:</p>
        <p><code><?php echo htmlspecialchars($redirectUri); ?></code></p>
        <p>Then put the credentials in <code>php/config/oauth.php</code> or set these environment variables:</p>
        <ul class="setup-list">
            <li><code><?php echo strtoupper($providerKey); ?>_CLIENT_ID</code></li>
            <li><code><?php echo strtoupper($providerKey); ?>_CLIENT_SECRET</code></li>
        </ul>
        <div class="auth-actions">
            <a href="<?php echo pageUrl('login.php'); ?>" class="btn">Use Email Login</a>
            <a href="<?php echo pageUrl('register.php'); ?>" class="btn btn-success">Use Email Sign Up</a>
        </div>
    <?php else: ?>
        <h1><?php echo htmlspecialchars($provider['name']); ?> Is Ready</h1>
        <div class="alert alert-info">Click the provider button again to continue.</div>
        <div class="auth-actions">
            <a href="<?php echo pageUrl('login.php'); ?>" class="btn">Back to Login</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../templates/footer.php'; ?>
