    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up()
        {
            if (!Schema::hasTable('categories')) {
                Schema::create('categories', function (Blueprint $table) {
                    $table->id();
                    if (!Schema::hasColumn('categories', 'name')) {
                        $table->string('name');
                    } else {
                        $table->string('name')->change();
                    }

                    if (!Schema::hasColumn('categories', 'description')) {
                        $table->string('description');
                    } else {
                        $table->string('description')->change();
                    }


                    $table->timestamps();
                    $table->softDeletes();
                });
            }else{
                Schema::table('categories', function (Blueprint $table) {
                    $table->id()->change();
                    if (!Schema::hasColumn('categories', 'name')) {
                        $table->string('name');
                    } else {
                        $table->string('name')->change();
                    }

                    if (!Schema::hasColumn('categories', 'description')) {
                        $table->string('description');
                    } else {
                        $table->string('description')->change();
                    }


                    if (!Schema::hasColumn('categories', 'created_at')) {
                        $table->timestamps();
                    }
                    if (!Schema::hasColumn('categories', 'deleted_at')) {
                        $table->softDeletes();
                    }
                });
            }
        }

        public function down()
        {
            Schema::dropIfExists('categories');
        }
    };