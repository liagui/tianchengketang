<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseVideoResourceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_video_resource', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('分校id');
            $table->integer('parent_id')->default(0)->comment('学科一级分类id');
            $table->integer('child_id')->default(0)->comment('学科二级分类id');
            $table->integer('course_id')->default(0)->comment('欢拓课程ID');
            $table->integer('mt_video_id')->default(0)->comment('欢拓视频ID');
            $table->string('mt_video_name' , 255)->default('')->comment('欢拓视频标题');
            $table->string('mt_url' , 255)->default('')->comment('欢拓视频临时观看地址');
            $table->integer('mt_duration')->default(0)->comment('时长(秒)');
            $table->integer('start_time')->default(0)->comment('课程的开始时间');
            $table->integer('end_time')->default(0)->comment('课程的结束时间');
            $table->tinyInteger('resource_type')->default(0)->comment('资源类型(1视频2音频3课件4文档)');
            $table->string('resource_name' , 255)->default('')->comment('资源名称');
            $table->string('resource_url' , 255)->default('')->comment('资源url');
            $table->string('resource_size' , 255)->default('')->comment('资源大小');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('nature')->default(0)->comment('资源属性(1代表授权,0代表自增)');
            $table->tinyInteger('status')->default(0)->comment('状态(1禁用,0启用)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index(['parent_id', 'child_id' , 'resource_type' , 'nature' , 'status'], 'index_resource_status');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_video_resource` comment '录播资源表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_video_resource');
    }
}
