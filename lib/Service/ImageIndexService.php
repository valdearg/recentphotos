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
	): int {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$folder = $this->resolveFolder($userFolder, $relativePath);
		$runStartedAt = time();

		$count = 0;
		$this->walkAndIndex($userId, $folder, $count, $runStartedAt, $progressCallback);

		if ($relativePath === null || trim($relativePath) === '') {
			$this->repository->deleteStaleForUser($userId, $runStartedAt);
		}

		return $count;
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
		int &$count,
		int $runStartedAt,
		?callable $progressCallback = null
	): void {
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof Folder) {
				try {
					$this->walkAndIndex($userId, $node, $count, $runStartedAt, $progressCallback);
				} catch (\Throwable $e) {
					if ($progressCallback !== null) {
						$progressCallback($count, '[folder error] ' . $node->getPath() . ' :: ' . $e->getMessage());
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

				$this->repository->replaceRow($this->mapFile($userId, $node, $mediaType, $runStartedAt));
				$count++;

				if ($progressCallback !== null) {
					$progressCallback($count, $node->getPath());
				}
			} catch (\Throwable $e) {
				if ($progressCallback !== null) {
					$progressCallback($count, '[file error] ' . $node->getPath() . ' :: ' . $e->getMessage());
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
