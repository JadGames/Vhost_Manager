<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Cloudflare Domains</h1>
        <p class="page-description">Domain-specific zone and API token mappings.</p>
    </div>
</div>

<div class="settings-grid">
    <section class="form-card settings-card">
        <h2 class="settings-title">Add Mapping</h2>
        <p class="settings-subtitle">When a domain matches this suffix, these credentials are used.</p>

        <form class="form" method="post" action="/?route=settings-cloudflare-domains-action" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="add">

            <div class="form-group">
                <label class="form-label" for="domain">Domain Suffix</label>
                <input class="form-input" id="domain" type="text" name="domain" placeholder="example.com" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="zone_id">Zone ID</label>
                <input class="form-input" id="zone_id" type="text" name="zone_id" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="api_token">API Token</label>
                <div class="secret-input-wrap">
                    <input class="form-input" id="api_token" type="password" name="api_token" required autocomplete="off" spellcheck="false">
                    <button class="secret-toggle-btn" type="button" data-secret-target="api_token" aria-controls="api_token" aria-label="Show secret" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="btn-group">
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-plus"></i>
                    Save Mapping
                </button>
            </div>
        </form>
    </section>

    <section class="form-card settings-card">
        <h2 class="settings-title">Configured Mappings</h2>
        <p class="settings-subtitle">Most specific matching suffix is used first.</p>

        <?php if (empty($mappings)): ?>
            <p class="form-hint">No domain mappings configured.</p>
        <?php else: ?>
            <?php foreach ($mappings as $mapping): ?>
                <div class="settings-list-row">
                    <div>
                        <strong><?= e((string) $mapping['domain']) ?></strong>
                        <div class="form-hint">Zone: <?= e((string) $mapping['zone_id']) ?></div>
                    </div>
                    <form method="post" action="/?route=settings-cloudflare-domains-action" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                        <input type="hidden" name="intent" value="delete">
                        <input type="hidden" name="domain" value="<?= e((string) $mapping['domain']) ?>">
                        <button class="btn btn--ghost" type="submit">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
