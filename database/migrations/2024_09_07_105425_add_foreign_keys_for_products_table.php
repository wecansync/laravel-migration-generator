    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up()
        {
            Schema::table('products', function (Blueprint $table) {

                    if (!Schema::hasColumn('products', 'category_id')) {
                        $table->unsignedBigInteger('category_id')->nullable();
                    } else {
                        $table->unsignedBigInteger('category_id')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'brand_id')) {
                        $table->unsignedBigInteger('brand_id')->nullable();
                    } else {
                        $table->unsignedBigInteger('brand_id')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'group_id')) {
                        $table->unsignedBigInteger('group_id')->nullable();
                    } else {
                        $table->unsignedBigInteger('group_id')->nullable()->change();
                    }


                    if($this->foreignKeyExists('category_id', 'products')){
                        $table->dropForeign(['category_id']);
                    }
                    $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
                    if($this->foreignKeyExists('brand_id', 'products')){
                        $table->dropForeign(['brand_id']);
                    }
                    $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
                    if($this->foreignKeyExists('group_id', 'products')){
                        $table->dropForeign(['group_id']);
                    }
                    $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');

            });
        }

        public function down()
        {
            Schema::table('products', function (Blueprint $table) {
                // Add code here to revert changes if necessary
            });
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


    };