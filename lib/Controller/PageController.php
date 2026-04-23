<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Controller;

use OCA\RecentPhotos\Service\IndexStatusService;
use OCA\RecentPhotos\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IUserSession;

class PageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IInitialState $initialState,
        private SettingsService $settingsService,
        private IndexStatusService $indexStatusService,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $user = $this->userSession->getUser();
        $uid = $user?->getUID() ?? '';

        $this->initialState->provideInitialState('settings', $this->settingsService->getEffectiveSettings($uid));
        $this->initialState->provideInitialState('indexStatus', $this->indexStatusService->getStatus());

        return new TemplateResponse('recentphotos', 'main');
    }
}
