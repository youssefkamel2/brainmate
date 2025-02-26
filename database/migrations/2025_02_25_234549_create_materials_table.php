<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialsTable extends Migration
{
    public function up()
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Name of the material
            $table->string('media'); // Path to the file
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade'); // Linked team
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade'); // User who uploaded the material
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('materials');
    }
}