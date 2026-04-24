<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Command;

use OCA\RecentPhotos\Service\RebuildQueueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueRebuildCommand extends Command
{
    public function __construct(
        private RebuildQueueService $queueService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('recentphotos:queue-rebuild')
            ->setDescription('Queue background rebuild jobs for one user')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Repeatable path, e.g. --path="files/Photos/Pixiv"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getOption('user');
        $paths = $input->getOption('path');

        if (!is_string($userId) || trim($userId) === '') {
            $output->writeln('<error>--user is required</error>');
            return Command::FAILURE;
        }

        if (!is_array($paths) || $paths === []) {
            $output->writeln('<error>At least one --path is required</error>');
            return Command::FAILURE;
        }

        $this->queueService->queueUserPaths(trim($userId), array_values($paths));
        $output->writeln(sprintf('Queued %d rebuild job(s) for %s', count($paths), trim($userId)));

        return Command::SUCCESS;
    }
}
