<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseChaptersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_chapters', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('分校id');
            $table->integer('parent_id')->default(0)->comment('父级id');
            $table->integer('course_id')->default(0)->comment('课程id');
            $table->integer('resource_id')->default(0)->comment('录播资源id');
            $table->string('name',255)->default(0)->comment('章/节名称');
            $table->tinyInteger('type')->default(0)->comment('小节类型(1视频2音频3课件4文档)');
            $table->tinyInteger('is_free')->default(0)->comment('是否免费(2代表试听,1代表收费,0代表免费)');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->timestamp('create_at')->comment('创建时间')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('parent_id' , 'index_parent_id');
            $table->index('resource_id' , 'index_resource_id');
            $table->index('course_id' , 'index_course_id');

            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_chapters` comment '课程章节表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_chapters');
    }
}
