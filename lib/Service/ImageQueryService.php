<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Service;

use OCA\RecentPhotos\Repository\ImageIndexRepository;
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
		[$rows, $total] = $this->repository->getPage($uid, $page, $limit, $sortBy, $sortDir, $mediaFilter);
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
