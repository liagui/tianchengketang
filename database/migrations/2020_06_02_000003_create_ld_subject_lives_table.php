<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdSubjectLivesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_subject_lives', function (Blueprint $table) {
            $table->integer('live_id')->unsigned()->comment('直播ID');
            $table->integer('subject_id')->unsigned()->comment('科目ID');
            $table->timestamps();

            $table->foreign('live_id')->references('id')->on('ld_lives')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('ld_subjects')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->index('live_id');
            $table->index('subject_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_subject_lives');
    }
}
