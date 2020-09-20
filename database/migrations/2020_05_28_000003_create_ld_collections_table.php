<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdCollectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_collections', function (Blueprint $table) {
            $table->integer('student_id')->unsigned()->comment('学员ID');
            $table->integer('lesson_id')->unsigned()->comment('课程ID');
            $table->tinyInteger('is_del')->default(0)->comment('删除0否1是');
            $table->timestamps();

            $table->foreign('lesson_id')->references('id')->on('ld_lessons')->onUpdate('cascade')->onDelete('cascade');  
            $table->foreign('student_id')->references('id')->on('ld_student')->onUpdate('cascade')->onDelete('cascade');  

            $table->primary(['lesson_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_collections');
    }
}
