<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class ImageIndexMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'recentphotos_index', ImageIndex::class);
	}

	public function deleteForUser(string $userId): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recentphotos_index')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	public function deleteByFileId(int $fileId): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete('recentphotos_index')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));
		$qb->executeStatement();
	}

	public function deleteStaleForUser(string $userId, int $runStartedAt): int
	{
		$qb = $this->db->getQueryBuilder();
		$expr = $qb->expr();

		$qb->delete('recentphotos_index')
			->where($expr->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere(
				$expr->orX(
					$expr->lt('last_seen_at', $qb->createNamedParameter($runStartedAt)),
					$expr->isNull('last_seen_at')
				)
			);

		return $qb->executeStatement();
	}

	public function upsert(array $row): void
	{
		$this->deleteByFileId((int)$row['fileId']);

		$entity = new ImageIndex();
		$entity->setUserId((string)$row['userId']);
		$entity->setFileId((int)$row['fileId']);
		$entity->setPath((string)$row['path']);
		$entity->setName((string)$row['name']);
		$entity->setMime((string)$row['mime']);
		$entity->setSize((int)$row['size']);
		$entity->setMediaType((string)($row['mediaType'] ?? 'image'));
		$entity->setDateTaken(isset($row['dateTaken']) ? (int)$row['dateTaken'] : null);
		$entity->setCreated((int)$row['created']);
		$entity->setModified((int)$row['modified']);
		$entity->setLastSeenAt(isset($row['lastSeenAt']) ? (int)$row['lastSeenAt'] : null);

		$this->insert($entity);
	}

	public function getPage(
		string $userId,
		int $page,
		int $limit,
		string $sortBy,
		string $sortDir,
		string $mediaFilter = 'all'
	): array {
		$allowedSort = ['date_taken', 'created', 'modified', 'name', 'size'];
		if (!in_array($sortBy, $allowedSort, true)) {
			$sortBy = 'date_taken';
		}

		$allowedMedia = ['all', 'image', 'gif', 'video'];
		if (!in_array($mediaFilter, $allowedMedia, true)) {
			$mediaFilter = 'all';
		}

		$sortDir = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
		$offset = ($page - 1) * $limit;

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('recentphotos_index')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy($sortBy, $sortDir)
			->setFirstResult($offset)
			->setMaxResults($limit);

		if ($mediaFilter !== 'all') {
			$qb->andWhere($qb->expr()->eq('media_type', $qb->createNamedParameter($mediaFilter)));
		}

		$rows = $qb->executeQuery()->fetchAllAssociative();

		$countQb = $this->db->getQueryBuilder();
		$countQb->selectAlias($countQb->func()->count('*'), 'total')
			->from('recentphotos_index')
			->where($countQb->expr()->eq('user_id', $countQb->createNamedParameter($userId)));

		if ($mediaFilter !== 'all') {
			$countQb->andWhere($countQb->expr()->eq('media_type', $countQb->createNamedParameter($mediaFilter)));
		}

		$total = (int)$countQb->executeQuery()->fetchOne();

		return [$rows, $total];
	}
}
