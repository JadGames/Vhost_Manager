<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Overview</h1>
        <p class="page-description">System status and quick access to key areas.</p>
    </div>
</div>

<div class="overview-stats-grid">
    <div class="overview-stat-card">
        <div class="overview-stat-icon">
            <i class="fa-solid fa-server"></i>
        </div>
        <div>
            <div class="overview-stat-value"><?= e((string) $vhostCount) ?></div>
            <div class="overview-stat-label">Virtual Hosts</div>
        </div>
    </div>
    <div class="overview-stat-card">
        <div class="overview-stat-icon is-info">
            <i class="fa-solid fa-puzzle-piece"></i>
        </div>
        <div>
            <div class="overview-stat-value"><?= e((string) $integrationCount) ?></div>
            <div class="overview-stat-label">Integrations</div>
        </div>
    </div>
    <div class="overview-stat-card">
        <div class="overview-stat-icon is-warning">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <div class="overview-stat-value"><?= e((string) ($userCount + 1)) ?></div>
            <div class="overview-stat-label">Users</div>
        </div>
    </div>
    <div class="overview-stat-card">
        <div class="overview-stat-icon">
            <i class="fa-solid fa-plug-circle-bolt"></i>
        </div>
        <div>
            <div class="overview-stat-value"><?= e((string) $activeModuleCount) ?></div>
            <div class="overview-stat-label">Active Apache Modules</div>
        </div>
    </div>
</div>

<div class="settings-tiles-stack">
    <div class="settings-tile">
        <div class="settings-tile__header">
            <div class="settings-tile__header-left">
                <div class="settings-tile__icon"><i class="fa-solid fa-server"></i></div>
                <div>
                    <div class="settings-tile__title">Virtual Hosts</div>
                    <div class="settings-tile__subtitle"><?= e((string) $vhostCount) ?> configured</div>
                </div>
            </div>
            <div class="settings-tile__header-actions">
                <a href="/?route=vhosts" class="btn btn--ghost btn--sm">View All</a>
                <a href="/?route=create-vhost" class="btn btn--primary btn--sm">
                    <i class="fa-solid fa-plus"></i> New VHost
                </a>
            </div>
        </div>
        <?php if (!empty($recentVhosts)): ?>
            <div class="settings-tile__body">
                <div class="settings-tile__list">
                    <?php foreach (array_slice($recentVhosts, 0, 8) as $vh): ?>
                        <span class="settings-tile__tag">
                            <span class="tag-dot"></span>
                            <?= e((string) ($vh['domain'] ?? $vh)) ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($recentVhosts) > 8): ?>
                        <span class="settings-tile__tag" style="color: var(--text-4);">+<?= e((string) (count($recentVhosts) - 8)) ?> more</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
