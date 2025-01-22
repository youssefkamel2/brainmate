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
            $table->text('images')->nullable(); // Changed from json to text
            $table->string('location');
            $table->string('map_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('amenities')->nullable();
            $table->decimal('rating', 3, 1)->default(0.0);
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->boolean('wifi')->default(false);
            $table->boolean('coffee')->default(false);
            $table->boolean('meetingroom')->default(false);
            $table->boolean('silentroom')->default(false);
            $table->boolean('amusement')->default(false);
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