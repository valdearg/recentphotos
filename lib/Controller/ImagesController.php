<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Controller;

use OCA\RecentPhotos\Service\ImageQueryService;
use OCA\RecentPhotos\Service\ImageIndexService;
use OCA\RecentPhotos\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
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
		private ImageIndexService $imageIndexService,
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
			$user,
		);

		return new DataResponse($result);
	}

	#[NoAdminRequired]
	public function delete(): DataResponse
	{
		$user = $this->userSession->getUser();
		$uid = $user?->getUID() ?? '';
		if ($uid === '') {
			return new DataResponse(['message' => 'User not found.'], Http::STATUS_FORBIDDEN);
		}

		$fileIds = $this->getRequestedFileIds();
		if ($fileIds === []) {
			return new DataResponse(['message' => 'No files selected.'], Http::STATUS_BAD_REQUEST);
		}

		$deleted = [];
		$failed = [];

		foreach ($fileIds as $fileId) {
			$file = $this->getUserFileById($fileId, $uid);
			if ($file === null) {
				$failed[] = ['fileId' => $fileId, 'message' => 'File not found.'];
				continue;
			}

			try {
				$file->delete();
				$this->imageIndexService->removeFile($fileId);
				$deleted[] = $fileId;
			} catch (\Throwable $e) {
				$failed[] = ['fileId' => $fileId, 'message' => $e->getMessage()];
			}
		}

		$status = $failed === [] ? Http::STATUS_OK : 207;
		return new DataResponse([
			'deleted' => $deleted,
			'failed' => $failed,
		], $status);
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

	private function getRequestedFileIds(): array
	{
		$fileIds = $this->request->getParam('fileIds', []);

		if (!is_array($fileIds)) {
			$fileIds = [];
		}

		if ($fileIds === []) {
			$rawBody = file_get_contents('php://input');
			$body = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : null;
			if (is_array($body) && is_array($body['fileIds'] ?? null)) {
				$fileIds = $body['fileIds'];
			}
		}

		return array_values(array_unique(array_filter(array_map('intval', $fileIds), static fn(int $id): bool => $id > 0)));
	}

	private function getUserFileById(int $fileId, string $uid): ?File
	{
		try {
			$nodes = $this->rootFolder->getById($fileId);
		} catch (\Throwable $e) {
			return null;
		}

		foreach ($nodes as $node) {
			if (!$node instanceof File) {
				continue;
			}

			$owner = $node->getOwner();
			if ($owner === null || $owner->getUID() !== $uid) {
				continue;
			}

			if (str_starts_with($node->getPath(), '/' . $uid . '/files/')) {
				return $node;
			}
		}

		return null;
	}
}
