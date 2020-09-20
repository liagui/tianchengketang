<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdQuestionExamTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_question_exam', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('parent_id')->default(0)->comment('材料题父级id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('subject_id')->default(0)->comment('科目id');
            $table->integer('bank_id')->default(0)->comment('题库id');
            $table->text('exam_content')->nullable()->comment('试题内容');
            $table->text('answer')->nullable()->comment('正确答案');
            $table->text('text_analysis')->nullable()->comment('文字解析');
            $table->string('audio_analysis' , 255)->default('')->comment('音频解析');
            $table->string('video_analysis' , 255)->default('')->comment('视频解析');
            $table->integer('chapter_id')->default(0)->comment('章id');
            $table->integer('joint_id')->default(0)->comment('节id');
            $table->integer('point_id')->default(0)->comment('考点id');
            $table->tinyInteger('type')->default(0)->comment('试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)');
            $table->tinyInteger('item_diffculty')->default(0)->comment('试题难度(1代表简单,2代表一般,3代表困难)');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('is_publish')->default(0)->comment('是否发布(1代表发布,0代表未发布)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');
            
            //索引设置部分
            $table->index('bank_id' , 'index_bank_id');
            $table->index(['admin_id', 'chapter_id','joint_id', 'point_id','item_diffculty','is_publish'], 'index_exam');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_question_exam` comment '题库试题表'");
        //增加全文索引
        DB::statement('alter table `ld_question_exam` add fulltext index index_content(exam_content)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_question_exam');
    }
}
