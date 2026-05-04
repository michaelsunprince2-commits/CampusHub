<?php

$authMode = $authMode ?? 'login';
$oauthConfig = require '../config/oauth.php';
$socialAuthProviders = [
    'google' => [
        'label' => 'Google',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.31 9.14 5.38 12 5.38z"/></svg>',
        'implemented' => true,
    ],
    'apple' => [
        'label' => 'Apple ID',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#111111" d="M16.37 1.43c0 1.14-.46 2.17-1.2 2.99-.8.88-2.12 1.56-3.18 1.47-.13-1.09.39-2.25 1.12-3.05.8-.88 2.2-1.55 3.26-1.41zM20.7 17.37c-.55 1.25-.82 1.8-1.53 2.91-.99 1.52-2.38 3.42-4.11 3.44-1.54.01-1.94-1-4.03-.99-2.09.01-2.53 1.01-4.07 1-1.73-.02-3.05-1.73-4.04-3.25-2.76-4.22-3.05-9.18-1.35-11.82 1.2-1.87 3.1-2.97 4.88-2.97 1.82 0 2.97 1 4.48 1 1.46 0 2.35-1 4.46-1 1.6 0 3.29.87 4.48 2.37-3.94 2.16-3.3 7.79.83 9.31z"/></svg>',
        'implemented' => false,
    ],
    'github' => [
        'label' => 'GitHub',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#24292F" d="M12 .5C5.65.5.5 5.65.5 12c0 5.09 3.29 9.4 7.86 10.93.58.11.79-.25.79-.56v-2.15c-3.2.7-3.87-1.36-3.87-1.36-.52-1.33-1.28-1.68-1.28-1.68-1.05-.72.08-.71.08-.71 1.16.08 1.77 1.19 1.77 1.19 1.03 1.76 2.7 1.25 3.36.96.1-.75.4-1.25.73-1.54-2.55-.29-5.24-1.28-5.24-5.68 0-1.26.45-2.28 1.19-3.09-.12-.29-.52-1.46.11-3.05 0 0 .97-.31 3.17 1.18.92-.26 1.91-.38 2.89-.39.98.01 1.97.13 2.89.39 2.2-1.49 3.17-1.18 3.17-1.18.63 1.59.23 2.76.11 3.05.74.81 1.19 1.83 1.19 3.09 0 4.41-2.69 5.38-5.25 5.67.41.36.78 1.06.78 2.14v3.16c0 .31.21.68.79.56A11.51 11.51 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5z"/></svg>',
        'implemented' => true,
    ],
];
?>

<div class="social-auth">
    <div class="social-divider">
        <span>or continue with</span>
    </div>

    <div class="social-auth-grid">
        <?php foreach ($socialAuthProviders as $providerKey => $provider): ?>
            <?php
            $configured = !empty($oauthConfig[$providerKey]['client_id']) && !empty($oauthConfig[$providerKey]['client_secret']);
            $enabled = $provider['implemented'] && $configured;
            ?>
            <?php if ($enabled): ?>
                <a
                    class="social-auth-btn social-auth-<?php echo htmlspecialchars($providerKey); ?>"
                    href="<?php echo pageUrl('social-auth.php?provider=' . urlencode($providerKey) . '&mode=' . urlencode($authMode)); ?>">
                    <span class="social-auth-mark"><?php echo $provider['icon']; ?></span>
                    <span>Continue with <?php echo htmlspecialchars($provider['label']); ?></span>
                </a>
            <?php else: ?>
                <button
                    class="social-auth-btn social-auth-<?php echo htmlspecialchars($providerKey); ?> social-auth-disabled"
                    type="button"
                    disabled
                    title="<?php echo htmlspecialchars($provider['implemented'] ? 'Configure OAuth credentials to enable this provider.' : 'This provider is coming soon.'); ?>">
                    <span class="social-auth-mark"><?php echo $provider['icon']; ?></span>
                    <span><?php echo htmlspecialchars($provider['label']); ?> <?php echo $provider['implemented'] ? 'not configured' : 'coming soon'; ?></span>
                </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
