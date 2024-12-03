<?php

namespace WeCanSync\MigrationGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use ReflectionMethod;

class GenerateMigrationFromModel extends Command
{
    protected $signature = 'generate:migration {model}';
    protected $description = 'Generate migration from model configuration, handle field type changes, and add new columns';


    private mixed  $model;
    private string  $table_name = "";

    private string $models_directory = "App\\Models\\";

    private array $default_migration_schema = [
        'type' => 'string'
    ];

    private array $schema_types = [
        'int' => 'integer',
        'varchar' => 'string',
        'tinyint' => 'boolean',
        'text' => 'text',
        'json' => 'json',
        'bigint' => 'unsignedBigInteger'
    ];

    public function handle()
    {
        $modelName = $this->argument('model');
        $modelClass = $this->models_directory . $modelName;

        if (!class_exists($modelClass)) {
            $this->error("Model $modelClass does not exist.");
            return;
        }

        // Get migration schema attributes from the model
        $this->model = new $modelClass;
        $this->table_name = $this->model->getTable();
        $migration_schema = $this->model->migration_schema ?? [];
        $relationships = $this->model->relationships ?? [];

        if (empty($migration_schema)) {

            $this->error('No migration schema found in the model.');
            return;
        }


        // Check if the table exists
        if (!$this->tableExists($this->table_name)) {
            // If the table doesn't exist, create a full migration for it
            $this->generateFullMigration($migration_schema);
        } else {
            // If the table exists, generate a migration only for the new or changed columns
            $this->generateNewOrChangedColumnsMigration($migration_schema);
        }
        sleep(1);
        $this->createRelationships($relationships);
        $this->createPivotTables($relationships);
    }

    protected function generateFullMigration($migration_schema)
    {
        // Generate a migration for a new table
        $migrationName = "create_{$this->table_name}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $columns = "";
        foreach ($migration_schema as $field=>$details) {
            $details = $details ?? $this->default_migration_schema;
            $columns .= $this->generateColumn($field, $details);
        }


        $migrationContent =
            <<<PHP
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up()
                {
                    if (!Schema::hasTable('{$this->table_name}')) {
                        Schema::create('{$this->table_name}', function (Blueprint \$table) {
                            \$table->id();\n$columns
                            \$table->timestamps();
                            \$table->softDeletes();
                        });
                    }else{
                        Schema::table('{$this->table_name}', function (Blueprint \$table) {
                            \$table->id()->change();\n$columns
                            if (!Schema::hasColumn('{$this->table_name}', 'created_at')) {
                                \$table->timestamps();
                            }
                            if (!Schema::hasColumn('{$this->table_name}', 'deleted_at')) {
                                \$table->softDeletes();
                            }
                        });
                    }
                }

                public function down()
                {
                    Schema::dropIfExists('{$this->table_name}');
                }
            };
        PHP;

        // Save migration file
        File::put($fileName, $migrationContent);
        $this->info("Migration created: {$fileName}");
    }

    protected function generateNewOrChangedColumnsMigration($migration_schema)
    {
        // Get existing columns from the database using Schema::getColumnListing()
        $existingColumns = Schema::getColumnListing($this->table_name);
        $newColumns = [];
        $changedColumns = [];

        // Get column types by retrieving the column definitions
        $columnsDetails = Schema::getColumns($this->table_name);


        // Check for new or changed columns
        foreach ($migration_schema as $field=>$details) {

            $details = $details ?? $this->default_migration_schema;

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
            $this->info("No new or changed columns to add to the {$this->table_name} table.");
            return;
        }

        // Generate a migration for the new or changed columns
        $migrationName = "update_{$this->table_name}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $columns = "";

        foreach ($newColumns as $field => $details) {
            $columns .= $this->generateColumn($field, $details);
        }

        foreach ($changedColumns as $field => $details) {
            $columns .= $this->generateColumn($field, $details);
        }

        $migrationContent =
            <<<PHP
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up()
                {
                    Schema::table('{$this->table_name}', function (Blueprint \$table) {\n$columns
                    });
                }

                public function down()
                {
                    Schema::table('{$this->table_name}', function (Blueprint \$table) {
                        // Add code here to revert changes if necessary
                    });
                }
            };
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
        $default = $details['default'] ?? null;

        $row = "";

        switch ($type) {
            case 'string':
                $row = $length ? "\$table->string('{$field}', {$length})" : "\$table->string('{$field}')";
                break;
            case 'text':
                $row = "\$table->text('{$field}')";
                break;
            case 'integer':
                $row = "\$table->integer('{$field}')";
                break;
            default:
                $row = "\$table->{$type}('{$field}')";
        }

        if ($nullable) {
            $row .= "->nullable()";
        }

        if ($default) {
            if(is_int($default)){
                $row .= "->default({$default})";
            }else{
                $row .= "->default('{$default}')";
            }
        }

        $return = 
            <<<PHP
                                if (!Schema::hasColumn('{$this->table_name}', '{$field}')) {
                                    {$row};
                                } else {
                                    {$row}->change();
                                }\n\n
            PHP;


        return $return;
    }

    protected function createRelationships($relationships)
    {

        if (empty($relationships)) {
            $this->info("No relationship changes for the {$this->table_name} table.");
            return;
        }

        // Generate a migration for a new table
        $migrationName = "add_foreign_keys_for_{$this->table_name}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $updatedColumns = "";
        $foreignKeys = "";
        foreach ($relationships as $relationshipDetails) {
            if ($relationshipDetails['type'] === 'manyToMany') {
                continue;
            }
            $foreignKey = $relationshipDetails['column'];
            if($relationshipDetails['type'] === 'foreignId'){
                if (!$this->foreignKeyExists($foreignKey, $this->table_name)) {
                    $foreignKeys .= $this->generateForeignId($relationshipDetails);
                }
                continue;
            }
            $updatedColumns .= $this->checkForeignKeyColumn($relationshipDetails);
            if (!$this->foreignKeyExists($foreignKey, $this->table_name)) {
                $foreignKeys .= $this->generateForeignKey($relationshipDetails);
            }
        }

        if (empty($foreignKeys)) {
            $this->info("No relationship changes for the {$this->table_name} table.");
            return;
        }

        $method = $this->getMethodCode('foreignKeyExists');

        $migrationContent =
            <<<PHP
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up()
                {
                    Schema::table('{$this->table_name}', function (Blueprint \$table) {\n\n$updatedColumns\n$foreignKeys
                    });
                }

                public function down()
                {
                    Schema::table('{$this->table_name}', function (Blueprint \$table) {
                        // Add code here to revert changes if necessary
                    });
                }\n
            $method
            };
        PHP;

        // Save migration file
        File::put($fileName, $migrationContent);
        $this->info("Migration created: {$fileName}");
    }

    private function checkForeignKeyColumn($relationshipDetails){

        $toUpdate = '';
        $foreignKey = $relationshipDetails['column'];
        if(!$this->columnExists($foreignKey)){
            // create column
            $toUpdate .= $this->createForeignKeyColumn($foreignKey, $relationshipDetails);
        }else{
            if(!$this->isForeignKeyConstraintValid($foreignKey)){
                // fix configuration
                $toUpdate .= $this->fixForeignKeyConstraints($foreignKey, $relationshipDetails);
            }
        }
        return $toUpdate;
    }

    private function isForeignKeyConstraintValid($foreignKey){

        $isValid = true;
        $columnsDetails = Schema::getColumns($this->table_name);
        // Existing column, check for type changes
        $currentColumn = current(array_filter($columnsDetails, function ($column) use ($foreignKey) {
            return $column['name'] === $foreignKey;
        }));

        $existingType = $this->parseTypeName($currentColumn['type_name']);

        if ($existingType !== 'unsignedBigInteger') {
            $isValid = false;
        }
        return $isValid;
    }

    private function createForeignKeyColumn($foreignKey, $relationshipDetails){
       
        $nullable = false;
        if(isset($relationshipDetails['onDelete']) && $relationshipDetails['onDelete'] == 'set null'){
            $nullable = true;
        }
        $details = [
            'type' => 'unsignedBigInteger',
            'nullable' => $nullable
        ];
        $toAdd = $this->generateColumn($foreignKey, $details);

        return $toAdd;

    }

    private function fixForeignKeyConstraints($foreignKey, $relationshipDetails){

        $nullable = false;
        if(isset($relationshipDetails['onDelete']) && $relationshipDetails['onDelete'] == 'set null'){
            $nullable = true;
        }
        $details = [
            'type' => 'unsignedBigInteger',
            'nullable' => $nullable
        ];
        $toAdd = $this->generateColumn($foreignKey, $details);

        return $toAdd;
    }

    protected function createPivotTables($relationships)
    {
        foreach ($relationships as $details) {
            if ($details['type'] === 'manyToMany') {
                $table1 = $this->table_name;
                $table2 = (new $details['model'])->getTable();
                $pivotTableName = $this->getPivotTableName($table1, $table2);
                if (!$this->tableExists($pivotTableName)) {
                    $this->createPivotTableMigration($pivotTableName, $details);
                }else{
                    $this->info("Pivot table {$pivotTableName} already exists.");
                }
            }
        }
    }

    protected function getPivotTableName($table1, $table2)
    {
        // Alphabetically sort tables to ensure consistent pivot table naming
        $tables = [Str::singular($table1), Str::singular($table2)];
        sort($tables);
        return Str::snake(implode('_', $tables));
    }

    protected function createPivotTableMigration($pivotTableName, $details)
    {
        $table1 = Str::snake($this->table_name);
        $table2 = Str::snake($table2 = (new $details['model'])->getTable());

        $column1 = Str::singular($table1);
        $column2 = Str::singular($table2);
        
        $migrationName = "create_{$pivotTableName}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $migrationContent = 
        <<<PHP
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up()
                {
                    if (!Schema::hasTable('{$pivotTableName}')) {
                        Schema::create('{$pivotTableName}', function (Blueprint \$table) {
                            \$table->id();
                            \$table->foreignId('{$column1}_id')->constrained()->onDelete('cascade');
                            \$table->foreignId('{$column2}_id')->constrained()->onDelete('cascade');
                            \$table->timestamps();
                            \$table->softDeletes();
                        });
                    }
                }

                public function down()
                {
                    Schema::dropIfExists('{$pivotTableName}');
                }
            };
        PHP;

        // Save migration file
        File::put($fileName, $migrationContent);
        $this->info("Pivot table migration created: {$fileName}");
    }

    // get_function_code.php
    private function getMethodCode($methodName) {
        $reflector = new ReflectionMethod(__CLASS__, $methodName);
        $filename = $reflector->getFileName();
        $startLine = $reflector->getStartLine() - 1; // Adjust for 0-based index
        $endLine = $reflector->getEndLine();
        $lines = file($filename);
        $methodCode = implode('', array_slice($lines, $startLine, $endLine - $startLine + 1));

        return $methodCode;
    }


    private function foreignKeyExists($foreignKey, $table_name)
    {

        $exists = false;

        $indexes = Schema::getIndexes($table_name);
        foreach ($indexes as $index) {
            if($exists){
                continue;
            }
            $exists = current(array_filter($index["columns"], function ($column) use ($foreignKey) {
                return $column === $foreignKey;
            }));
        }
        return $exists;
    }

    private function tableExists($table)
    {
        return Schema::hasTable($table);
    }

    private function columnExists($column)
    {
        $existingColumns = Schema::getColumnListing($this->table_name);

        return in_array($column, $existingColumns);
    }


    protected function generateForeignKey($relationship)
    {
        $field = $relationship['column'];
        $relatedModel = $relationship['model'];
        $relatedTable = (new $relatedModel)->getTable();
        $relatedField = $relationship['field'];
        $onDelete = $relationship['onDelete'] ?? 'cascade';

        $return = 
        <<<PHP
                            if(\$this->foreignKeyExists('{$field}', '{$this->table_name}')){
                                \$table->dropForeign(['{$field}']);
                            }
                            \$table->foreign('{$field}')->references('{$relatedField}')->on('{$relatedTable}')->onDelete('{$onDelete}');\n\n
        PHP;

        return $return;
    }
    protected function generateForeignId($relationship)
    {
        $field = $relationship['column'];
        $relatedModel = $relationship['model'];
        $relatedTable = (new $relatedModel)->getTable();
        $onDelete = $relationship['onDelete'] ?? 'cascade';
        $onDeleteBehaviour = $onDelete == 'set null' ? '->nullOnDelete()' : '->cascadeOnDelete()';
        $foreignModel = $relatedModel;
        $return = 
        <<<PHP
                            if(\$this->foreignKeyExists('{$field}', '{$this->table_name}')){
                                \$table->dropForeignIdFor('{$foreignModel}');
                                \$table->dropColumn('{$field}');
                            }
                            \$table->foreignId('{$field}')->nullable()->constrained('{$relatedTable}'){$onDeleteBehaviour};\n\n
        PHP;

        return $return;
    }

    private function checkChangedColumn($currentColumn, $newColumn)
    {
        
        $changed = false;
        $existingType = $this->parseTypeName($currentColumn['type_name']);
        $newType = $newColumn['type'] ?? 'string';

       

        $existingNullable = $currentColumn['nullable'];
        $newNullable = $newColumn['nullable'] ?? false;

        $existingDefault = $currentColumn['default'];
        $newDefault = $newColumn['default'] ?? null;


        if ($existingType != $newType) {
            $changed = true;
        }

        if ($existingNullable != $newNullable) {
            $changed = true;
        }

        if ($existingDefault != $newDefault) {
            $changed = true;
        }

        return $changed;
    }

    private function parseTypeName($schema_name)
    {
        return $this->schema_types[$schema_name] ?? $schema_name;
    }
}
