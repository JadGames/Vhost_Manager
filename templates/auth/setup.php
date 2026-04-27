<?php declare(strict_types=1); ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">First-time setup wizard</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Setup: Admin Account</h1>
        <p class="auth-subtitle">Step 1 of 3: Create your first admin account</p>

        <form class="form" method="post" action="/?route=setup" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <?php $requirePassword = empty($hasPendingPassword); ?>
            <?php $fe = $fieldErrors ?? []; ?>

            <div class="form-group">
                <label class="form-label" for="admin_email">Admin Email</label>
                <input class="form-input<?= !empty($fe['admin_email']) ? ' is-error' : '' ?>" id="admin_email" type="email" name="admin_email" value="<?= e((string) ($setupAdminEmail ?? '')) ?>" maxlength="254" autofocus placeholder="admin@example.com">
                <?php if (!empty($fe['admin_email'])): ?><span class="form-field-error"><?= e((string) $fe['admin_email']) ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Admin Password</label>
                <div class="secret-input-wrap">
                    <input class="form-input<?= !empty($fe['password']) ? ' is-error' : '' ?>" id="password" type="password" name="password" placeholder="At least 8 characters" data-password-policy-list="setup-password-policy">
                    <button class="secret-toggle-btn" type="button" data-secret-target="password" aria-controls="password" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <?php if (!$requirePassword): ?>
                    <span class="form-hint form-hint--warning">Leave blank to keep the password you already set.</span>
                <?php endif; ?>
                <?php if (!empty($fe['password'])): ?><span class="form-field-error"><?= e((string) $fe['password']) ?></span><?php endif; ?>
                <ul id="setup-password-policy" class="password-policy" aria-live="polite">
                    <li data-rule="length">8 chars long</li>
                    <li data-rule="uppercase">At least 1 uppercase</li>
                    <li data-rule="special">At least one special char</li>
                </ul>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <div class="secret-input-wrap">
                    <input class="form-input<?= !empty($fe['confirm_password']) ? ' is-error' : '' ?>" id="confirm_password" type="password" name="confirm_password" placeholder="Re-enter your password">
                    <button class="secret-toggle-btn" type="button" data-secret-target="confirm_password" aria-controls="confirm_password" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <?php if (!empty($fe['confirm_password'])): ?><span class="form-field-error"><?= e((string) $fe['confirm_password']) ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="app_url_hostpath">App URL</label>
                <div class="app-url-input-row">
                    <select class="form-select" id="app_url_scheme" name="app_url_scheme" aria-label="App URL protocol">
                        <option value="http" <?= (($appUrlScheme ?? 'http') === 'http') ? 'selected' : '' ?>>http://</option>
                        <option value="https" <?= (($appUrlScheme ?? 'http') === 'https') ? 'selected' : '' ?>>https://</option>
                    </select>
                    <input class="form-input<?= !empty($fe['app_url']) ? ' is-error' : '' ?>" id="app_url_hostpath" type="text" name="app_url_hostpath" value="<?= e((string) ($appUrlHostPath ?? 'localhost:8080')) ?>" placeholder="localhost:8080">
                </div>
                <?php if (!empty($fe['app_url'])): ?><span class="form-field-error"><?= e((string) $fe['app_url']) ?></span><?php else: ?><span class="form-hint">URL users will visit for Vhost Manager.</span><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="default_docroot_base">Default Docroot Base</label>
                <select class="form-select<?= !empty($fe['default_docroot_base']) ? ' is-error' : '' ?>" id="default_docroot_base" name="default_docroot_base">
                    <?php foreach (($allowedDocrootBases ?? []) as $base): ?>
                        <?php $base = (string) $base; ?>
                        <option value="<?= e($base) ?>" <?= $base === (string) $defaultDocrootBase ? 'selected' : '' ?>>
                            <?= e($base) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($fe['default_docroot_base'])): ?><span class="form-field-error"><?= e((string) $fe['default_docroot_base']) ?></span><?php endif; ?>
            </div>

            <?php foreach (($allowedDocrootBases ?? []) as $base): ?>
                <input type="hidden" name="allowed_docroot_bases[]" value="<?= e((string) $base) ?>">
            <?php endforeach; ?>

            <div style="margin-top: 4px;">
                <button class="btn btn--primary btn--full" type="submit">
                    Continue to Integrations
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>
