<?php declare(strict_types=1); ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-server"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">Apache virtual host management</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-subtitle">Sign in with your administrator credentials.</p>

        <form class="form" method="post" action="/?route=login" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input class="form-input" id="username" type="text" name="username" required maxlength="64" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="password" type="password" name="password" required>
                    <button class="secret-toggle-btn" type="button" data-secret-target="password" aria-controls="password" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div style="margin-top: 4px;">
                <button class="btn btn--primary btn--full" type="submit">
                    Sign in
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

