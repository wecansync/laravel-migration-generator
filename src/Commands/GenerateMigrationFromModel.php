<?php

namespace FDS\MigrationGenerator\Commands;

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
        $relationships = $model->relationships ?? [];

        if (empty($migrationSchema)) {

            $this->error('No migration schema found in the model.');
            return;
        }

        // Determine the table name based on the model name
        $tableName = Str::plural(Str::snake($modelName));

        // Check if the table exists
        if (!$this->tableExists($tableName)) {
            // If the table doesn't exist, create a full migration for it
            $this->generateFullMigration($modelName, $migrationSchema, $tableName);
        } else {
            // If the table exists, generate a migration only for the new or changed columns
            $this->generateNewOrChangedColumnsMigration($modelName, $migrationSchema, $tableName);
        }
        sleep(1);
        $this->createRelationships($modelName, $tableName, $relationships);
        $this->createPivotTables($relationships);
    }

    protected function generateFullMigration($modelName, $migrationSchema, $tableName)
    {
        // Generate a migration for a new table
        $className = Str::studly(Str::plural(Str::snake($modelName)));
        $migrationName = "create_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = database_path("migrations/{$timestamp}_{$migrationName}.php");

        $columns = "";
        foreach ($migrationSchema as $field=>$details) {
            $details = $details ?? $this->defaultMigrationSchema;
            $columns .= $this->generateColumn($tableName, $field, $details);
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
                    if (!Schema::hasTable('{$tableName}')) {
                        Schema::create('{$tableName}', function (Blueprint \$table) {
                            \$table->id();\n$columns
                            \$table->timestamps();
                            \$table->softDeletes();
                        });
                    }else{
                        Schema::table('{$tableName}', function (Blueprint \$table) {
                            \$table->id()->change();\n$columns
                            if (!Schema::hasColumn('{$tableName}', 'created_at')) {
                                \$table->timestamps();
                            }
                            if (!Schema::hasColumn('{$tableName}', 'deleted_at')) {
                                \$table->softDeletes();
                            }
                        });
                    }
                }

                public function down()
                {
                    Schema::dropIfExists('{$tableName}');
                }
            };
        PHP;

        // Save migration file
        File::put($fileName, $migrationContent);
        $this->info("Migration created: {$fileName}");
    }

    protected function generateNewOrChangedColumnsMigration($modelName, $migrationSchema, $tableName)
    {
        // Get existing columns from the database using Schema::getColumnListing()
        $existingColumns = Schema::getColumnListing($tableName);
        $newColumns = [];
        $changedColumns = [];

        // Get column types by retrieving the column definitions
        $columnsDetails = Schema::getColumns($tableName);


        // Check for new or changed columns
        foreach ($migrationSchema as $field=>$details) {

            $details = $details ?? $this->defaultMigrationSchema;

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
            $columns .= $this->generateColumn($tableName, $field, $details);
        }

        foreach ($changedColumns as $field => $details) {
            $columns .= $this->generateColumn($tableName, $field, $details);
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
                    Schema::table('{$tableName}', function (Blueprint \$table) {\n$columns
                    });
                }

                public function down()
                {
                    Schema::table('{$tableName}', function (Blueprint \$table) {
                        // Add code here to revert changes if necessary
                    });
                }
            };
        PHP;

        // Save migration file
        File::put($fileName, $migrationContent);
        $this->info("Migration created for new or changed columns: {$fileName}");
    }

    protected function generateColumn($tableName, $field, $details)
    {
        $type = $details['type'] ?? 'string'; // Default to string if type is not defined
        $length = $details['length'] ?? null;
        $nullable = $details['nullable'] ?? false;

        $row = "";

        switch ($type) {
            case 'string':
                $row = $length ? "\$table->string('{$field}', {$length})" : "\$table->string('{$field}')";
            case 'text':
                $row = "\$table->text('{$field}')";
            case 'integer':
                $row = "\$table->integer('{$field}')";
            default:
                $row = "\$table->{$type}('{$field}')";
        }

        if ($nullable) {
            $row .= "->nullable()";
        }

        $return = 
            <<<PHP
                                if (!Schema::hasColumn('{$tableName}', '{$field}')) {
                                    {$row};
                                } else {
                                    {$row}->change();
                                }\n\n
            PHP;


        return $return;
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

        $updatedColumns = "";
        $foreignKeys = "";
        foreach ($relationships as $relationshipDetails) {
            if ($relationshipDetails['type'] === 'manyToMany') {
                continue;
            }
            $foreignKey = $relationshipDetails['column'];
            $updatedColumns .= $this->checkForeignKeyColumn($modelName, $tableName, $relationshipDetails);
            if (!$this->foreignKeyExists($foreignKey, $tableName)) {
                $foreignKeys .= $this->generateForeignKey($tableName, $relationshipDetails);
            }
        }

        if (empty($foreignKeys)) {
            $this->info("No relationship changes for the {$tableName} table.");
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
                    Schema::table('{$tableName}', function (Blueprint \$table) {\n\n$updatedColumns\n$foreignKeys
                    });
                }

                public function down()
                {
                    Schema::table('{$tableName}', function (Blueprint \$table) {
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

    private function checkForeignKeyColumn($modelName, $tableName, $relationshipDetails){

        $toUpdate = '';
        $foreignKey = $relationshipDetails['column'];
        if(!$this->columnExists($tableName, $foreignKey)){
            // create column
            $toUpdate .= $this->createForeignKeyColumn($tableName, $foreignKey, $relationshipDetails);
        }else{
            if(!$this->isForeignKeyConstraintsValid($tableName, $foreignKey)){
                // fix configuration
                $toUpdate .= $this->fixForeignKeyConstraints($tableName, $foreignKey, $relationshipDetails);
            }
        }
        return $toUpdate;
    }

    private function isForeignKeyConstraintsValid($tableName, $foreignKey){

        $isValid = true;
        $columnsDetails = Schema::getColumns($tableName);
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

    private function createForeignKeyColumn($tableName, $foreignKey, $relationshipDetails){
       
        $nullable = false;
        if(isset($relationshipDetails['onDelete']) && $relationshipDetails['onDelete'] == 'set null'){
            $nullable = true;
        }
        $details = [
            'type' => 'unsignedBigInteger',
            'nullable' => $nullable
        ];
        $toAdd = $this->generateColumn($tableName, $foreignKey, $details);

        return $toAdd;

    }

    private function fixForeignKeyConstraints($tableName, $foreignKey, $relationshipDetails){

        $nullable = false;
        if(isset($relationshipDetails['onDelete']) && $relationshipDetails['onDelete'] == 'set null'){
            $nullable = true;
        }
        $details = [
            'type' => 'unsignedBigInteger',
            'nullable' => $nullable
        ];
        $toAdd = $this->generateColumn($tableName, $foreignKey, $details);

        return $toAdd;
    }

    protected function createPivotTables($relationships)
    {
        foreach ($relationships as $details) {
            if ($details['type'] === 'manyToMany') {
                $pivotTableName = $this->getPivotTableName($details['table1'], $details['table2']);
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
        $tables = [Str::snake($table1), Str::snake($table2)];
        sort($tables);
        return Str::plural(implode('_', $tables));
    }

    protected function createPivotTableMigration($pivotTableName, $details)
    {
        $table1 = Str::snake($details['table1']);
        $table2 = Str::snake($details['table2']);

        $column1 = Str::singular($table1);
        $column2 = Str::singular($table2);
        
        $className = Str::studly($pivotTableName);
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


    private function foreignKeyExists($foreignKey, $tableName)
    {

        $exists = false;

        $indexes = Schema::getIndexes($tableName);
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

    private function tableExists($tableName)
    {
        return Schema::hasTable($tableName);
    }

    private function columnExists($tableName, $column)
    {
        $existingColumns = Schema::getColumnListing($tableName);

        return in_array($column, $existingColumns);
    }


    protected function generateForeignKey($tableName, $relationship)
    {
        $field = $relationship['column'];
        $relatedTable = $relationship['table'];
        $relatedField = $relationship['field'];
        $onDelete = $relationship['onDelete'] ?? 'restrict';

        $return = 
        <<<PHP
                            if(\$this->foreignKeyExists('{$field}', '{$tableName}')){
                                \$table->dropForeign(['{$field}']);
                            }
                            \$table->foreign('{$field}')->references('{$relatedField}')->on('{$relatedTable}')->onDelete('{$onDelete}');\n
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
