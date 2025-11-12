<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('book')) {
            Schema::create('book', function (Blueprint $table) {
                // primary key named book_id as requested
                $table->id('book_id');

                // foreign key referencing `user.id`
                $table->unsignedBigInteger('user_id');

                // book name
                $table->string('book_name', 255);

                // define foreign key constraint
                $table->foreign('user_id')
                      ->references('id')
                      ->on('user')
                      ->onDelete('cascade');

                // no timestamps by design; model will not expect them
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book');
    }
};
