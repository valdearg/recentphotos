<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Repository;

use OCA\RecentPhotos\Db\ImageIndexMapper;

class ImageIndexRepository
{
    public function __construct(
        private ImageIndexMapper $mapper,
    ) {}

    public function clearUser(string $userId): void
    {
        $this->mapper->deleteForUser($userId);
    }

    public function deleteByFileId(int $fileId): void
    {
        $this->mapper->deleteByFileId($fileId);
    }

    public function deleteStaleForUser(string $userId, int $runStartedAt): int
    {
        return $this->mapper->deleteStaleForUser($userId, $runStartedAt);
    }

    public function replaceRow(array $row): string
    {
        return $this->mapper->upsert($row);
    }

    public function getPage(
        string $userId,
        int $page,
        int $limit,
        string $sortBy,
        string $sortDir,
        string $mediaFilter = 'all'
    ): array {
        return $this->mapper->getPage($userId, $page, $limit, $sortBy, $sortDir, $mediaFilter);
    }
}
