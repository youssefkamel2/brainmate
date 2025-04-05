<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('duration_days')->nullable()->after('deadline');
            $table->timestamp('published_at')->nullable()->after('duration_days');
            $table->boolean('is_backlog')->default(false)->after('published_at');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['duration_days', 'published_at', 'is_backlog']);
        });
    }
};
