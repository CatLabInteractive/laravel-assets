<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAssetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assets', function(Blueprint $table) {

            $table->increments('id');

            $table->string('name');
            $table->string('mimetype');
            $table->string('type');
            $table->integer('size')->unsigned();

            $table->string('disk', 32)->after('size')->nullable();
            $table->string('path');

            $table->string('hash')->length(64);
            $table->index('hash');

            $table->integer('width')->unsigned()->nullable();
            $table->integer('height')->unsigned()->nullable();

            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_Id')->references('id')->on('users');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assets');
    }
}
