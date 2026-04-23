<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\BackgroundJob;

use OCA\RecentPhotos\Service\ImageIndexService;
use OCA\RecentPhotos\Service\IndexStatusService;
use OCP\BackgroundJob\TimedJob;
use OCP\IUserManager;

class RebuildIndexJob extends TimedJob {
    public function __construct(
        private IUserManager $userManager,
        private ImageIndexService $imageIndexService,
        private IndexStatusService $indexStatusService,
    ) {
        $this->setInterval(3600);
    }

    protected function run($argument): void {
        $this->indexStatusService->setStatus('running');

        $total = 0;
        foreach ($this->userManager->search('') as $user) {
            $total += $this->imageIndexService->rebuildForUser($user->getUID());
        }

        $this->indexStatusService->setStatus('idle', time(), $total);
    }
}
