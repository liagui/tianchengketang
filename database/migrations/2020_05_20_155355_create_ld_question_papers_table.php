<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdQuestionPapersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_question_papers', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('subject_id')->default(0)->comment('科目id');
            $table->integer('bank_id')->default(0)->comment('题库id');
            $table->string('papers_name' , 255)->default('')->comment('试卷名称');
            $table->tinyInteger('diffculty')->default(0)->comment('试题难度(1代表真题,2代表模拟题,3代表其他)');
            $table->integer('papers_time')->default(0)->comment('答题时间');
            $table->integer('area')->default(0)->comment('所属区域');
            $table->string('cover_img' , 255)->default('')->comment('封面图片');
            $table->text('content')->nullable()->comment('简介');
            $table->string('type' , 60)->default('')->comment('试题类型(1代表单选题2代表多选题4代表不定项3代表判断题5填空题6简答题7材料题)');
            $table->integer('signle_score')->default(0)->comment('单选题每题得分');
            $table->integer('more_score')->default(0)->comment('多选题每题得分');
            $table->integer('judge_score')->default(0)->comment('判断题每题得分');
            $table->integer('options_score')->default(0)->comment('不定项选择题每题得分');
            $table->integer('pack_score')->default(0)->comment('填空每题得分');
            $table->integer('short_score')->default(0)->comment('简答题每题得分');
            $table->integer('material_score')->default(0)->comment('材料题每题得分');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('is_publish')->default(1)->comment('是否发布(1代表发布,0代表未发布)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('bank_id' , 'index_bank_id');
            $table->index(['admin_id', 'diffculty','is_publish', 'papers_name'], 'index_subject_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_question_papers` comment '题库试卷表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_question_papers');
    }
}
