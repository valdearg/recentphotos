<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Service;

use OCA\RecentPhotos\Repository\ImageIndexRepository;

class ImageQueryService
{
	public function __construct(private ImageIndexRepository $repository) {}

	public function getImages(string $uid, int $page, int $limit, string $sortBy, string $sortDir): array
	{
		[$rows, $total] = $this->repository->getPage($uid, $page, $limit, $sortBy, $sortDir);
		$pages = max(1, (int)ceil($total / $limit));
		return ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => $pages, 'sortBy' => $sortBy, 'sortDir' => $sortDir, 'items' => array_map([$this, 'mapRow'], $rows)];
	}

	private function mapRow(array $row): array
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
		];
	}
}
