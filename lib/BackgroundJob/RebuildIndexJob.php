<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\BackgroundJob;

use OCA\RecentPhotos\Service\ImageIndexService;
use OCP\BackgroundJob\QueuedJob;

class RebuildPathJob extends QueuedJob
{
    public function __construct()
    {
        parent::__construct();
        $this->setAllowParallelRuns(false);
    }

    protected function run($argument): void
    {
        $userId = is_array($argument) ? ($argument['userId'] ?? null) : null;
        $path = is_array($argument) ? ($argument['path'] ?? null) : null;

        if (!is_string($userId) || $userId === '') {
            return;
        }

        /** @var ImageIndexService $service */
        $service = \OC::$server->get(ImageIndexService::class);
        $service->rebuildForUser($userId, is_string($path) && $path !== '' ? $path : null, null);
    }
}
