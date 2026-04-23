<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Controller;

use OCA\RecentPhotos\BackgroundJob\RebuildIndexJob;
use OCA\RecentPhotos\Service\IndexStatusService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;

class IndexController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IJobList $jobList,
        private IndexStatusService $indexStatusService,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @AdminRequired
     */
    public function getStatus(): DataResponse {
        return new DataResponse($this->indexStatusService->getStatus());
    }

    /**
     * @AdminRequired
     */
    public function rebuild(): DataResponse {
        $this->jobList->add(RebuildIndexJob::class, []);
        $this->indexStatusService->setStatus('queued');

        return new DataResponse([
            'status' => 'queued',
        ]);
    }
}
