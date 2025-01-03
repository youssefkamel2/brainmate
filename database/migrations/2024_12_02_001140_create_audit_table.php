<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditTable extends Migration
{
    public function up()
    {
        Schema::create('audit', function (Blueprint $table) {
            $table->id();
            $table->string('table_name'); // The table that was modified
            $table->bigInteger('record_id'); // ID of the record that was modified
            $table->text('action'); // Type of action (create, update, delete)
            $table->json('old_values')->nullable(); // Previous values (before the change)
            $table->json('new_values')->nullable(); // New values (after the change)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // User who made the change
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit');
    }
}
