<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseOpenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_open', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('学校id');
            $table->integer('parent_id')->default(0)->comment('学科一级分类id');
            $table->integer('child_id')->default(0)->comment('学科二级分类id');
            $table->string('title' , 255)->default('')->comment('课程标题');
            $table->string('keywords' , 255)->default('')->comment('课程关键词');
            $table->string('cover' , 255)->default('')->comment('课程封面');
            $table->integer('start_at')->nullable()->comment('公开课开始时间');
            $table->integer('end_at')->nullable()->comment('公开课结束时间');
            $table->text('describe')->nullable()->comment('课程描述');
            $table->text('introduce')->nullable()->comment('课程介绍');
            $table->tinyInteger('is_barrage')->default(0)->comment('是否弹幕(1代表是,0代表否)');
            $table->tinyInteger('live_type')->default(6)->comment('直播类型(1语音云3大班5小班6大班互动)');
            $table->tinyInteger('nature')->default(0)->comment('课程属性(1代表授权,0代表自增)');
            $table->tinyInteger('is_recommend')->default(0)->comment('是否推荐(1代表推荐,0代表不推荐)');
            $table->tinyInteger('status')->default(0)->comment('课程状态(0代表未发布,1代表在售(已发布),2代表停售(已下架))');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('status' , 'index_status');
            $table->index('nature' , 'index_nature');
            $table->index(['parent_id', 'child_id','status', 'nature'], 'index_admin_course');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_open` comment '课程公开课表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_open');
    }
}
