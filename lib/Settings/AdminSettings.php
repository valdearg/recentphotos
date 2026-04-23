<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Settings;

use OCA\RecentPhotos\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    public function getForm(): TemplateResponse {
        return new TemplateResponse('recentphotos', 'settings/admin', [
            'settings' => $this->settingsService->getAdminSettings(),
        ], '');
    }

    public function getSection(): string {
        return 'recentphotos';
    }

    public function getPriority(): int {
        return 50;
    }
}
