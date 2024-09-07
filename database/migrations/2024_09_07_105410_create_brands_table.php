    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up()
        {
            if (!Schema::hasTable('brands')) {
                Schema::create('brands', function (Blueprint $table) {
                    $table->id();
                    if (!Schema::hasColumn('brands', 'name')) {
                        $table->string('name');
                    } else {
                        $table->string('name')->change();
                    }

                    if (!Schema::hasColumn('brands', 'description')) {
                        $table->string('description');
                    } else {
                        $table->string('description')->change();
                    }


                    $table->timestamps();
                    $table->softDeletes();
                });
            }else{
                Schema::table('brands', function (Blueprint $table) {
                    $table->id()->change();
                    if (!Schema::hasColumn('brands', 'name')) {
                        $table->string('name');
                    } else {
                        $table->string('name')->change();
                    }

                    if (!Schema::hasColumn('brands', 'description')) {
                        $table->string('description');
                    } else {
                        $table->string('description')->change();
                    }


                    if (!Schema::hasColumn('brands', 'created_at')) {
                        $table->timestamps();
                    }
                    if (!Schema::hasColumn('brands', 'deleted_at')) {
                        $table->softDeletes();
                    }
                });
            }
        }

        public function down()
        {
            Schema::dropIfExists('brands');
        }
    };