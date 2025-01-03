<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatsTable extends Migration
{
    public function up()
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade'); // Sender of the message
            $table->foreignId('receiver_id')->nullable()->constrained('users')->onDelete('cascade'); // Receiver of the message (null for group messages)
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade'); // Linked to a team for group chats
            $table->text('message'); // Chat message content
            $table->enum('type', ['text', 'file', 'image'])->default('text'); // Type of message
            $table->string('media')->nullable(); // Path to file/image if type is file/image
            $table->timestamps(); // Created and updated timestamps
        });
    }

    public function down()
    {
        Schema::dropIfExists('chats');
    }
}
