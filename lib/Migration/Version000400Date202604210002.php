<?php
declare(strict_types=1);

namespace OCA\RecentPhotos\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000400Date202604210002 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('recentphotos_index')) {
            $table = $schema->createTable('recentphotos_index');
            $table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('file_id', 'bigint', ['unsigned' => true, 'notnull' => true]);
            $table->addColumn('path', 'text', ['notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 400, 'notnull' => true]);
            $table->addColumn('mime', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('size', 'bigint', ['unsigned' => true, 'notnull' => true, 'default' => 0]);
            $table->addColumn('created', 'bigint', ['unsigned' => true, 'notnull' => true, 'default' => 0]);
            $table->addColumn('modified', 'bigint', ['unsigned' => true, 'notnull' => true, 'default' => 0]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['file_id'], 'recentphotos_index_fileid_uq');
            $table->addIndex(['user_id', 'created'], 'recentphotos_user_created_idx');
            $table->addIndex(['user_id', 'modified'], 'recentphotos_user_modified_idx');
            $table->addIndex(['user_id', 'name'], 'recentphotos_user_name_idx');
            $table->addIndex(['user_id', 'size'], 'recentphotos_user_size_idx');
        }

        return $schema;
    }
}
