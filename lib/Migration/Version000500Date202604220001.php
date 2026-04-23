<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000500Date202604220001 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('recentphotos_index')) {
			return $schema;
		}
		$table = $schema->getTable('recentphotos_index');
		if (!$table->hasColumn('date_taken')) {
			$table->addColumn('date_taken', 'bigint', ['unsigned' => true, 'notnull' => false, 'default' => null]);
			$table->addIndex(['user_id', 'date_taken'], 'recentphotos_user_datetaken_idx');
		}
		return $schema;
	}
}
