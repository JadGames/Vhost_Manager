<?php declare(strict_types=1); ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">First-time setup wizard</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Setup: Review & Confirm</h1>
        <p class="auth-subtitle">Step 3 of 3: Review your settings</p>

        <div class="confirm-card">
            <h3 class="confirm-card-title">Admin Account</h3>
            <div class="confirm-grid">
                <strong>Full Name:</strong>
                <span><?= e((string) $summary['admin_full_name']) ?></span>
                <strong>Email:</strong>
                <span><?= e((string) $summary['admin_email']) ?></span>
                <strong>Password:</strong>
                <span class="confirm-secret" data-secret-value="<?= e((string) $summary['admin_password']) ?>">
                    ••••••••••
                    <button type="button" class="confirm-secret-toggle" data-secret-index="0" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                </span>
            </div>
        </div>

        <div class="confirm-card">
            <h3 class="confirm-card-title">App Settings</h3>
            <div class="confirm-grid">
                <strong>App URL:</strong>
                <span><?= e((string) $summary['app_url']) ?></span>
                <strong>Protocol:</strong>
                <span><?= $summary['app_https'] ? 'HTTPS' : 'HTTP' ?></span>
                <strong>Allowed Docroot Bases:</strong>
                <span><?= e((string) $summary['allowed_docroot_bases']) ?></span>
                <strong>Default Docroot Base:</strong>
                <span><?= e((string) $summary['default_docroot_base']) ?></span>
            </div>
        </div>

        <div class="confirm-card">
            <h3 class="confirm-card-title">Reverse Proxy</h3>
            <div class="confirm-grid">
                <strong>Mode:</strong>
                <span>
                    <?php 
                    $modes = [
                        'builtin_npm' => 'Built-in NPM (Docker)',
                        'external_npm' => 'External NPM',
                        'disabled' => 'Disabled'
                    ];
                    echo e($modes[$summary['proxy_mode']] ?? $summary['proxy_mode']);
                    ?>
                </span>
                <?php if ($summary['proxy_mode'] === 'builtin_npm'): ?>
                    <strong>NPM Admin Email:</strong>
                    <span><?= e((string) $summary['builtin_npm_identity']) ?></span>
                    <strong>NPM Admin Password:</strong>
                    <span class="confirm-secret" data-secret-value="<?= e((string) $summary['builtin_npm_secret']) ?>">
                        ••••••••••
                        <button type="button" class="confirm-secret-toggle" data-secret-index="1" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                    </span>
                    <strong>Status:</strong>
                    <span><i class="fa-solid fa-check" style="color: var(--accent);"></i> Connection successful</span>
                <?php elseif ($summary['proxy_mode'] === 'external_npm'): ?>
                    <strong>NPM URL:</strong>
                    <span><?= e((string) $summary['npm_base_url']) ?></span>
                    <strong>NPM Email:</strong>
                    <span><?= e((string) $summary['npm_identity']) ?></span>
                    <strong>Forward To:</strong>
                    <span><?= e((string) $summary['npm_forward_host']) ?>:<?= e((string) $summary['npm_forward_port']) ?></span>
                    <strong>Status:</strong>
                    <span><i class="fa-solid fa-check" style="color: var(--accent);"></i> Connection successful</span>
                <?php endif; ?>
            </div>
        </div>

        <form class="form" method="post" action="/?route=setup-confirm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

            <div style="display:flex; gap:8px;">
                <a href="/?route=setup-integration" class="btn btn--secondary" style="flex:1; text-align:center; text-decoration:none;">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <button class="btn btn--success" type="submit" style="flex:1;">
                    <i class="fa-solid fa-check"></i> Complete Setup & Login
                </button>
            </div>
        </form>
    </div>
</div>
