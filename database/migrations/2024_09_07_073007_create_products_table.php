    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    class CreateProductsTable extends Migration
    {
        public function up()
        {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
$table->text('description')->nullable();
$table->text('description2')->nullable();
$table->integer('price')->nullable();
$table->json('price2')->nullable();
$table->unsignedBigInteger('category_id')->nullable();
$table->unsignedBigInteger('brand_id')->nullable();

                $table->timestamps();
                $table->softDeletes();
            });
        }

        public function down()
        {
            Schema::dropIfExists('products');
        }
    }