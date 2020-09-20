<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdSubjectVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_subject_videos', function (Blueprint $table) {
            $table->integer('video_id')->unsigned()->comment('录播资源ID');
            $table->integer('subject_id')->unsigned()->comment('科目ID');
            $table->timestamps();

            $table->foreign('video_id')->references('id')->on('ld_videos')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('ld_subjects')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->index('video_id');
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
        Schema::dropIfExists('ld_subject_videos');
    }
}
