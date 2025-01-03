<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskMembersTable extends Migration
{
    public function up()
    {
        Schema::create('task_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade'); // Linked task
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade'); // Linked team
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade'); // Linked project
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User member
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_members');
    }
}
