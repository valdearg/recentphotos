<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Service;

use OCA\RecentPhotos\AppInfo\Application;
use OCP\IConfig;

class SettingsService {
	public const DISPLAY_MODES = ['pagination', 'infinite'];
	public const SORT_FIELDS = ['date_taken', 'created', 'modified', 'name', 'size'];
	public const SORT_DIRECTIONS = ['asc', 'desc'];

	public function __construct(private IConfig $config) {}

	public function getAdminSettings(): array {
		return [
			'defaultDisplayMode' => $this->sanitizeDisplayMode($this->config->getAppValue(Application::APP_ID, 'defaultDisplayMode', 'pagination')),
			'defaultPageSize' => max(1, (int)$this->config->getAppValue(Application::APP_ID, 'defaultPageSize', '100')),
			'maxPageSize' => max(1, (int)$this->config->getAppValue(Application::APP_ID, 'maxPageSize', '500')),
			'defaultSortBy' => $this->sanitizeSortField($this->config->getAppValue(Application::APP_ID, 'defaultSortBy', 'date_taken')),
			'defaultSortDir' => $this->sanitizeSortDirection($this->config->getAppValue(Application::APP_ID, 'defaultSortDir', 'desc')),
		];
	}

	public function saveAdminSettings(array $settings): void {
		$this->config->setAppValue(Application::APP_ID, 'defaultDisplayMode', $this->sanitizeDisplayMode((string)$settings['defaultDisplayMode']));
		$this->config->setAppValue(Application::APP_ID, 'defaultPageSize', (string)max(1, (int)$settings['defaultPageSize']));
		$this->config->setAppValue(Application::APP_ID, 'maxPageSize', (string)max(1, (int)$settings['maxPageSize']));
		$this->config->setAppValue(Application::APP_ID, 'defaultSortBy', $this->sanitizeSortField((string)$settings['defaultSortBy']));
		$this->config->setAppValue(Application::APP_ID, 'defaultSortDir', $this->sanitizeSortDirection((string)$settings['defaultSortDir']));
	}

	public function getPersonalSettings(string $uid): array {
		return [
			'displayMode' => $this->sanitizeDisplayMode($this->config->getUserValue($uid, Application::APP_ID, 'displayMode', 'pagination')),
			'pageSize' => max(1, (int)$this->config->getUserValue($uid, Application::APP_ID, 'pageSize', '100')),
			'sortBy' => $this->sanitizeSortField($this->config->getUserValue($uid, Application::APP_ID, 'sortBy', 'date_taken')),
			'sortDir' => $this->sanitizeSortDirection($this->config->getUserValue($uid, Application::APP_ID, 'sortDir', 'desc')),
		];
	}

	public function savePersonalSettings(string $uid, array $settings): void {
		$this->config->setUserValue($uid, Application::APP_ID, 'displayMode', $this->sanitizeDisplayMode((string)$settings['displayMode']));
		$this->config->setUserValue($uid, Application::APP_ID, 'pageSize', (string)max(1, (int)$settings['pageSize']));
		$this->config->setUserValue($uid, Application::APP_ID, 'sortBy', $this->sanitizeSortField((string)$settings['sortBy']));
		$this->config->setUserValue($uid, Application::APP_ID, 'sortDir', $this->sanitizeSortDirection((string)$settings['sortDir']));
	}

	public function getEffectiveSettings(string $uid): array {
		$admin = $this->getAdminSettings();
		$personal = $this->getPersonalSettings($uid);
		return [
			'displayMode' => $personal['displayMode'] ?: $admin['defaultDisplayMode'],
			'pageSize' => min($personal['pageSize'] ?: $admin['defaultPageSize'], $admin['maxPageSize']),
			'maxPageSize' => $admin['maxPageSize'],
			'sortBy' => $personal['sortBy'] ?: $admin['defaultSortBy'],
			'sortDir' => $personal['sortDir'] ?: $admin['defaultSortDir'],
			'availableDisplayModes' => self::DISPLAY_MODES,
			'availableSortFields' => self::SORT_FIELDS,
			'availableSortDirections' => self::SORT_DIRECTIONS,
		];
	}

	private function sanitizeDisplayMode(string $value): string { return in_array($value, self::DISPLAY_MODES, true) ? $value : 'pagination'; }
	private function sanitizeSortField(string $value): string { return in_array($value, self::SORT_FIELDS, true) ? $value : 'date_taken'; }
	private function sanitizeSortDirection(string $value): string { return in_array($value, self::SORT_DIRECTIONS, true) ? $value : 'desc'; }
}
