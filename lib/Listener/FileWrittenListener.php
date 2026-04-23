<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Listener;

use OCA\RecentPhotos\Service\ImageIndexService;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;

/**
 * @template-implements IEventListener<NodeWrittenEvent>
 */
class FileWrittenListener implements IEventListener {
    public function __construct(
        private ImageIndexService $imageIndexService,
    ) {}

    public function handle($event): void {
        $node = $event->getNode();
        if (!$node instanceof File) {
            return;
        }

        $owner = $node->getOwner();
        if ($owner === null) {
            return;
        }

        $this->imageIndexService->indexFile($owner->getUID(), $node);
    }
}
