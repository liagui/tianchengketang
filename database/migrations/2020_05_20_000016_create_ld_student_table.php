<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdStudentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_student', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('分校id');
            $table->integer('province_id')->default(0)->comment('省id');
            $table->integer('city_id')->default(0)->comment('市id');
            $table->char('phone' , 11)->default('')->comment('手机号');
            $table->string('token' , 255)->default('')->comment('用户token');
            $table->string('real_name' , 255)->default('')->comment('姓名');
            $table->string('nickname' , 255)->default('')->comment('昵称');
            $table->string('sign' , 255)->default('')->comment('签名');
            $table->string('head_icon' , 255)->default('')->comment('头像');
            $table->string('password' , 255)->default('')->comment('密码');
            $table->tinyInteger('sex')->default(1)->comment('性别(1男,2代表女)');
            $table->tinyInteger('papers_type')->default(0)->comment('证件类型(1代表身份证,2代表护照,3代表港澳通行证,4代表台胞证,5代表军官证,6代表士官证,7代表其他)');
            $table->string('papers_num' , 255)->default('')->comment('证件号码');
            $table->string('birthday' , 255)->default('')->comment('出生日期');
            $table->string('address_locus' , 255)->default('')->comment('户口所在地');
            $table->integer('age')->default(0)->comment('年龄');
            $table->tinyInteger('educational')->default(0)->comment('学历(1代表小学,2代表初中,3代表高中,4代表大专,5代表大本,6代表研究生,7代表博士生,8代表博士后及以上)');
            $table->string('family_phone' , 60)->default('')->comment('家庭电话号');
            $table->string('office_phone' , 60)->default('')->comment('办公电话');
            $table->string('contact_people' , 60)->default('')->comment('紧急联系人');
            $table->string('contact_phone' , 60)->default('')->comment('紧急联系电话');
            $table->string('email' , 60)->default('')->comment('邮箱');
            $table->string('qq' , 30)->default('')->comment('qq号码');
            $table->string('wechat' , 30)->default('')->comment('微信号');
            $table->string('address' , 255)->default('')->comment('住址');
            $table->text('remark')->nullable()->comment('备注');
            $table->string('device' , 255)->default('')->comment('设备唯一标识');
            $table->decimal('balance' , 10 , 2)->default(0)->comment('余额');
            $table->tinyInteger('is_forbid')->default(1)->comment('账号状态(1代表启用,2代表禁用)');
            $table->tinyInteger('enroll_status')->default(0)->comment('报名状态(1代表已报名,0代表未报名)');
            $table->tinyInteger('state_status')->default(0)->comment('开课状态(0代表均未开课,1代表部分未开课,2代表全部开课)');
            $table->tinyInteger('reg_source')->default(0)->comment('注册来源(0代表官网注册,1代表手机端,2代表线下录入)');
            $table->tinyInteger('user_type')->default(1)->comment('用户类型(1代表正常用户,2代表游客)');
            $table->dateTime('create_at')->nullable()->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');
            $table->dateTime('login_at')->nullable()->comment('最后登录时间');

            //索引设置部分
            $table->index('school_id' , 'index_school_id');
            $table->index(['is_forbid', 'state_status'], 'index_status_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_student` comment '学员表'");
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_student');
    }
}
