<?php declare(strict_types=1); ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-network-wired"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">External proxy setup</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Configure NPM</h1>
        <p class="auth-subtitle">Provide the Nginx Proxy Manager API details so APHost can manage proxy hosts for you.</p>

        <form class="form" method="post" action="/?route=setup-proxy" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="npm_base_url">NPM Base URL</label>
                <input class="form-input" id="npm_base_url" type="url" name="npm_base_url" value="<?= e((string) $npmBaseUrl) ?>" required>
                <small>Usually the NPM admin UI URL, for example `http://your-server:81`.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="npm_identity">NPM Username / Email</label>
                <input class="form-input" id="npm_identity" type="text" name="npm_identity" value="<?= e((string) $npmIdentity) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="npm_secret">NPM Password</label>
                <input class="form-input" id="npm_secret" type="password" name="npm_secret" value="<?= e((string) $npmSecret) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="npm_forward_host">Forward Host</label>
                <input class="form-input" id="npm_forward_host" type="text" name="npm_forward_host" value="<?= e((string) $npmForwardHost) ?>" required>
                <small>Where NPM should forward app-managed sites. Use a container/service name if on the same Docker network, otherwise a reachable hostname or IP.</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="npm_forward_port">Forward Port</label>
                <input class="form-input" id="npm_forward_port" type="number" name="npm_forward_port" value="<?= e((string) $npmForwardPort) ?>" min="1" max="65535" required>
            </div>

            <div class="form-group">
                <label class="form-label">Documentation</label>
                <div>
                    <a href="https://nginxproxymanager.com/guide/" target="_blank" rel="noopener noreferrer">NPM installation guide</a>
                </div>
                <div>
                    <a href="https://hub.docker.com/r/jc21/nginx-proxy-manager" target="_blank" rel="noopener noreferrer">NPM Docker image</a>
                </div>
                <small>After installing NPM, open its admin UI, create or reset your admin account, then use that URL and login here.</small>
            </div>

            <div style="margin-top: 4px;">
                <button class="btn btn--primary btn--full" type="submit">
                    Complete Setup
                    <i class="fa-solid fa-check"></i>
                </button>
            </div>
        </form>
    </div>
</div>
