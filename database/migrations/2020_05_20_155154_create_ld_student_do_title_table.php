<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdStudentDoTitleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_student_do_title', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('student_id')->default(0)->comment('学员id');
            $table->integer('bank_id')->default(0)->comment('题库id');
            $table->integer('subject_id')->default(0)->comment('科目id');
            $table->integer('chapter_id')->default(0)->comment('章id');
            $table->integer('joint_id')->default(0)->comment('节id');
            $table->integer('papers_id')->default(0)->comment('试卷id');
            $table->integer('exam_id')->default(0)->comment('试题id');
            $table->text('answer')->nullable()->comment('学员答案');
            $table->tinyInteger('is_right')->default(0)->comment('对错类型(1代表正确,2代表错误)');
            $table->tinyInteger('type')->default(0)->comment('生成类型(1代表章节练习2代表快速做题3代表模拟真题)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index(['student_id', 'bank_id'], 'index_exam_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_student_do_title` comment '学员做题记录表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_student_do_title');
    }
}
