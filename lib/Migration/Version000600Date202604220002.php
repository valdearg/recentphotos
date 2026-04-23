<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000600Date202604220002 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('recentphotos_index')) {
			return $schema;
		}

		$table = $schema->getTable('recentphotos_index');

		if (!$table->hasColumn('media_type')) {
			$table->addColumn('media_type', 'string', [
				'length' => 16,
				'notnull' => true,
				'default' => 'image',
			]);
			$table->addIndex(['user_id', 'media_type', 'date_taken'], 'recentphotos_user_mediatype_datetaken_idx');
			$table->addIndex(['user_id', 'media_type', 'created'], 'recentphotos_user_mediatype_created_idx');
		}

		return $schema;
	}
}
