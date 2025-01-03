<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskNotesTable extends Migration
{
    public function up()
    {
        Schema::create('task_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade'); // Linked task
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Creator of the note
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_notes');
    }
}
