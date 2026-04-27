<?php declare(strict_types=1); ?>

<?php
/**
 * @param string $iso
 */
$formatDate = static function (string $iso): string {
    $iso = trim($iso);
    if ($iso === '') {
        return 'N/A';
    }

    try {
        $dt = new DateTimeImmutable($iso);
        return $dt->format('d/m/y - H:i');
    } catch (Throwable) {
        return 'N/A';
    }
};
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Users</h1>
        <p class="page-description">Manage account access, roles, and status.</p>
    </div>
    <button class="btn btn--primary" type="button" id="open-create-user-modal">
        <i class="fa-solid fa-user-plus"></i>
        Create User
    </button>
</div>

<div class="settings-tiles-stack">
    <?php foreach (($userRecords ?? []) as $email => $record): ?>
        <?php
            $email = (string) $email;
            $record = is_array($record) ? $record : [];
            $isPrimary = !empty($record['is_primary']);
            $isActive = !empty($record['active']);
            $isOnline = !empty($record['is_online']);
            $accountType = (string) ($record['account_type'] ?? 'user');
            $fullName = (string) ($record['full_name'] ?? $email);
            $createdAt = $formatDate((string) ($record['created_at'] ?? ''));
            $lastLoginAt = $isOnline ? 'Online now' : $formatDate((string) ($record['last_login_at'] ?? ''));

            $idSuffix = substr(md5($email), 0, 8);
            $resetFieldId = 'user_reset_password_' . $idSuffix;
            $resetConfirmId = 'user_reset_password_confirm_' . $idSuffix;
            $resetPolicyId = 'user_reset_policy_' . $idSuffix;
            $editModalId = 'edit-user-modal-' . $idSuffix;
        ?>
        <div class="settings-tile">
            <div class="settings-tile__header">
                <div class="settings-tile__header-left">
                    <div class="settings-tile__icon"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <div class="settings-tile__title"><?= e($fullName) ?></div>
                        <div class="settings-tile__subtitle"><?= e($email) ?></div>
                    </div>
                </div>
                <div class="settings-tile__header-actions">
                    <span class="settings-tile__tag <?= $isActive ? 'is-active' : '' ?>">
                        <span class="tag-dot"></span>
                        <?= $isActive ? 'Enabled' : 'Disabled' ?>
                    </span>
                    <span class="settings-tile__tag"><?= e(strtoupper($accountType)) ?></span>
                    <?php if ($isPrimary): ?>
                        <span class="settings-tile__tag is-active">Primary Admin</span>
                    <?php endif; ?>
                    <button class="btn btn--ghost btn--sm" type="button" data-open-user-modal="<?= e($editModalId) ?>">
                        <i class="fa-solid fa-pen"></i>
                        Edit
                    </button>
                </div>
            </div>

            <div class="settings-tile__body">
                <div class="users-meta-grid">
                    <div class="users-meta-item">
                        <span class="users-meta-label">Role</span>
                        <span class="users-meta-value"><?= e(ucfirst($accountType)) ?></span>
                    </div>
                    <div class="users-meta-item">
                        <span class="users-meta-label">Current Primary Admin</span>
                        <span class="users-meta-value"><?= e((string) ($adminUser ?? 'admin@example.com')) ?></span>
                    </div>
                    <div class="users-meta-item">
                        <span class="users-meta-label">Status</span>
                        <span class="users-meta-value"><?= $isActive ? 'Enabled' : 'Disabled' ?></span>
                    </div>
                    <div class="users-meta-item">
                        <span class="users-meta-label">Created</span>
                        <span class="users-meta-value"><?= e($createdAt) ?></span>
                    </div>
                    <div class="users-meta-item users-meta-item--wide">
                        <span class="users-meta-label">Last Login</span>
                        <span class="users-meta-value"><?= e($lastLoginAt) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <dialog id="<?= e($editModalId) ?>" aria-modal="true" aria-labelledby="<?= e($editModalId) ?>-title">
            <div class="dialog-header">
                <div class="dialog-header-text">
                    <p class="dialog-title" id="<?= e($editModalId) ?>-title">Edit User</p>
                    <p class="dialog-subtitle"><?= e($fullName) ?> · <?= e($email) ?></p>
                </div>
                <button class="dialog-close-btn" type="button" data-close-user-modal="<?= e($editModalId) ?>" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="dialog-body">
                <?php if ($isPrimary): ?>
                    <form class="form" method="post" action="/?route=settings-users-action" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                        <input type="hidden" name="intent" value="admin-update">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="admin_full_name_<?= e($idSuffix) ?>">Full Name</label>
                                <input class="form-input" id="admin_full_name_<?= e($idSuffix) ?>" type="text" name="admin_full_name" value="<?= e($fullName) ?>" maxlength="120" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="admin_user_<?= e($idSuffix) ?>">Email</label>
                                <input class="form-input" id="admin_user_<?= e($idSuffix) ?>" type="email" name="admin_user" value="<?= e($email) ?>" required>
                            </div>
                        </div>

                        <div class="btn-group" style="margin-top: 6px;">
                            <button class="btn btn--primary" type="submit">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save Profile
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <form class="form" method="post" action="/?route=settings-users-action" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                        <input type="hidden" name="intent" value="user-update">
                        <input type="hidden" name="target_user" value="<?= e($email) ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="new_user_full_name_<?= e($idSuffix) ?>">Full Name</label>
                                <input class="form-input" id="new_user_full_name_<?= e($idSuffix) ?>" type="text" name="new_user_full_name" value="<?= e($fullName) ?>" maxlength="120" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="new_user_email_<?= e($idSuffix) ?>">Email</label>
                                <input class="form-input" id="new_user_email_<?= e($idSuffix) ?>" type="email" name="new_user_email" value="<?= e($email) ?>" required>
                            </div>
                        </div>

                        <div class="btn-group" style="margin-top: 6px;">
                            <button class="btn btn--primary" type="submit">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save Profile
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <form class="form" method="post" action="/?route=settings-users-action" autocomplete="off" style="margin-top: 14px;">
                    <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                    <input type="hidden" name="intent" value="user-reset">
                    <input type="hidden" name="target_user" value="<?= e($email) ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="<?= e($resetFieldId) ?>">Change Password</label>
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
                            <label class="form-label" for="<?= e($resetConfirmId) ?>">Confirm Password</label>
                            <div class="secret-input-wrap">
                                <input class="form-input" id="<?= e($resetConfirmId) ?>" type="password" name="reset_password_confirm" required minlength="8">
                                <button class="secret-toggle-btn" type="button" data-secret-target="<?= e($resetConfirmId) ?>" aria-controls="<?= e($resetConfirmId) ?>" aria-label="Show password" aria-pressed="false">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top: 6px;">
                        <button class="btn btn--primary" type="submit">
                            <i class="fa-solid fa-key"></i>
                            Save New Password
                        </button>
                    </div>
                </form>

                <?php if (!$isPrimary): ?>
                    <div class="dialog-footer" style="margin-top: 18px;">
                        <form method="post" action="/?route=settings-users-action" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                            <input type="hidden" name="intent" value="user-toggle-role">
                            <input type="hidden" name="target_user" value="<?= e($email) ?>">
                            <button class="btn btn--ghost" type="submit">
                                <i class="fa-solid fa-user-shield"></i>
                                Toggle Role
                            </button>
                        </form>

                        <form method="post" action="/?route=settings-users-action" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                            <input type="hidden" name="intent" value="user-toggle-status">
                            <input type="hidden" name="target_user" value="<?= e($email) ?>">
                            <button class="btn <?= $isActive ? 'btn--ghost' : 'btn--primary' ?>" type="submit">
                                <i class="fa-solid <?= $isActive ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                <?= $isActive ? 'Disable Account' : 'Enable Account' ?>
                            </button>
                        </form>

                        <form method="post" action="/?route=settings-users-action" autocomplete="off" data-confirm-message="Delete this user? This cannot be undone.">
                            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                            <input type="hidden" name="intent" value="user-delete">
                            <input type="hidden" name="target_user" value="<?= e($email) ?>">
                            <button class="btn btn--danger" type="submit">
                                <i class="fa-solid fa-trash"></i>
                                Delete User
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="dialog-footer" style="margin-top: 18px; justify-content: flex-start;">
                        <span class="settings-tile__tag is-active">Primary admin account cannot be disabled or deleted.</span>
                    </div>
                <?php endif; ?>

                <div class="dialog-footer">
                    <button class="btn btn--ghost" type="button" data-close-user-modal="<?= e($editModalId) ?>">Close</button>
                </div>
            </div>
        </dialog>
    <?php endforeach; ?>
</div>

<dialog id="create-user-modal" aria-modal="true" aria-labelledby="create-user-modal-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="create-user-modal-title">Create User</p>
            <p class="dialog-subtitle">Create a new account with a role and secure password.</p>
        </div>
        <button class="dialog-close-btn" type="button" data-close-user-modal="create-user-modal" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <form class="form" method="post" action="/?route=settings-users-action" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="user-add">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="create_new_user_full_name">Full Name</label>
                    <input class="form-input" id="create_new_user_full_name" type="text" name="new_user_full_name" maxlength="120" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="create_new_user">Email</label>
                    <input class="form-input" id="create_new_user" type="email" name="new_user" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="create_new_user_role">Account Type</label>
                    <select class="form-select" id="create_new_user_role" name="new_user_role">
                        <option value="user" selected>User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="create_new_password">Password</label>
                    <div class="secret-input-wrap">
                        <input class="form-input" id="create_new_password" type="password" name="new_password" required minlength="8" data-password-policy-list="create-user-policy">
                        <button class="secret-toggle-btn" type="button" data-secret-target="create_new_password" aria-controls="create_new_password" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <ul id="create-user-policy" class="password-policy" aria-live="polite">
                        <li data-rule="length">8 chars long</li>
                        <li data-rule="uppercase">At least 1 uppercase</li>
                        <li data-rule="special">At least one special char</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label class="form-label" for="create_new_password_confirm">Confirm Password</label>
                    <div class="secret-input-wrap">
                        <input class="form-input" id="create_new_password_confirm" type="password" name="new_password_confirm" required minlength="8">
                        <button class="secret-toggle-btn" type="button" data-secret-target="create_new_password_confirm" aria-controls="create_new_password_confirm" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="dialog-footer">
                <button class="btn btn--ghost" type="button" data-close-user-modal="create-user-modal">Cancel</button>
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-user-plus"></i>
                    Create User
                </button>
            </div>
        </form>
    </div>
</dialog>

<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
(function () {
    function openDialog(dialogEl) {
        if (!dialogEl) {
            return;
        }

        if (typeof dialogEl.showModal === 'function') {
            dialogEl.showModal();
            return;
        }

        dialogEl.setAttribute('open', 'open');
    }

    function closeDialog(dialogEl) {
        if (!dialogEl) {
            return;
        }

        if (typeof dialogEl.close === 'function') {
            dialogEl.close();
            return;
        }

        dialogEl.removeAttribute('open');
    }

    var createBtn = document.getElementById('open-create-user-modal');
    var createModal = document.getElementById('create-user-modal');

    if (createBtn && createModal) {
        createBtn.addEventListener('click', function () {
            openDialog(createModal);
        });
    }

    document.addEventListener('click', function (event) {
        var openBtn = event.target.closest('[data-open-user-modal]');
        if (openBtn) {
            var targetId = openBtn.getAttribute('data-open-user-modal');
            if (!targetId) {
                return;
            }

            var dialog = document.getElementById(targetId);
            openDialog(dialog);
            return;
        }

        var closeBtn = event.target.closest('[data-close-user-modal]');
        if (closeBtn) {
            var closeId = closeBtn.getAttribute('data-close-user-modal');
            if (!closeId) {
                return;
            }

            closeDialog(document.getElementById(closeId));
        }
    });

    document.querySelectorAll('dialog').forEach(function (dialogEl) {
        dialogEl.addEventListener('click', function (event) {
            if (event.target === dialogEl) {
                closeDialog(dialogEl);
            }
        });
    });

    document.querySelectorAll('form[data-confirm-message]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm-message') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
}());
</script>
