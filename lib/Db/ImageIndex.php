<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setPath(string $path)
 * @method string getPath()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setMime(string $mime)
 * @method string getMime()
 * @method void setSize(int $size)
 * @method int getSize()
 * @method void setMediaType(string $mediaType)
 * @method string getMediaType()
 * @method void setDateTaken(?int $dateTaken)
 * @method ?int getDateTaken()
 * @method void setCreated(int $created)
 * @method int getCreated()
 * @method void setModified(int $modified)
 * @method int getModified()
 * @method void setLastSeenAt(?int $lastSeenAt)
 * @method ?int getLastSeenAt()
 */
class ImageIndex extends Entity
{
	protected $userId = '';
	protected $fileId = 0;
	protected $path = '';
	protected $name = '';
	protected $mime = '';
	protected $size = 0;
	protected $mediaType = 'image';
	protected $dateTaken = null;
	protected $created = 0;
	protected $modified = 0;
	protected $lastSeenAt = null;

	public function __construct()
	{
		$this->addType('fileId', 'integer');
		$this->addType('size', 'integer');
		$this->addType('dateTaken', 'integer');
		$this->addType('created', 'integer');
		$this->addType('modified', 'integer');
		$this->addType('lastSeenAt', 'integer');
	}
}
