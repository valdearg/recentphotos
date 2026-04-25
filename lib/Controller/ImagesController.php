<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Controller;

use OCA\RecentPhotos\Service\ImageQueryService;
use OCA\RecentPhotos\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;

class ImagesController extends Controller
{
	public function __construct(
		string $appName,
		IRequest $request,
		private ImageQueryService $imageQueryService,
		private SettingsService $settingsService,
		private IUserSession $userSession,
		private IRootFolder $rootFolder,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(
		int $page = 1,
		?int $limit = null,
		?string $sortBy = null,
		?string $sortDir = null,
		?string $mediaFilter = null
	): DataResponse {
		$user = $this->userSession->getUser();
		$uid = $user?->getUID() ?? '';

		$effective = $this->settingsService->getEffectiveSettings($uid);

		$limit = $limit ?: (int)$effective['pageSize'];
		$sortBy = $sortBy ?: (string)$effective['sortBy'];
		$sortDir = $sortDir ?: (string)$effective['sortDir'];
		$mediaFilter = $mediaFilter ?: 'all';

		$result = $this->imageQueryService->getImages(
			$uid,
			max(1, $page),
			max(1, min($limit, (int)$effective['maxPageSize'])),
			$sortBy,
			$sortDir,
			$mediaFilter,
		);

		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function view(int $fileId)
	{
		$user = $this->userSession->getUser();
		$uid = $user?->getUID() ?? '';

		$nodes = $this->rootFolder->getById($fileId);
		if ($nodes === []) {
			return new NotFoundResponse();
		}

		foreach ($nodes as $node) {
			if ($node instanceof File) {
				$owner = $node->getOwner();
				if ($owner === null || $owner->getUID() !== $uid) {
					continue;
				}

				return new FileDisplayResponse($node);
			}
		}

		return new NotFoundResponse();
	}
}
