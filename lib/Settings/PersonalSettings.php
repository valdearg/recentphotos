<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Settings;

use OCA\RecentPhotos\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\IUserSession;

class PersonalSettings implements ISettings {
    public function __construct(
        private SettingsService $settingsService,
        private IUserSession $userSession,
    ) {}

    public function getForm(): TemplateResponse {
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        return new TemplateResponse('recentphotos', 'settings/personal', [
            'settings' => $this->settingsService->getEffectiveSettings($uid),
        ], '');
    }

    public function getSection(): string {
        return 'recentphotos';
    }

    public function getPriority(): int {
        return 50;
    }
}
