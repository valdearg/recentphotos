<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Settings;

use OCP\IL10N;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {
    public function __construct(
        private IL10N $l,
    ) {}

    public function getID(): string {
        return 'recentphotos';
    }

    public function getName(): string {
        return $this->l->t('Recent Photos');
    }

    public function getPriority(): int {
        return 50;
    }

    public function getIcon(): string {
        return '/apps/recentphotos/img/app.svg';
    }
}
