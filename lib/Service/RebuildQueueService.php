<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Service;

use OCA\RecentPhotos\BackgroundJob\RebuildPathJob;
use OCP\BackgroundJob\IJobList;

class RebuildQueueService
{
    public function __construct(
        private IJobList $jobList,
    ) {}

    /**
     * @param list<string> $paths
     */
    public function queueUserPaths(string $userId, array $paths): void
    {
        foreach ($paths as $path) {
            $path = trim($path);
            if ($path === '') {
                continue;
            }

            $this->jobList->add(RebuildPathJob::class, [
                'userId' => $userId,
                'path' => $path,
            ]);
        }
    }
}
