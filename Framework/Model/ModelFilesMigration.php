<?php

namespace Framework\Model;

use Framework\Database\Migration;
use Framework\Database\Schema;

/**
 * ModelFilesMigration
 * Creates the model_files table required for the HasFiles trait.
 * Run this migration once to enable file attachments on all models.
 *
 * Usage:
 * (new \Framework\Model\ModelFilesMigration())->up();
 */
class ModelFilesMigration extends Migration
{
    protected $tableName = 'model_files';

    public function up()
    {
        $this->createTable($this->tableName, function (Schema $table) {
            $table->id();
            $table->string('model_type', 255);
            $table->integer('model_id');
            $table->string('collection', 100)->default('default');
            $table->string('disk', 50)->default('local');
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->integer('file_size')->default(0);
            $table->timestamp('created_at');
        });

        $this->raw("CREATE INDEX idx_model_files_model ON {$this->tableName} (model_type, model_id)");
        $this->raw("CREATE INDEX idx_model_files_collection ON {$this->tableName} (model_type, model_id, collection)");
    }

    public function down()
    {
        $this->dropTable($this->tableName);
    }

    public static function create()
    {
        (new self())->up();
    }

    public static function drop()
    {
        (new self())->down();
    }
}
