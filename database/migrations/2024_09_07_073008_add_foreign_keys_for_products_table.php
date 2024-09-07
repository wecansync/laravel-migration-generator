    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    class AddForeignKeysForProductsTable extends Migration
    {
        public function up()
        {
            Schema::table('products', function (Blueprint $table) {
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
$table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');

            });
        }

        public function down()
        {
            Schema::table('products', function (Blueprint $table) {
                // Add code here to revert changes if necessary
            });
        }
    }