<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Listener;

use OCA\RecentPhotos\Service\ImageIndexService;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;

/**
 * @template-implements IEventListener<NodeDeletedEvent>
 */
class FileDeletedListener implements IEventListener {
    public function __construct(
        private ImageIndexService $imageIndexService,
    ) {}

    public function handle($event): void {
        $node = $event->getNode();
        $fileId = method_exists($node, 'getId') ? $node->getId() : null;
        if ($fileId === null) {
            return;
        }

        $this->imageIndexService->removeFile((int)$fileId);
    }
}
