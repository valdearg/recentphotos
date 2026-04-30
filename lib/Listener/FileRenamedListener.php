<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Listener;

use OCA\RecentPhotos\Service\ImageIndexService;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\File;

/**
 * @template-implements IEventListener<NodeRenamedEvent>
 */
class FileRenamedListener implements IEventListener {
    public function __construct(
        private ImageIndexService $imageIndexService,
    ) {}

    public function handle($event): void {
        $node = $event->getTarget();
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
