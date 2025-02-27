<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User receiving the notification
            $table->text('message'); // Notification content
            $table->string('type')->default('info'); // Type of notification (e.g., info, success, warning, error)
            $table->boolean('read')->default(false); // Read/unread status
            $table->string('action_url')->nullable(); // URL for actionable notifications
            $table->json('metadata')->nullable(); // Additional data for the notification
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}