    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up()
        {
            if (!Schema::hasTable('products')) {
                Schema::create('products', function (Blueprint $table) {
                    $table->id();
                    if (!Schema::hasColumn('products', 'name')) {
                        $table->string('name')->nullable();
                    } else {
                        $table->string('name')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'description')) {
                        $table->text('description')->nullable();
                    } else {
                        $table->text('description')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'description2')) {
                        $table->text('description2')->nullable();
                    } else {
                        $table->text('description2')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'price')) {
                        $table->integer('price')->nullable();
                    } else {
                        $table->integer('price')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'price2')) {
                        $table->json('price2')->nullable();
                    } else {
                        $table->json('price2')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'price3')) {
                        $table->string('price3');
                    } else {
                        $table->string('price3')->change();
                    }


                    $table->timestamps();
                    $table->softDeletes();
                });
            }else{
                Schema::table('products', function (Blueprint $table) {
                    $table->id()->change();
                    if (!Schema::hasColumn('products', 'name')) {
                        $table->string('name')->nullable();
                    } else {
                        $table->string('name')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'description')) {
                        $table->text('description')->nullable();
                    } else {
                        $table->text('description')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'description2')) {
                        $table->text('description2')->nullable();
                    } else {
                        $table->text('description2')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'price')) {
                        $table->integer('price')->nullable();
                    } else {
                        $table->integer('price')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'price2')) {
                        $table->json('price2')->nullable();
                    } else {
                        $table->json('price2')->nullable()->change();
                    }

                    if (!Schema::hasColumn('products', 'price3')) {
                        $table->string('price3');
                    } else {
                        $table->string('price3')->change();
                    }


                    if (!Schema::hasColumn('products', 'created_at')) {
                        $table->timestamps();
                    }
                    if (!Schema::hasColumn('products', 'deleted_at')) {
                        $table->softDeletes();
                    }
                });
            }
        }

        public function down()
        {
            Schema::dropIfExists('products');
        }
    };