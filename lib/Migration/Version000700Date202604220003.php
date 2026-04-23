<?php

declare(strict_types=1);

namespace OCA\RecentPhotos\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000700Date202604220003 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('recentphotos_index')) {
            return $schema;
        }

        $table = $schema->getTable('recentphotos_index');

        if (!$table->hasColumn('last_seen_at')) {
            $table->addColumn('last_seen_at', 'bigint', [
                'unsigned' => true,
                'notnull' => false,
                'default' => null,
            ]);
            $table->addIndex(['user_id', 'last_seen_at'], 'recentphotos_user_lastseen_idx');
        }

        return $schema;
    }
}
