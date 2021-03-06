<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('shuffled_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_id');
            $table
                ->foreign('item_id')
                ->references('id')
                ->on('items');
            $table->json('crop');
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shuffled_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shuffled_item_id');
            $table->string('locale')->index();
            $table->json('filters');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shuffled_items_translations');
        Schema::dropIfExists('shuffled_items');
    }
};
