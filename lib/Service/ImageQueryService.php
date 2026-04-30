<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Service;

use OCA\RecentPhotos\Repository\ImageIndexRepository;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;

class ImageQueryService
{
	public function __construct(
		private ImageIndexRepository $repository,
		private IRootFolder $rootFolder,
		private ISystemTagObjectMapper $tagObjectMapper,
		private ISystemTagManager $tagManager,
	) {}

	public function getImages(
		string $uid,
		int $page,
		int $limit,
		string $sortBy,
		string $sortDir,
		string $mediaFilter = 'all',
		?IUser $user = null,
	): array {
		$attempts = 0;

		do {
			[$rows, $total] = $this->repository->getPage($uid, $page, $limit, $sortBy, $sortDir, $mediaFilter);
			[$rows, $removed] = $this->filterExistingRows($uid, $rows);
			$attempts++;
		} while ($removed > 0 && count($rows) < $limit && $attempts < 10);

		$total = max(0, $total - $removed);
		$pages = max(1, (int)ceil($total / $limit));
		$folderTags = $this->getFolderTagsForRows($uid, $rows, $user);

		return [
			'page' => $page,
			'limit' => $limit,
			'total' => $total,
			'pages' => $pages,
			'sortBy' => $sortBy,
			'sortDir' => $sortDir,
			'mediaFilter' => $mediaFilter,
			'items' => array_map(
				fn(array $row): array => $this->mapRow($row, $folderTags[(string)$row['path']] ?? []),
				$rows,
			),
		];
	}

	private function filterExistingRows(string $uid, array $rows): array
	{
		if ($uid === '' || $rows === []) {
			return [$rows, 0];
		}

		$validRows = [];
		$removed = 0;

		foreach ($rows as $row) {
			$fileId = (int)($row['file_id'] ?? 0);
			$file = $fileId > 0 ? $this->getLiveFileForUser($fileId, $uid) : null;
			if ($file === null) {
				if ($fileId > 0) {
					$this->repository->deleteByFileId($fileId);
				}
				$removed++;
				continue;
			}

			$refreshedRow = $this->refreshRowFromFile($row, $uid, $file);
			if ($refreshedRow === null) {
				$this->repository->deleteByFileId($fileId);
				$removed++;
				continue;
			}

			$validRows[] = $refreshedRow;
		}

		return [$validRows, $removed];
	}

	private function getLiveFileForUser(int $fileId, string $uid): ?File
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

	private function refreshRowFromFile(array $row, string $uid, File $file): ?array
	{
		$mediaType = $this->classifyMedia($file->getMimeType(), $file->getName());
		if ($mediaType === null) {
			return null;
		}

		$refreshed = $row;
		$refreshed['user_id'] = $uid;
		$refreshed['file_id'] = $file->getId();
		$refreshed['path'] = $file->getPath();
		$refreshed['name'] = $file->getName();
		$refreshed['mime'] = $file->getMimeType();
		$refreshed['size'] = $file->getSize();
		$refreshed['media_type'] = $mediaType;
		$refreshed['modified'] = $file->getMTime();
		$refreshed['last_seen_at'] = time();

		if ($this->rowNeedsRefresh($row, $refreshed)) {
			$this->repository->replaceRow([
				'userId' => $uid,
				'fileId' => (int)$refreshed['file_id'],
				'path' => (string)$refreshed['path'],
				'name' => (string)$refreshed['name'],
				'mime' => (string)$refreshed['mime'],
				'size' => (int)$refreshed['size'],
				'mediaType' => (string)$refreshed['media_type'],
				'dateTaken' => isset($refreshed['date_taken']) && $refreshed['date_taken'] !== null ? (int)$refreshed['date_taken'] : null,
				'created' => (int)$refreshed['created'],
				'modified' => (int)$refreshed['modified'],
				'lastSeenAt' => (int)$refreshed['last_seen_at'],
			]);
		}

		return $refreshed;
	}

	private function rowNeedsRefresh(array $row, array $refreshed): bool
	{
		foreach (['path', 'name', 'mime', 'media_type'] as $key) {
			if ((string)($row[$key] ?? '') !== (string)($refreshed[$key] ?? '')) {
				return true;
			}
		}

		return (int)($row['size'] ?? 0) !== (int)($refreshed['size'] ?? 0)
			|| (int)($row['modified'] ?? 0) !== (int)($refreshed['modified'] ?? 0);
	}

	private function classifyMedia(string $mime, string $name): ?string
	{
		$lowerName = strtolower($name);

		if ($mime === 'image/gif' || str_ends_with($lowerName, '.gif')) {
			return 'gif';
		}

		if (str_starts_with($mime, 'image/')) {
			return 'image';
		}

		if (str_starts_with($mime, 'video/')) {
			return 'video';
		}

		return null;
	}

	private function mapRow(array $row, array $folderTags): array
	{
		$fileId = (int)$row['file_id'];
		$mediaType = (string)($row['media_type'] ?? 'image');
		$directUrl = '/apps/recentphotos/view?fileId=' . $fileId;

		$previewUrl = $mediaType === 'gif'
			? $directUrl
			: '/core/preview?fileId=' . $fileId . '&x=384&y=384&a=true';

		return [
			'id' => $fileId,
			'name' => (string)$row['name'],
			'path' => (string)$row['path'],
			'mime' => (string)$row['mime'],
			'size' => (int)$row['size'],
			'mediaType' => $mediaType,
			'dateTaken' => isset($row['date_taken']) && $row['date_taken'] !== null ? (int)$row['date_taken'] : null,
			'created' => (int)$row['created'],
			'modified' => (int)$row['modified'],
			'previewUrl' => $previewUrl,
			'fullUrl' => $directUrl,
			'openUrl' => '/f/' . $fileId,
			'downloadUrl' => $directUrl,
			'folderTags' => $folderTags,
		];
	}

	private function getFolderTagsForRows(string $uid, array $rows, ?IUser $user): array
	{
		if ($uid === '' || $rows === []) {
			return [];
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
		} catch (\Throwable $e) {
			return [];
		}

		$folderIdsByPath = [];
		$folderIdsByImagePath = [];

		foreach ($rows as $row) {
			$imagePath = (string)($row['path'] ?? '');
			if ($imagePath === '') {
				continue;
			}

			$folderPath = $this->getRelativeFolderPath($uid, $imagePath);
			if ($folderPath === null) {
				continue;
			}

			if (!array_key_exists($folderPath, $folderIdsByPath)) {
				$folderIdsByPath[$folderPath] = $this->getFolderId($userFolder, $folderPath);
			}

			if ($folderIdsByPath[$folderPath] !== null) {
				$folderIdsByImagePath[$imagePath] = (string)$folderIdsByPath[$folderPath];
			}
		}

		if ($folderIdsByImagePath === []) {
			return [];
		}

		try {
			$tagIdsByFolderId = $this->tagObjectMapper->getTagIdsForObjects(
				array_values(array_unique($folderIdsByImagePath)),
				'files',
			);
		} catch (\Throwable $e) {
			return [];
		}

		$tagIds = [];
		foreach ($tagIdsByFolderId as $folderTagIds) {
			foreach ($folderTagIds as $tagId) {
				$tagIds[] = (string)$tagId;
			}
		}

		$tagsById = $this->getVisibleTagsById(array_values(array_unique($tagIds)), $user);
		if ($tagsById === []) {
			return [];
		}

		$tagsByImagePath = [];
		foreach ($folderIdsByImagePath as $imagePath => $folderId) {
			$tags = [];
			foreach ($tagIdsByFolderId[$folderId] ?? [] as $tagId) {
				$tag = $tagsById[(string)$tagId] ?? null;
				if ($tag !== null) {
					$tags[] = $tag;
				}
			}

			usort($tags, fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
			$tagsByImagePath[$imagePath] = $tags;
		}

		return $tagsByImagePath;
	}

	private function getRelativeFolderPath(string $uid, string $imagePath): ?string
	{
		$prefix = '/' . $uid . '/files/';
		if (!str_starts_with($imagePath, $prefix)) {
			return null;
		}

		$relativePath = substr($imagePath, strlen($prefix));
		$folderPath = trim(str_replace('\\', '/', dirname($relativePath)), '/');

		return $folderPath === '.' ? '' : $folderPath;
	}

	private function getFolderId(Folder $userFolder, string $folderPath): ?int
	{
		try {
			$folder = $folderPath === '' ? $userFolder : $userFolder->get($folderPath);
			return $folder instanceof Folder ? $folder->getId() : null;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function getVisibleTagsById(array $tagIds, ?IUser $user): array
	{
		if ($tagIds === []) {
			return [];
		}

		try {
			$tags = $this->tagManager->getTagsByIds($tagIds);
		} catch (\Throwable $e) {
			return [];
		}

		$visibleTags = [];
		foreach ($tags as $tag) {
			if (!$tag instanceof ISystemTag) {
				continue;
			}

			if ($user !== null) {
				try {
					if (!$this->tagManager->canUserSeeTag($tag, $user)) {
						continue;
					}
				} catch (\Throwable $e) {
					continue;
				}
			}

			$visibleTags[$tag->getId()] = [
				'id' => $tag->getId(),
				'name' => $tag->getName(),
				'color' => method_exists($tag, 'getColor') ? $tag->getColor() : null,
			];
		}

		return $visibleTags;
	}
}
