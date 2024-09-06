<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class GenerateMigrationFromModel extends Command
{
    protected $signature = 'generate:migration {model}';
    protected $description = 'Generate migration from model configuration, handle field type changes, and add new columns';

    private $defaultMigrationSchema = [
        'type' => 'string'
    ];

    private $schema_types = [
        'int' => 'integer',
        'varchar' => 'string',
        'text' => 'text',
        'json' => 'json',
        'bigint' => 'unsignedBigInteger'
    ];

    public function handle()
    {
        $modelName = $this->argument('model');
        $modelClass = "App\\Models\\$modelName";

        if (!class_exists($modelClass)) {
            $this->error("Model $modelClass does not exist.");
            return;
        }

        // Get migration schema attributes from the model
        $model = new $modelClass;
        $migrationSchema = $model->migrationSchema ?? [];
        $fillable = $model->fillable ?? [];
        $relationships = $model->relationships ?? [];

        if (empty($fillable)) {
            $this->error('No migration schema found in the model.');
            return;
        }

        // Determine the table name based on the model name
        $tableName = Str::plural(Str::snake($modelName));

        // Check if the table exists
        if (!Schema::hasTable($tableName)) {
            // If the table doesn't exist, create a full migration for it
            $this->generateFullMigration($modelName, $fillable, $migrationSchema, $tableName);
        } else {
            // If the table exists, generate a migration only for the new or changed columns
            $this->generateNewOrChangedColumnsMigration($modelName, $fillable, $migrationSchema, $tableName);
        }
        sleep(1);
        $this->createRelationships($modelName, $tableName, $relationships);
    }

    protected function generateFullMigration($modelName, $fillable, $migrationSchema, $tableName)
    {
        // Generate a migration for a new table
        $className = Str::studly(Str::plural(Str::snake($modelName)));
        $migrationName = "create_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $columns = "";
        foreach ($fillable as $field) {
            $details = $migrationSchema[$field] ?? $this->defaultMigrationSchema;
            $columns .= $this->generateColumn($field, $details);
        }


        $migrationContent = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Create{$className}Table extends Migration
{
    public function up()
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            $columns
            \$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('{$tableName}');
    }
}
PHP;

        // Save migration file
        File::put($fileName, $migrationContent);
        $this->info("Migration created: {$fileName}");
    }

    protected function generateNewOrChangedColumnsMigration($modelName, $fillable, $migrationSchema, $tableName)
    {
        // Get existing columns from the database using Schema::getColumnListing()
        $existingColumns = Schema::getColumnListing($tableName);
        $newColumns = [];
        $changedColumns = [];

        // Get column types by retrieving the column definitions
        $columnsDetails = Schema::getColumns($tableName);


        // Check for new or changed columns
        foreach ($fillable as $field) {
            $details = $migrationSchema[$field] ?? $this->defaultMigrationSchema;
            if (!in_array($field, $existingColumns)) {
                // New column
                $newColumns[$field] = $details;
            } else {
                // Existing column, check for type changes
                $currentColumn = current(array_filter($columnsDetails, function ($column) use ($field) {
                    return $column['name'] === $field;
                }));

                if ($this->checkChangedColumn($currentColumn, $details)) {
                    $changedColumns[$field] = $details;
                }
            }
        }

       

        if (empty($newColumns) && empty($changedColumns)) {
            $this->info("No new or changed columns to add to the {$tableName} table.");
            return;
        }

        // Generate a migration for the new or changed columns
        $className = Str::studly(Str::plural(Str::snake($modelName)));
        $migrationName = "update_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $columns = "";

        foreach ($newColumns as $field => $details) {
            $columns .= $this->generateColumn($field, $details);
        }

        foreach ($changedColumns as $field => $details) {
            $columns .= $this->modifyColumn($field, $details);
        }


        

        $migrationContent = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Update{$className}Table extends Migration
{
    public function up()
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            $columns
        });
    }

    public function down()
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            // Add code here to revert changes if necessary
        });
    }
}
PHP;

        // Save migration file
        File::put($fileName, $migrationContent);
        $this->info("Migration created for new or changed columns: {$fileName}");
    }

    protected function generateColumn($field, $details)
    {
        $type = $details['type'] ?? 'string'; // Default to string if type is not defined
        $length = $details['length'] ?? null;
        $nullable = $details['nullable'] ?? false;

        $return = "";

        switch ($type) {
            case 'string':
                $return = $length ? "\$table->string('{$field}', {$length})" : "\$table->string('{$field}')";
            case 'text':
                $return = "\$table->text('{$field}')";
            case 'integer':
                $return = "\$table->integer('{$field}')";
            default:
                $return = "\$table->{$type}('{$field}')";
        }

        if ($nullable) {
            $return .= "->nullable()";
        }


        return $return . ";\n";
    }

    protected function createRelationships($modelName, $tableName, $relationships)
    {

        if (empty($relationships)) {
            $this->info("No relationship changes for the {$tableName} table.");
            return;
        }

        // Generate a migration for a new table
        $className = Str::studly(Str::plural(Str::snake($modelName)));
        $migrationName = "add_foreign_keys_for_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $foreignKeys = "";
        foreach ($relationships as $foreignKey => $relationshipDetails) {
            if(!$this->foreignKeyExists($foreignKey, $tableName)){
                $foreignKeys .= $this->generateForeignKey($foreignKey, $relationshipDetails);
            }
        }

        if(empty($foreignKeys)){
            $this->info("No relationship changes for the {$tableName} table.");
            return ;
        }

        $migrationContent = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysFor{$className}Table extends Migration
{
    public function up()
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            $foreignKeys
        });
    }

    public function down()
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            // Add code here to revert changes if necessary
        });
    }
}
PHP;

        // Save migration file
        File::put($fileName, $migrationContent);
        $this->info("Migration created: {$fileName}");
    }


    private function foreignKeyExists($foreignKey, $tableName){

        $exists = false;

        $indexes = Schema::getIndexes($tableName);
        foreach($indexes as $index){
            $exists = current(array_filter($index["columns"], function ($column) use ($foreignKey) {
                return $column === $foreignKey;
            }));
        }
        return $exists;
    }

    protected function modifyColumn($field, $details)
    {
        $type = $details['type'] ?? 'string'; // Default to string if type is not defined
        $length = $details['length'] ?? null;
        $nullable = $details['nullable'] ?? false;

        $return = "";

        switch ($type) {
            case 'string':
                $return = $length ? "\$table->string('{$field}', {$length})" : "\$table->string('{$field}')";
            case 'text':
                $return = "\$table->text('{$field}')";
            case 'integer':
                $return = "\$table->integer('{$field}')";
            default:
                $return = "\$table->{$type}('{$field}')";
        }

        if ($nullable) {
            $return .= "->nullable()";
        }


        return $return . "->change();\n";
    }

    protected function generateForeignKey($field, $relationship)
    {
        $relatedTable = $relationship['table'];
        $relatedField = $relationship['field'];
        $onDelete = $relationship['onDelete'] ?? 'restrict';

        return "\$table->foreign('{$field}')->references('{$relatedField}')->on('{$relatedTable}')->onDelete('{$onDelete}');\n";
    }

    private function checkChangedColumn($currentColumn, $newColumn)
    {

        $changed = false;
        $existingType = $this->parseTypeName($currentColumn['type_name']);
        $newType = $newColumn['type'] ?? 'string';

        $existingNullable = $currentColumn['nullable'];
        $newNullable = $newColumn['nullable'] ?? false;

        if ($existingType !== $newType) {
            $changed = true;
        }

        if ($existingNullable !== $newNullable) {
            $changed = true;
        }

        return $changed;
    }

    private function parseTypeName($schema_name)
    {
        return $this->schema_types[$schema_name] ?? $schema_name;
    }
}
