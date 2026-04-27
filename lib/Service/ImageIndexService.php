<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Service;

use OCA\RecentPhotos\Repository\ImageIndexRepository;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

class ImageIndexService
{
	private const IMAGE_MIME_PREFIX = 'image/';
	private const VIDEO_MIME_PREFIX = 'video/';

	public function __construct(
		private IRootFolder $rootFolder,
		private ImageIndexRepository $repository
	) {}

	public function rebuildForUser(
		string $userId,
		?string $relativePath = null,
		?callable $progressCallback = null
	): array {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$folder = $this->resolveFolder($userFolder, $relativePath);
		$runStartedAt = time();

		$stats = [
			'folders' => 0,
			'files' => 0,
			'new' => 0,
			'updated' => 0,
			'removed' => 0,
			'errors' => 0,
		];

		$this->walkAndIndex($userId, $folder, $stats, $runStartedAt, $progressCallback);

		// Only remove stale records on full-user rebuilds.
		// Path-scoped rebuilds are intentionally non-destructive.
		if ($relativePath === null || trim($relativePath) === '') {
			$stats['removed'] = $this->repository->deleteStaleForUser($userId, $runStartedAt);
		}

		return $stats;
	}

	public function indexFile(string $userId, File $file): void
	{
		$mediaType = $this->classifyMedia($file->getMimeType(), $file->getName());
		if ($mediaType === null) {
			return;
		}

		$this->repository->replaceRow($this->mapFile($userId, $file, $mediaType, time()));
	}

	public function removeFile(int $fileId): void
	{
		$this->repository->deleteByFileId($fileId);
	}

	private function resolveFolder(Folder $userFolder, ?string $relativePath): Folder
	{
		if ($relativePath === null || trim($relativePath) === '') {
			return $userFolder;
		}

		$normalized = trim($relativePath);
		$nodes = $userFolder->get($normalized);

		if ($nodes instanceof Folder) {
			return $nodes;
		}

		throw new \RuntimeException(sprintf('Path "%s" is not a folder or does not exist.', $normalized));
	}

	private function walkAndIndex(
		string $userId,
		Folder $folder,
		array &$stats,
		int $runStartedAt,
		?callable $progressCallback = null
	): void {
		$stats['folders']++;

		try {
			$listing = $folder->getDirectoryListing();
		} catch (\Throwable $e) {
			$stats['errors']++;
			if ($progressCallback !== null) {
				$progressCallback($stats['files'], '[folder error] ' . $folder->getPath() . ' :: ' . $e->getMessage());
			}
			return;
		}

		foreach ($listing as $node) {
			if ($node instanceof Folder) {
				try {
					$this->walkAndIndex($userId, $node, $stats, $runStartedAt, $progressCallback);
				} catch (\Throwable $e) {
					$stats['errors']++;
					if ($progressCallback !== null) {
						$progressCallback($stats['files'], '[folder error] ' . $node->getPath() . ' :: ' . $e->getMessage());
					}
				}
				continue;
			}

			if (!$node instanceof File) {
				continue;
			}

			try {
				$mediaType = $this->classifyMedia($node->getMimeType(), $node->getName());
				if ($mediaType === null) {
					continue;
				}

				$result = $this->repository->replaceRow(
					$this->mapFile($userId, $node, $mediaType, $runStartedAt)
				);

				$stats['files']++;

				if ($result === 'new') {
					$stats['new']++;
				} else {
					$stats['updated']++;
				}

				if ($progressCallback !== null) {
					$progressCallback($stats['files'], $node->getPath());
				}
			} catch (\Throwable $e) {
				$stats['errors']++;
				if ($progressCallback !== null) {
					$progressCallback($stats['files'], '[file error] ' . $node->getPath() . ' :: ' . $e->getMessage());
				}
				continue;
			}
		}
	}

	private function classifyMedia(string $mime, string $name): ?string
	{
		$lowerName = strtolower($name);

		if ($mime === 'image/gif' || str_ends_with($lowerName, '.gif')) {
			return 'gif';
		}

		if (str_starts_with($mime, self::IMAGE_MIME_PREFIX)) {
			return 'image';
		}

		if (str_starts_with($mime, self::VIDEO_MIME_PREFIX)) {
			return 'video';
		}

		return null;
	}

	private function mapFile(string $userId, File $file, string $mediaType, int $lastSeenAt): array
	{
		$created = method_exists($file, 'getCreationTime')
			? ($file->getCreationTime() ?: $file->getMTime())
			: $file->getMTime();

		$dateTaken = $mediaType === 'video' ? null : $this->extractDateTaken($file);

		return [
			'userId' => $userId,
			'fileId' => $file->getId(),
			'path' => $file->getPath(),
			'name' => $file->getName(),
			'mime' => $file->getMimeType(),
			'size' => $file->getSize(),
			'mediaType' => $mediaType,
			'dateTaken' => $dateTaken,
			'created' => $created,
			'modified' => $file->getMTime(),
			'lastSeenAt' => $lastSeenAt,
		];
	}

	private function extractDateTaken(File $file): ?int
	{
		$mime = $file->getMimeType();
		if (!in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
			return null;
		}

		$tmp = tempnam(sys_get_temp_dir(), 'rp_exif_');
		if ($tmp === false) {
			return null;
		}

		try {
			$content = $file->getContent();
			if ($content === '' || $content === false) {
				return null;
			}

			if (file_put_contents($tmp, $content) === false) {
				return null;
			}

			$exif = @exif_read_data($tmp, null, true);
			if (!is_array($exif)) {
				return null;
			}

			$candidates = [
				$exif['EXIF']['DateTimeOriginal'] ?? null,
				$exif['EXIF']['DateTimeDigitized'] ?? null,
				$exif['IFD0']['DateTime'] ?? null,
			];

			foreach ($candidates as $value) {
				if (!is_string($value) || trim($value) === '') {
					continue;
				}

				$dt = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $value, new \DateTimeZone('UTC'));
				if ($dt !== false) {
					return $dt->getTimestamp();
				}
			}

			return null;
		} catch (\Throwable $e) {
			return null;
		} finally {
			@unlink($tmp);
		}
	}
}
