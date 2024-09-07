    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up()
        {
            if (!Schema::hasTable('groups')) {
                Schema::create('groups', function (Blueprint $table) {
                    $table->id();
                    if (!Schema::hasColumn('groups', 'name')) {
                        $table->string('name');
                    } else {
                        $table->string('name')->change();
                    }

                    if (!Schema::hasColumn('groups', 'description')) {
                        $table->string('description');
                    } else {
                        $table->string('description')->change();
                    }


                    $table->timestamps();
                    $table->softDeletes();
                });
            }else{
                Schema::table('groups', function (Blueprint $table) {
                    $table->id()->change();
                    if (!Schema::hasColumn('groups', 'name')) {
                        $table->string('name');
                    } else {
                        $table->string('name')->change();
                    }

                    if (!Schema::hasColumn('groups', 'description')) {
                        $table->string('description');
                    } else {
                        $table->string('description')->change();
                    }


                    if (!Schema::hasColumn('groups', 'created_at')) {
                        $table->timestamps();
                    }
                    if (!Schema::hasColumn('groups', 'deleted_at')) {
                        $table->softDeletes();
                    }
                });
            }
        }

        public function down()
        {
            Schema::dropIfExists('groups');
        }
    };