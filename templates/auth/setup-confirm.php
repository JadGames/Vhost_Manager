<?php declare(strict_types=1); ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">First-time setup wizard</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Setup: Review & Confirm</h1>
        <?php 
            $totalSteps = ($enableIntegrations ?? true) ? 5 : 3;
        ?>
        <p class="auth-subtitle">Step <?= $totalSteps ?> of <?= $totalSteps ?>: Review your settings</p>

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
                <span><?= !empty($summary['app_https']) ? 'HTTPS' : 'HTTP' ?></span>
                <strong>Allowed Docroot Bases:</strong>
                <span><?= e((string) $summary['allowed_docroot_bases']) ?></span>
                <strong>Default Docroot Base:</strong>
                <span><?= e((string) $summary['default_docroot_base']) ?></span>
            </div>
        </div>

        <?php if (!empty($summary['setup_domains']) && is_array($summary['setup_domains']) && count($summary['setup_domains']) > 0): ?>
        <div class="confirm-card">
            <h3 class="confirm-card-title">Domains</h3>
            <div class="confirm-grid">
                <strong>Added Domains:</strong>
                <span><?= e(implode(', ', array_map(fn($d) => (string) $d, $summary['setup_domains']))) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($enableIntegrations ?? true)): ?>
        <div class="confirm-card">
            <h3 class="confirm-card-title">Integrations</h3>
            <div class="confirm-grid">
                <strong>Reverse Proxy:</strong>
                <?php if (!empty($summary['proxy_integration']) && is_array($summary['proxy_integration'])): ?>
                    <span><?= e((string) ($summary['proxy_integration']['name'] ?? 'Proxy Integration')) ?> (<?= e((string) ($summary['proxy_provider_label'] ?? 'Provider')) ?>)</span>
                    <?php if (!empty($summary['proxy_integration']['base_url'])): ?>
                        <strong>Base URL:</strong>
                        <span><?= e((string) ($summary['proxy_integration']['base_url'] ?? '')) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($summary['proxy_integration']['identity'])): ?>
                        <strong>Runtime Account:</strong>
                        <span><?= e((string) ($summary['proxy_integration']['identity'] ?? '')) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($summary['proxy_integration']['forward_host'])): ?>
                        <strong>Forward To:</strong>
                        <span><?= e((string) ($summary['proxy_integration']['forward_host'] ?? '')) ?>:<?= e((string) ($summary['proxy_integration']['forward_port'] ?? '')) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span>Not configured</span>
                <?php endif; ?>

                <strong>DNS Provider:</strong>
                <?php if (!empty($summary['dns_integration']) && is_array($summary['dns_integration'])): ?>
                    <span><?= e((string) ($summary['dns_integration']['name'] ?? 'DNS Integration')) ?> (<?= e((string) ($summary['dns_provider_label'] ?? 'Provider')) ?>)</span>
                <?php else: ?>
                    <span>Not configured</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <form class="form" method="post" action="/?route=setup-confirm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

            <div style="display:flex; gap:8px;">
                <a href="/?route=setup-domain" class="btn btn--secondary" style="flex:1; text-align:center; text-decoration:none;">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <button class="btn btn--success" type="submit" style="flex:1;">
                    <i class="fa-solid fa-check"></i> Complete Setup & Login
                </button>
            </div>
        </form>
    </div>
</div>
