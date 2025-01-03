<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkspacesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable(); // Path to workspace image
            $table->string('location');
            $table->decimal('rate', 2, 1)->default(0.0); // Average rating (e.g., 4.5)
            $table->decimal('price', 10, 2); // Price in dollars or another currency
            $table->string('phone_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('workspaces');
    }
}
