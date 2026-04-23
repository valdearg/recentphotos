<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\AppInfo;

use OCA\RecentPhotos\Listener\FileDeletedListener;
use OCA\RecentPhotos\Listener\FileWrittenListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'recentphotos';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(NodeWrittenEvent::class, FileWrittenListener::class);
        $context->registerEventListener(NodeDeletedEvent::class, FileDeletedListener::class);
    }

    public function boot(IBootContext $context): void {
        // Intentionally do not queue a rebuild on every boot.
        // Rebuilds should be requested explicitly or handled incrementally.
    }
}
