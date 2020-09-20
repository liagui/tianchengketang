<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseLivecastResourceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_livecast_resource', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('分校id');
            $table->integer('parent_id')->default(0)->comment('学科一级分类id');
            $table->integer('child_id')->default(0)->comment('学科二级分类id');
            $table->string('name' , 255)->default('')->comment('课程单元名称');
            $table->text('introduce')->nullable()->comment('课程介绍');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('nature')->default(0)->comment('资源属性(1代表授权,0代表自增)');
            $table->tinyInteger('is_forbid')->default(0)->comment('状态(2禁用,1已被课程使用,0未被课程使用)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index(['parent_id', 'child_id' , 'nature' , 'is_forbid'], 'index_resource');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_livecast_resource` comment '直播资源表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_livecast_resource');
    }
}
