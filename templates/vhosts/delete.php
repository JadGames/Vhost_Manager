<?php declare(strict_types=1); ?>
<?php $domain = (string) ($entry['domain'] ?? ''); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Delete Virtual Host</h1>
        <p class="page-description">This action cannot be undone.</p>
    </div>
</div>

<div class="danger-zone">
    <div class="danger-zone-title">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Confirm deletion
    </div>
    <p class="danger-zone-text">
        You are about to permanently remove <strong><?= e($domain) ?></strong>.<br>
        This will disable the Apache site and delete its configuration file.
    </p>

    <form class="form" method="post" action="/?route=delete-vhost" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
        <input type="hidden" name="domain" value="<?= e($domain) ?>">

        <label class="form-check">
            <input type="checkbox" name="delete_root" value="1">
            Also delete document root: <code><?= e((string) ($entry['docroot'] ?? '')) ?></code>
        </label>

        <div class="btn-group" style="margin-top: 8px;">
            <button class="btn btn--danger" type="submit">
                <i class="fa-solid fa-trash"></i>
                Delete Virtual Host
            </button>
            <a href="/?route=dashboard" class="btn btn--ghost">Cancel</a>
        </div>
    </form>
</div>
