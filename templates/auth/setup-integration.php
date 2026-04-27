<?php declare(strict_types=1); ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">First-time setup wizard</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Setup: Integrations</h1>
        <p class="auth-subtitle">Step 2 of 3: Configure your reverse proxy (optional)</p>

        <form class="form" method="post" action="/?route=setup-integration" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="proxy_mode">Reverse Proxy Mode</label>
                <select class="form-input" id="proxy_mode" name="proxy_mode">
                    <?php if (!empty($hasBuiltinNpm)): ?>
                        <option value="builtin_npm" <?= ($proxyMode === 'builtin_npm') ? 'selected' : '' ?>>Built-in NPM</option>
                    <?php endif; ?>
                    <option value="external_npm" <?= ($proxyMode === 'external_npm') ? 'selected' : '' ?>>External NPM</option>
                    <option value="disabled" <?= ($proxyMode === 'disabled') ? 'selected' : '' ?>>Configure later</option>
                </select>
                <small id="proxy_mode_help">
                    <?php if ($proxyMode === 'external_npm'): ?>
                        Connect to an existing Nginx Proxy Manager running on another server.
                    <?php elseif ($proxyMode === 'disabled'): ?>
                        Skip proxy setup for now. You can configure it later in settings.
                    <?php else: ?>
                        Pre-configured Nginx Proxy Manager is included and will manage ports 80 and 443.
                    <?php endif; ?>
                </small>
            </div>

            <?php if (!empty($hasBuiltinNpm)): ?>
            <div id="builtin_npm_section" class="integration-panel" <?= ($proxyMode === 'builtin_npm') ? '' : 'hidden' ?>>
                <h3 style="margin:0 0 12px 0; font-size:1em;">Built-in NPM Setup</h3>
                <p style="margin:0 0 12px 0; font-size:0.9em;">Set the admin credentials for the built-in Nginx Proxy Manager. You can change these later in settings.</p>

                <div class="form-group">
                    <label class="form-label" for="builtin_npm_identity">Admin Email</label>
                    <input class="form-input" id="builtin_npm_identity" type="email" name="builtin_npm_identity" value="<?= e((string) ($_SESSION['setup_pending_builtin_npm_identity'] ?? 'admin@example.com')) ?>" placeholder="admin@example.com">
                    <small id="builtin_npm_identity_error" class="form-field-error" hidden></small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="builtin_npm_secret">Admin Password</label>
                    <div class="secret-input-wrap">
                        <input class="form-input" id="builtin_npm_secret" type="password" name="builtin_npm_secret" placeholder="Choose a strong password">
                        <button
                            type="button"
                            class="secret-toggle-btn"
                            data-secret-target="builtin_npm_secret"
                               aria-label="Show password"
                            aria-pressed="false"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <small>Password must be at least 8 characters. Name is auto-set to APHost Admin for built-in setup.</small>
                </div>
            </div>
            <?php endif; ?>

            <div id="external_npm_section" class="integration-panel" <?= ($proxyMode === 'external_npm') ? '' : 'hidden' ?>>
                <h3 style="margin:0 0 12px 0; font-size:1em;">External NPM Configuration</h3>
                <p style="margin:0 0 10px 0; font-size:0.9em;">Install docs: <a href="https://nginxproxymanager.com/setup/" target="_blank" rel="noreferrer noopener"><span class="link-accent">NPM Setup Guide</span></a></p>

                <div class="form-group">
                    <label class="form-label" for="npm_base_url_scheme">NPM Admin Panel URL</label>
                    <div class="app-url-input-row">
                        <select class="form-select" id="npm_base_url_scheme" name="npm_base_url_scheme" aria-label="NPM URL protocol">
                            <option value="http">http://</option>
                            <option value="https">https://</option>
                        </select>
                        <input class="form-input" id="npm_base_url_host" type="text" name="npm_base_url_host" placeholder="your-npm-host or 192.168.1.100">
                        <input class="form-input" id="npm_base_url_port" type="number" name="npm_base_url_port" placeholder="81" min="1" max="65535" style="width: 80px; flex: 0 0 80px;">
                    </div>
                    <small>Admin panel URL (usually port 81).</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="npm_identity">NPM Username or Email</label>
                    <input class="form-input" id="npm_identity" type="text" name="npm_identity" value="<?= e((string) $npmIdentity) ?>" placeholder="admin@example.com">
                    <small>Use the same login identity you use for the NPM web UI.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="npm_secret">NPM Password</label>
                    <div class="secret-input-wrap">
                        <input class="form-input" id="npm_secret" type="password" name="npm_secret" value="<?= e((string) $npmSecret) ?>" placeholder="Your NPM password">
                        <button
                            type="button"
                            class="secret-toggle-btn"
                            data-secret-target="npm_secret"
                               aria-label="Show password"
                            aria-pressed="false"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="npm_forward_host">Forward Address</label>
                    <input class="form-input" id="npm_forward_host" type="text" name="npm_forward_host" value="<?= e((string) $npmForwardHost) ?>" placeholder="aphost">
                    <small>URL, IP, or DNS name where APHost is reachable from NPM.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="npm_forward_port">Forward Port</label>
                    <input class="form-input" id="npm_forward_port" type="number" name="npm_forward_port" value="<?= e((string) $npmForwardPort) ?>" min="1" max="65535" placeholder="80">
                    <small>Internal port where APHost listens on the forward host.</small>
                </div>
            </div>

            <div style="margin-top:20px; display:flex; gap:8px;">
                <a href="/?route=setup" class="btn btn--secondary" style="flex:1; text-align:center; text-decoration:none;">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <button id="integration-submit-btn" class="btn btn--primary" type="submit" style="flex:1;">
                    <span class="btn-text"><?= ($proxyMode === 'disabled') ? 'Continue' : 'Test &amp; Continue' ?></span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($externalNpmTestError)): ?>
<dialog id="setupNpmTestFailedModal" data-auto-open="true" aria-labelledby="setupNpmTestFailedTitle">
    <div class="dialog-title" id="setupNpmTestFailedTitle">Failed to Connect</div>
    <div class="dialog-subtitle">External NPM test did not pass. Verify your URL and credentials.</div>
    <div class="dialog-rows">
        <div class="dialog-row">
            <span class="dialog-row-label">Error</span>
            <span class="dialog-row-value"><?= e((string) $externalNpmTestError) ?></span>
        </div>
    </div>
    <div class="btn-group" style="justify-content:flex-end;">
        <form method="dialog">
            <button class="btn btn--primary" type="submit">OK</button>
        </form>
    </div>
</dialog>
<?php endif; ?>
