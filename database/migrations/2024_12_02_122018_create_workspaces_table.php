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
            $table->json('images')->nullable(); // Store multiple image paths as JSON
            $table->string('location');
            $table->string('map_url')->nullable(); // Google Maps URL
            $table->string('phone')->nullable(); // Phone number
            $table->string('amenities')->nullable(); // Amenities as a string (e.g., "wifi . coffee . meeting room")
            $table->decimal('rating', 3, 1)->default(0.0); // Rating (e.g., 4.9)
            $table->decimal('price', 10, 2); // Price
            $table->text('description')->nullable(); // Description
            $table->boolean('wifi')->default(false); // Wifi availability
            $table->boolean('coffee')->default(false); // Coffee availability
            $table->boolean('meetingroom')->default(false); // Meeting room availability
            $table->boolean('silentroom')->default(false); // Silent room availability
            $table->boolean('amusement')->default(false); // Amusement availability
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
