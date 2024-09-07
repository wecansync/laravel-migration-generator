    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up()
        {
            if (!Schema::hasTable('products_tags')) {
                Schema::create('products_tags', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->constrained()->onDelete('cascade');
                    $table->foreignId('tag_id')->constrained()->onDelete('cascade');
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        public function down()
        {
            Schema::dropIfExists('products_tags');
        }
    };