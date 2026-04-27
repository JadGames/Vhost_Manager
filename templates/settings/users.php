<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Users</h1>
        <p class="page-description">Manage the primary admin account and additional users.</p>
    </div>
</div>

<div class="settings-grid">
    <section class="form-card settings-card">
        <h2 class="settings-title">Primary Admin</h2>
        <p class="settings-subtitle">Change the main admin username and reset password.</p>

        <form class="form" method="post" action="/?route=settings-users-action" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="admin-update">

            <div class="form-group">
                <label class="form-label" for="admin_user">Admin Username</label>
                <input class="form-input" id="admin_user" type="text" name="admin_user" value="<?= e((string) $adminUser) ?>" required>
            </div>

            <div class="btn-group">
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-user-pen"></i>
                    Update Admin Username
                </button>
            </div>
        </form>

        <form class="form" method="post" action="/?route=settings-users-action" autocomplete="off" style="margin-top: 18px;">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="user-reset">
            <input type="hidden" name="target_user" value="<?= e((string) $adminUser) ?>">

            <div class="form-group">
                <label class="form-label" for="admin_reset_password">New Admin Password</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="admin_reset_password" type="password" name="reset_password" required minlength="8" data-password-policy-list="admin-reset-policy">
                    <button class="secret-toggle-btn" type="button" data-secret-target="admin_reset_password" aria-controls="admin_reset_password" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <ul id="admin-reset-policy" class="password-policy" aria-live="polite">
                    <li data-rule="length">8 chars long</li>
                    <li data-rule="uppercase">At least 1 uppercase</li>
                    <li data-rule="special">At least one special char</li>
                </ul>
            </div>
            <div class="form-group">
                <label class="form-label" for="admin_reset_password_confirm">Confirm New Admin Password</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="admin_reset_password_confirm" type="password" name="reset_password_confirm" required minlength="8">
                    <button class="secret-toggle-btn" type="button" data-secret-target="admin_reset_password_confirm" aria-controls="admin_reset_password_confirm" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="btn-group">
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-key"></i>
                    Reset Admin Password
                </button>
            </div>
        </form>
    </section>

    <section class="form-card settings-card">
        <h2 class="settings-title">Additional Users</h2>
        <p class="settings-subtitle">Add users and perform password resets.</p>

        <form class="form" method="post" action="/?route=settings-users-action" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="user-add">

            <div class="form-group">
                <label class="form-label" for="new_user">Username</label>
                <input class="form-input" id="new_user" type="text" name="new_user" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="new_password">Password</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="new_password" type="password" name="new_password" required minlength="8" data-password-policy-list="new-user-policy">
                    <button class="secret-toggle-btn" type="button" data-secret-target="new_password" aria-controls="new_password" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <ul id="new-user-policy" class="password-policy" aria-live="polite">
                    <li data-rule="length">8 chars long</li>
                    <li data-rule="uppercase">At least 1 uppercase</li>
                    <li data-rule="special">At least one special char</li>
                </ul>
            </div>
            <div class="form-group">
                <label class="form-label" for="new_password_confirm">Confirm Password</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="new_password_confirm" type="password" name="new_password_confirm" required minlength="8">
                    <button class="secret-toggle-btn" type="button" data-secret-target="new_password_confirm" aria-controls="new_password_confirm" aria-label="Show password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="btn-group">
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-user-plus"></i>
                    Add User
                </button>
            </div>
        </form>

        <?php if (!empty($users)): ?>
            <div style="margin-top: 18px;">
                <?php foreach ($users as $username => $hash): ?>
                    <?php
                        $resetFieldId = 'user_reset_password_' . substr(md5((string) $username), 0, 8);
                        $resetConfirmId = 'user_reset_password_confirm_' . substr(md5((string) $username), 0, 8);
                        $resetPolicyId = 'user-reset-policy-' . substr(md5((string) $username), 0, 8);
                    ?>
                    <div class="settings-list-row">
                        <div>
                            <strong><?= e((string) $username) ?></strong>
                            <div class="form-hint">Additional user</div>
                        </div>

                        <div class="btn-group">
                            <form method="post" action="/?route=settings-users-action" autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                                <input type="hidden" name="intent" value="user-delete">
                                <input type="hidden" name="target_user" value="<?= e((string) $username) ?>">
                                <button class="btn btn--ghost" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>

                    <form class="form" method="post" action="/?route=settings-users-action" autocomplete="off" style="margin-top: 10px; margin-bottom: 18px;">
                        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                        <input type="hidden" name="intent" value="user-reset">
                        <input type="hidden" name="target_user" value="<?= e((string) $username) ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Reset Password</label>
                                <div class="secret-input-wrap">
                                    <input class="form-input" id="<?= e($resetFieldId) ?>" type="password" name="reset_password" required minlength="8" data-password-policy-list="<?= e($resetPolicyId) ?>">
                                    <button class="secret-toggle-btn" type="button" data-secret-target="<?= e($resetFieldId) ?>" aria-controls="<?= e($resetFieldId) ?>" aria-label="Show password" aria-pressed="false">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                                <ul id="<?= e($resetPolicyId) ?>" class="password-policy" aria-live="polite">
                                    <li data-rule="length">8 chars long</li>
                                    <li data-rule="uppercase">At least 1 uppercase</li>
                                    <li data-rule="special">At least one special char</li>
                                </ul>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm</label>
                                <div class="secret-input-wrap">
                                    <input class="form-input" id="<?= e($resetConfirmId) ?>" type="password" name="reset_password_confirm" required minlength="8">
                                    <button class="secret-toggle-btn" type="button" data-secret-target="<?= e($resetConfirmId) ?>" aria-controls="<?= e($resetConfirmId) ?>" aria-label="Show password" aria-pressed="false">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn--primary" type="submit">Reset Password</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="form-hint" style="margin-top: 14px;">No additional users configured.</p>
        <?php endif; ?>
    </section>
</div>
