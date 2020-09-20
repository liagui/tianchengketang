<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseOpenLiveChildsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_open_live_childs', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('lesson_id')->default(0)->comment('公开课id');
            $table->integer('live_id')->default(0)->comment('直播ID');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->string('course_name' , 255)->default('')->comment('课程名称');
            $table->integer('account')->default(0)->comment('接入方主播账号或ID或手机号');
            $table->dateTime('start_time')->nullable()->comment('开始时间');
            $table->dateTime('end_time')->nullable()->comment('结束时间');
            $table->string('nickname' , 255)->default('')->comment('主播的昵称');
            $table->string('accountIntro' , 255)->default('')->comment('主播的简介');
            $table->string('options' , 255)->default('')->comment('其它可选参数');
            $table->string('url' , 255)->default('')->comment('资源地址');
            $table->integer('partner_id')->default(0)->comment('合作方ID');
            $table->integer('bid')->default(0)->comment('欢拓系统的主播ID');
            $table->integer('course_id')->default(0)->comment('课程ID');
            $table->string('zhubo_key' , 255)->default('')->comment('主播登录秘钥');
            $table->string('admin_key' , 255)->default('')->comment('助教登录秘钥');
            $table->string('user_key' , 255)->default('')->comment('学生登录秘钥');
            $table->integer('add_time')->default(0)->comment('课程创建时间');
            
            $table->integer('watch_num')->default(0)->comment('观看人数');
            $table->integer('like_num')->default(0)->comment('点赞人数');
            $table->integer('online_num')->default(0)->comment('在线人数');
            
            $table->tinyInteger('is_free')->default(0)->comment('是否收费：0否1是');
            $table->tinyInteger('is_public')->default(1)->comment('是否公开课：0否1是');
            $table->tinyInteger('modetype')->default(0)->comment('模式：1语音云3大班5小班6大班互动');
            $table->tinyInteger('barrage')->default(0)->comment('是否开启弹幕：0关闭1开启');
            $table->string('robot' , 255)->default('')->comment('虚拟用户数据');
            
            $table->tinyInteger('status')->default(0)->comment('直播状态(1代表预开始2代表正在播放3代表已结束)');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('is_forbid')->default(0)->comment('是否禁用(1代表是,0代表否)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');
            $table->tinyInteger('playback')->default(0)->comment('是否生成回放0未生成1已生成');
            $table->string('playbackUrl' , 255)->default('')->comment('回放地址');
            $table->integer('duration')->default(0)->comment('时长(秒)');

            //索引设置部分
            $table->index('live_id' , 'index_live_id');
            $table->index('admin_id' , 'index_admin_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_open_live_childs` comment '公开课欢拓关联表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_open__live_childs');
    }
}
