    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up()
        {
            if (!Schema::hasTable('tags')) {
                Schema::create('tags', function (Blueprint $table) {
                    $table->id();
                    if (!Schema::hasColumn('tags', 'name')) {
                        $table->string('name');
                    } else {
                        $table->string('name')->change();
                    }

                    if (!Schema::hasColumn('tags', 'description')) {
                        $table->string('description');
                    } else {
                        $table->string('description')->change();
                    }


                    $table->timestamps();
                    $table->softDeletes();
                });
            }else{
                Schema::table('tags', function (Blueprint $table) {
                    $table->id()->change();
                    if (!Schema::hasColumn('tags', 'name')) {
                        $table->string('name');
                    } else {
                        $table->string('name')->change();
                    }

                    if (!Schema::hasColumn('tags', 'description')) {
                        $table->string('description');
                    } else {
                        $table->string('description')->change();
                    }


                    if (!Schema::hasColumn('tags', 'created_at')) {
                        $table->timestamps();
                    }
                    if (!Schema::hasColumn('tags', 'deleted_at')) {
                        $table->softDeletes();
                    }
                });
            }
        }

        public function down()
        {
            Schema::dropIfExists('tags');
        }
    };