<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Service;

use OCA\RecentPhotos\AppInfo\Application;
use OCP\IConfig;

class IndexStatusService {
    public function __construct(
        private IConfig $config,
    ) {}

    public function getStatus(): array {
        return [
            'status' => $this->config->getAppValue(Application::APP_ID, 'index_status', 'idle'),
            'lastRun' => (int)$this->config->getAppValue(Application::APP_ID, 'index_last_run', '0'),
            'lastCount' => (int)$this->config->getAppValue(Application::APP_ID, 'index_last_count', '0'),
        ];
    }

    public function setStatus(string $status, int $lastRun = 0, int $lastCount = 0): void {
        $this->config->setAppValue(Application::APP_ID, 'index_status', $status);
        if ($lastRun > 0) {
            $this->config->setAppValue(Application::APP_ID, 'index_last_run', (string)$lastRun);
        }
        $this->config->setAppValue(Application::APP_ID, 'index_last_count', (string)$lastCount);
    }
}
