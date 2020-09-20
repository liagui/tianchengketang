<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdLecturerEducationaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_lecturer_educationa', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('网校id');
            $table->string('head_icon' , 255)->default('')->comment('头像');
            $table->string('teacher_icon' , 255)->default('')->comment('讲师图片');
            $table->char('phone', 11)->default('')->comment('手机号');
            $table->string('real_name' , 255)->default('')->comment('讲师姓名');
            $table->unsignedTinyInteger('sex')->default(1)->comment('性别(1男,2女)');
            $table->string('qq' , 255)->default('')->comment('QQ号');
            $table->string('wechat' , 255)->default('')->comment('微信号');
            $table->integer('parent_id')->default(0)->comment('学科一级分类');
            $table->integer('child_id')->default(0)->comment('学科二级分类');
            $table->text('describe')->nullable()->comment('描述');
            $table->text('content')->nullable()->comment('详情');
            $table->integer('number')->default(0)->comment('开课数量');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('is_forbid')->default(0)->comment('是否禁用(1代表禁用,0代表启用)');
            $table->tinyInteger('is_recommend')->default(0)->comment('是否推荐(1代表推荐,0代表不推荐)');
            $table->tinyInteger('type')->default(1)->comment('老师类型(1代表教务,2代表讲师)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('school_id' , 'index_school_id');
            $table->index('parent_id' , 'index_type');
            $table->index(['type', 'is_forbid','real_name','id'], 'index_status_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_lecturer_educationa` comment '讲师教务表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_lecturer_educationa');
    }
}
