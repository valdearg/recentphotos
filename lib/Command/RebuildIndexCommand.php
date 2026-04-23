<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Command;

use OCA\RecentPhotos\Service\ImageIndexService;
use OCA\RecentPhotos\Service\IndexStatusService;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Only rebuild a subpath within the user home, e.g. files/Photos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->indexStatusService->setStatus('running');

        $userOption = $input->getOption('user');
        $pathOption = $input->getOption('path');
        $path = is_string($pathOption) && trim($pathOption) !== '' ? trim($pathOption, '/') : null;

        $users = $this->resolveUsers($userOption);

        if ($users === []) {
            $output->writeln('<error>No matching user found.</error>');
            $this->indexStatusService->setStatus('idle');
            return Command::FAILURE;
        }

        $grandTotal = 0;

        foreach ($users as $user) {
            $userId = $user->getUID();

            if ($path !== null) {
                $output->writeln(sprintf('Indexing media for %s in path %s ...', $userId, $path));
            } else {
                $output->writeln(sprintf('Indexing media for %s ...', $userId));
            }

            $progress = new ProgressBar($output);
            $progress->setFormat('%current% files indexed [%elapsed:6s%] %message%');
            $progress->setMessage('starting...');
            $progress->start();

            $count = $this->imageIndexService->rebuildForUser(
                $userId,
                $path,
                function (int $currentCount, string $currentPath) use ($progress): void {
                    $progress->advance();

                    if ($currentCount % 100 === 0) {
                        $progress->setMessage($currentPath);
                    }
                }
            );

            $progress->finish();
            $output->writeln('');
            $output->writeln(sprintf('Finished %s: %d files indexed', $userId, $count));

            $grandTotal += $count;
        }

        $this->indexStatusService->setStatus('idle', time(), $grandTotal);
        $output->writeln(sprintf('Done. Indexed %d files in total.', $grandTotal));

        return Command::SUCCESS;
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
}
