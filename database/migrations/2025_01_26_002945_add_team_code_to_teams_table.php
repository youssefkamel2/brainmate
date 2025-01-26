<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeamCodeToTeamsTable extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('team_code')->unique()->nullable(); // Unique team code
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('team_code');
        });
    }
}