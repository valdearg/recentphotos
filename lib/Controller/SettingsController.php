<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Controller;

use OCA\RecentPhotos\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SettingsController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private SettingsService $settingsService,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function getPersonal(): DataResponse {
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        return new DataResponse($this->settingsService->getPersonalSettings($uid));
    }

    /**
     * @NoAdminRequired
     */
    public function savePersonal(
        string $displayMode = 'pagination',
        int $pageSize = 100,
        string $sortBy = 'created',
        string $sortDir = 'desc'
    ): DataResponse {
        $uid = $this->userSession->getUser()?->getUID() ?? '';
        $this->settingsService->savePersonalSettings($uid, [
            'displayMode' => $displayMode,
            'pageSize' => $pageSize,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);

        return new DataResponse([
            'status' => 'ok',
            'settings' => $this->settingsService->getEffectiveSettings($uid),
        ]);
    }

    /**
     * @AdminRequired
     */
    public function getAdmin(): DataResponse {
        return new DataResponse($this->settingsService->getAdminSettings());
    }

    /**
     * @AdminRequired
     */
    public function saveAdmin(
        string $defaultDisplayMode = 'pagination',
        int $defaultPageSize = 100,
        int $maxPageSize = 500,
        string $defaultSortBy = 'created',
        string $defaultSortDir = 'desc'
    ): DataResponse {
        $this->settingsService->saveAdminSettings([
            'defaultDisplayMode' => $defaultDisplayMode,
            'defaultPageSize' => $defaultPageSize,
            'maxPageSize' => $maxPageSize,
            'defaultSortBy' => $defaultSortBy,
            'defaultSortDir' => $defaultSortDir,
        ]);

        return new DataResponse([
            'status' => 'ok',
            'settings' => $this->settingsService->getAdminSettings(),
        ]);
    }
}
