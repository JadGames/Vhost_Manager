<?php declare(strict_types=1); ?>
<?php
$allowedDocrootBases = is_array($allowedDocrootBases ?? null) ? $allowedDocrootBases : [];

$normalizePath = static function (string $path): string {
    $path = preg_replace('#/+#', '/', trim($path)) ?? '';
    if ($path === '') {
        return '';
    }

    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    return $path === '' ? '/' : $path;
};

$groupSuffix = static function (string $domain): string {
    $parts = array_values(array_filter(explode('.', strtolower($domain)), static fn (string $part): bool => $part !== ''));
    if (count($parts) <= 2) {
        return implode('.', $parts);
    }

    return implode('.', array_slice($parts, 1));
};

$suffixDepth = static function (string $suffix): int {
    $parts = array_values(array_filter(explode('.', strtolower($suffix)), static fn (string $part): bool => $part !== ''));
    return max(2, count($parts));
};

$pathWithinBase = static function (string $path, string $base): bool {
    return $path === $base || str_starts_with($path, $base . '/');
};

$normalizedBases = array_values(array_filter(array_map($normalizePath, array_map('strval', $allowedDocrootBases))));
if ($normalizedBases === []) {
    $normalizedBases = ['/var/www'];
}
usort($normalizedBases, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

$grouped = [];
foreach ($vhosts as $domain => $entry) {
    $domainStr = (string) $domain;
    $docroot = $normalizePath((string) ($entry['docroot'] ?? ''));
    if ($docroot === '') {
        continue;
    }

    $bucketBase = null;
    foreach ($normalizedBases as $base) {
        if ($pathWithinBase($docroot, $base)) {
            $bucketBase = $base;
            break;
        }
    }

    if ($bucketBase === null) {
        continue;
    }

    $suffix = $groupSuffix($domainStr);

    if (!isset($grouped[$bucketBase])) {
        $grouped[$bucketBase] = [];
    }
    if (!isset($grouped[$bucketBase][$suffix])) {
        $grouped[$bucketBase][$suffix] = [];
    }

    $grouped[$bucketBase][$suffix][] = [$domainStr, $entry];
}

uksort($grouped, static fn (string $a, string $b): int => strnatcasecmp($a, $b));
foreach ($grouped as &$bySuffix) {
    uksort(
        $bySuffix,
        static function (string $a, string $b) use ($suffixDepth): int {
            $depthCmp = $suffixDepth($a) <=> $suffixDepth($b);
            if ($depthCmp !== 0) {
                return $depthCmp;
            }

            return strnatcasecmp($a, $b);
        }
    );

    foreach ($bySuffix as &$entries) {
        usort(
            $entries,
            static fn (array $left, array $right): int => strnatcasecmp((string) $left[0], (string) $right[0])
        );
    }
    unset($entries);
}
unset($bySuffix);
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Virtual Hosts</h1>
        <p class="page-description">Apache virtual hosts managed through this application.</p>
    </div>
</div>

<?php if (empty($grouped)): ?>

    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-server"></i></div>
        <div class="empty-state-title">No virtual hosts yet</div>
        <div class="empty-state-text">Get started by creating your first virtual host.</div>
        <a href="/?route=create-vhost" class="btn btn--primary">
            <i class="fa-solid fa-plus"></i>
            Create your first VHost
        </a>
    </div>

<?php else: ?>

    <div class="dashboard-sections">
        <?php foreach ($grouped as $docrootBase => $bySuffix): ?>
            <section class="domain-section">
                <h2 class="domain-header">
                    <i class="fa-solid fa-folder-tree"></i>
                    <span><?= e($docrootBase) ?></span>
                </h2>

                <?php foreach ($bySuffix as $suffix => $entries): ?>
                    <?php $headingLevel = min(6, $suffixDepth($suffix)); ?>
                    <div class="docroot-group">
                        <div class="docroot-header docroot-header--fqdn docroot-header--level-<?= $headingLevel ?>">
                            <i class="fa-solid fa-globe"></i>
                            <?= e($suffix) ?>
                        </div>

                        <div class="vhost-list">
                            <?php foreach ($entries as [$domainStr, $entry]): ?>
                                <?php
                                    $alias = trim((string) ($entry['alias'] ?? ''));
                                    $displayName = $alias !== '' ? $alias : (string) $domainStr;
                                    $createdRaw = (string) ($entry['created_at'] ?? '');
                                    $ts = strtotime($createdRaw);
                                    $createdFmt = $ts !== false ? date('d/m/y G:i', $ts) : $createdRaw;
                                    $hasCf = !empty($entry['cf_record_id']);
                                    $hasNpm = !empty($entry['npm_proxy_id']);
                                    $hasSsl = !empty($entry['npm_ssl_enabled']);
                                    $externalScheme = $hasSsl ? 'https' : 'http';
                                ?>
                                <article class="vhost-tile">
                                    <div class="tile-header">
                                        <div class="tile-title-group">
                                            <h3 class="tile-url" title="<?= e($displayName) ?>"><?= e($displayName) ?></h3>
                                            <?php if ($alias !== ''): ?>
                                                <div class="tile-domain" title="<?= e((string) $domainStr) ?>"><?= e((string) $domainStr) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="tile-actions">
                                            <a href="/?route=edit-vhost&amp;domain=<?= urlencode((string) $domainStr) ?>"
                                               class="action-btn"
                                               title="Edit"
                                               aria-label="Edit <?= e((string) $domainStr) ?>">
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>
                                            <a href="/?route=delete-vhost&amp;domain=<?= urlencode((string) $domainStr) ?>"
                                               class="action-btn action-btn--danger"
                                               title="Delete"
                                               aria-label="Delete <?= e((string) $domainStr) ?>">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                            <a href="<?= e($externalScheme) ?>://<?= e((string) $domainStr) ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="action-btn"
                                               title="Open in browser"
                                               aria-label="Open <?= e((string) $domainStr) ?> in browser">
                                                <i class="fa-solid fa-external-link-alt"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="tile-meta">
                                        <div class="meta-col">
                                            <div class="meta-label">Integrations</div>
                                            <div class="meta-value meta-value--plain">
                                                <div class="badge-row">
                                                    <?php if ($hasCf): ?>
                                                        <span class="badge badge--cf" title="Cloudflare DNS record linked">
                                                            <i class="fa-solid fa-cloud"></i>
                                                            <span>CF</span>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($hasNpm): ?>
                                                        <span class="badge badge--npm" title="Nginx Proxy Manager host linked">
                                                            <i class="fa-solid fa-network-wired"></i>
                                                            <span>NPM</span>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($hasSsl): ?>
                                                        <span class="badge badge--ssl" title="NPM SSL enabled">
                                                            <i class="fa-solid fa-lock"></i>
                                                            <span>SSL</span>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!$hasCf && !$hasNpm): ?>
                                                        <span class="badge badge--apache" title="Apache only">
                                                            <i class="fa-solid fa-feather-pointed"></i>
                                                            <span>Apache</span>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tile-footer">
                                        <time class="tile-created" datetime="<?= e($createdRaw) ?>"><?= e($createdFmt) ?></time>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
