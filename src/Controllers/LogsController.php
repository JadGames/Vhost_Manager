<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Session;
use App\Security\Csrf;

final class LogsController extends BaseController
{
    public function __construct(
        Config $config,
        private readonly Csrf $csrf
    ) {
        parent::__construct($config);
    }

    public function show(): void
    {
        $logFile = (string) $this->config->get('LOG_FILE', __DIR__ . '/../../storage/logs/app.log');
        $maxLines = 300;
        $allowedTypes = ['INFO', 'WARN', 'ERROR'];

        $selectedTypes = $this->selectedTypes($allowedTypes);
        $sort = $this->selectedSort();

        $entries = $this->readEntries($logFile, $maxLines);
        $entries = $this->filterByTypes($entries, $selectedTypes);
        $entries = $this->sortEntries($entries, $sort);

        $this->render('setup/logs.php', [
            'csrfToken' => $this->csrf->token(),
            'entries' => $entries,
            'logFile' => $logFile,
            'maxLines' => $maxLines,
            'selectedTypes' => $selectedTypes,
            'sort' => $sort,
        ]);
    }

    public function clear(): void
    {
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            Session::setFlash('error', 'Invalid CSRF token.');
            $this->redirect('logs');
        }

        $logFile = (string) $this->config->get('LOG_FILE', __DIR__ . '/../../storage/logs/app.log');
        $dir = dirname($logFile);

        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            Session::setFlash('error', 'Unable to prepare log directory.');
            $this->redirect('logs');
        }

        if (file_put_contents($logFile, '', LOCK_EX) === false) {
            Session::setFlash('error', 'Unable to clear log file.');
            $this->redirect('logs');
        }

        Session::setFlash('success', 'Logs cleared.');
        $this->redirect('logs');
    }

    /**
     * @return array<int, array{level:string,date:string,time:string,message:string,epoch:int}>
     */
    private function readEntries(string $logFile, int $maxLines): array
    {
        if (!is_file($logFile) || !is_readable($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $lines = array_slice($lines, -$maxLines);
        $entries = [];

        foreach ($lines as $line) {
            $entries[] = $this->parseLine((string) $line);
        }

        return $entries;
    }

    /**
     * @return array{level:string,date:string,time:string,message:string,epoch:int}
     */
    private function parseLine(string $line): array
    {
        $fallback = [
            'level' => 'INFO',
            'date' => '--/--/--',
            'time' => '--:--:--',
            'message' => $line,
            'epoch' => 0,
        ];

        if (!preg_match('/^\[(?<timestamp>[^\]]+)\]\s+\[(?<level>[A-Z]+)\]\s*(?<message>.*)$/', $line, $m)) {
            return $fallback;
        }

        $timestamp = (string) ($m['timestamp'] ?? '');
        $level = strtoupper(trim((string) ($m['level'] ?? 'INFO')));
        $message = trim((string) ($m['message'] ?? ''));

        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp);
        if (!$dt instanceof \DateTimeImmutable) {
            $date = '--/--/--';
            $time = '--:--:--';
            $epoch = 0;
        } else {
            $date = $dt->format('d/m/y');
            $time = $dt->format('H:i:s');
            $epoch = (int) $dt->format('U');
        }

        return [
            'level' => $level,
            'date' => $date,
            'time' => $time,
            'message' => $message,
            'epoch' => $epoch,
        ];
    }

    /**
     * @param array<int, string> $allowedTypes
     * @return array<int, string>
     */
    private function selectedTypes(array $allowedTypes): array
    {
        $raw = $_GET['types'] ?? null;
        if (!is_array($raw)) {
            return $allowedTypes;
        }

        $selected = [];
        foreach ($raw as $type) {
            $type = strtoupper(trim((string) $type));
            if (in_array($type, $allowedTypes, true)) {
                $selected[] = $type;
            }
        }

        return $selected === [] ? $allowedTypes : array_values(array_unique($selected));
    }

    private function selectedSort(): string
    {
        $sort = strtolower(trim((string) ($_GET['sort'] ?? 'newest')));

        return in_array($sort, ['newest', 'oldest'], true) ? $sort : 'newest';
    }

    /**
     * @param array<int, array{level:string,date:string,time:string,message:string,epoch:int}> $entries
     * @param array<int, string> $selectedTypes
     * @return array<int, array{level:string,date:string,time:string,message:string,epoch:int}>
     */
    private function filterByTypes(array $entries, array $selectedTypes): array
    {
        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => in_array($entry['level'], $selectedTypes, true)
        ));
    }

    /**
     * @param array<int, array{level:string,date:string,time:string,message:string,epoch:int}> $entries
     * @return array<int, array{level:string,date:string,time:string,message:string,epoch:int}>
     */
    private function sortEntries(array $entries, string $sort): array
    {
        usort(
            $entries,
            static function (array $a, array $b) use ($sort): int {
                if ($sort === 'oldest') {
                    return $a['epoch'] <=> $b['epoch'];
                }

                return $b['epoch'] <=> $a['epoch'];
            }
        );

        return $entries;
    }
}
