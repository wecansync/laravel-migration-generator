<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
$table->text('description2')->nullable()->change();
$table->integer('price')->nullable()->change();

        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            // Add code here to revert changes if necessary
        });
    }
}