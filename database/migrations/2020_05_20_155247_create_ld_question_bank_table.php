<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdQuestionBankTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_question_bank', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->string('topic_name' , 255)->default('')->comment('题库名称');
            $table->string('subject_id' , 255)->default('')->comment('题库科目id');
            $table->integer('parent_id')->default(0)->comment('学科一级分类');
            $table->integer('child_id')->default(0)->comment('学科二级分类');
            $table->text('describe')->nullable()->comment('描述');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('is_open')->default(1)->comment('开启状态(1代表关闭,0代表启用)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('subject_id' , 'index_subject_id');
            $table->index(['parent_id', 'child_id'], 'index_type_id');
            $table->index(['admin_id', 'subject_id','parent_id', 'child_id'], 'index_admin_bank');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_question_bank` comment '题库表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_question_bank');
    }
}
