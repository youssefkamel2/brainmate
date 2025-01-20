<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRemindersTable extends Migration
{
    public function up()
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade'); // Nullable user_id for custom reminder
            $table->foreignId('task_id')->nullable()->constrained('tasks')->onDelete('cascade'); // Nullable task_id for normal reminder
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('cascade'); // Nullable user_id for custom reminder
            $table->datetime('reminder_time');
            $table->string('message');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reminders');
    }
}
