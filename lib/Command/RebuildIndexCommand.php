<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Command;

use OCA\RecentPhotos\Service\ImageIndexService;
use OCA\RecentPhotos\Service\IndexStatusService;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class RebuildIndexCommand extends Command
{
    public function __construct(
        private IUserManager $userManager,
        private ImageIndexService $imageIndexService,
        private IndexStatusService $indexStatusService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('recentphotos:rebuild-index')
            ->setDescription('Rebuild the Recent Photos media index')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Only rebuild for a specific user ID')
            ->addOption(
                'delete-stale',
                null,
                InputOption::VALUE_NONE,
                'For path-based scans, remove stale indexed rows under the scanned path'
            )
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Only rebuild one or more subpaths, e.g. --path="/Photos/Pixiv"'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexStatusService->setStatus('running');

        $userOption = $input->getOption('user');
        $pathOption = $input->getOption('path');

        $paths = [];
        if (is_array($pathOption)) {
            foreach ($pathOption as $path) {
                if (is_string($path) && trim($path) !== '') {
                    $paths[] = trim($path);
                }
            }
        }

        $users = $this->resolveUsers($userOption);

        $deleteStale = (bool)$input->getOption('delete-stale');

        if ($users === []) {
            $output->writeln('<error>No matching user found.</error>');
            $this->indexStatusService->setStatus('idle');
            return Command::FAILURE;
        }

        $grandStats = $this->emptyStats();

        foreach ($users as $user) {
            $userId = $user->getUID();

            if ($paths !== []) {
                foreach ($paths as $path) {
                    $output->writeln(sprintf('Indexing media for %s in path %s ...', $userId, $path));
                    $stats = $this->runIndex($output, $userId, $path, $deleteStale);
                    $this->mergeStats($grandStats, $stats);
                }
            } else {
                $output->writeln(sprintf('Indexing media for %s ...', $userId));
                $stats = $this->runIndex($output, $userId, null, $deleteStale);
                $this->mergeStats($grandStats, $stats);
            }
        }

        $this->indexStatusService->setStatus('idle', time(), (int)$grandStats['files']);
        $output->writeln(sprintf('Done. Indexed %d files in total.', (int)$grandStats['files']));

        if (count($users) > 1 || count($paths) > 1) {
            $output->writeln('');
            $output->writeln('Total:');
            $this->renderStatsTable($output, $grandStats, (int)($grandStats['elapsed'] ?? 0));
        }

        return Command::SUCCESS;
    }

    private function runIndex(OutputInterface $output, string $userId, ?string $path, bool $deleteStale): array
    {
        $started = microtime(true);

        $terminal = new Terminal();

        $progress = new ProgressBar($output);
        $progress->setFormat('%current% files indexed [%elapsed:6s%] %message%');
        $progress->setMessage('starting...');
        $progress->start();

        $stats = $this->imageIndexService->rebuildForUser(
            $userId,
            $path,
            function (int $currentCount, string $currentPath) use ($progress, $output, $terminal): void {
                $progress->advance();

                if (str_starts_with($currentPath, '[file error]') || str_starts_with($currentPath, '[folder error]')) {
                    $progress->clear();
                    $output->writeln($currentPath);
                    $progress->display();
                    return;
                }

                if ($currentCount % 100 === 0) {
                    $termWidth = $terminal->getWidth() ?: 120;
                    $maxPathLength = max(30, $termWidth - 50);
                    $progress->setMessage($this->formatProgressPath($currentPath, $maxPathLength));
                }
            },
            $deleteStale
        );

        $elapsed = (int)round(microtime(true) - $started);
        $stats['elapsed'] = $elapsed;

        $progress->finish();
        $output->writeln('');
        $this->renderStatsTable($output, $stats, $elapsed);
        $output->writeln('');

        return $stats;
    }

    /**
     * @return list<IUser>
     */
    private function resolveUsers(mixed $userOption): array
    {
        if (is_string($userOption) && trim($userOption) !== '') {
            $user = $this->userManager->get(trim($userOption));
            return $user instanceof IUser ? [$user] : [];
        }

        return array_values($this->userManager->search(''));
    }

    private function emptyStats(): array
    {
        return [
            'folders' => 0,
            'files' => 0,
            'new' => 0,
            'updated' => 0,
            'removed' => 0,
            'errors' => 0,
            'elapsed' => 0,
        ];
    }

    private function mergeStats(array &$target, array $source): void
    {
        foreach ($this->emptyStats() as $key => $_) {
            $target[$key] = (int)($target[$key] ?? 0) + (int)($source[$key] ?? 0);
        }
    }

    private function renderStatsTable(OutputInterface $output, array $stats, int $elapsedSeconds): void
    {
        $table = new Table($output);
        $table
            ->setHeaders(['Folders', 'Files', 'New', 'Updated', 'Removed', 'Errors', 'Elapsed time'])
            ->setRows([[
                (string)($stats['folders'] ?? 0),
                (string)($stats['files'] ?? 0),
                (string)($stats['new'] ?? 0),
                (string)($stats['updated'] ?? 0),
                (string)($stats['removed'] ?? 0),
                (string)($stats['errors'] ?? 0),
                $this->formatElapsed($elapsedSeconds),
            ]]);

        $table->render();
    }

    private function formatElapsed(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    private function formatProgressPath(string $path, int $maxLength): string
    {
        $path = preg_replace('#^/[^/]+/files/#', '', $path) ?? $path;

        $parts = explode('/', trim($path, '/'));
        $filename = array_pop($parts) ?: $path;

        $folderParts = array_slice($parts, -2);
        $folder = implode('/', $folderParts);

        $message = $folder !== ''
            ? $folder . ' → ' . $filename
            : $filename;

        if (mb_strlen($message) <= $maxLength) {
            return $message;
        }

        $filenameMax = max(20, (int)($maxLength * 0.55));
        if (mb_strlen($filename) > $filenameMax) {
            $filename = '…' . mb_substr($filename, - ($filenameMax - 1));
        }

        $folderMax = max(8, $maxLength - mb_strlen($filename) - 3);
        if (mb_strlen($folder) > $folderMax) {
            $folder = '…' . mb_substr($folder, - ($folderMax - 1));
        }

        return $folder !== ''
            ? $folder . ' → ' . $filename
            : $filename;
    }
}
