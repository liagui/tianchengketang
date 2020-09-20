<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseClassNumberTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_class_number', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('分校id');
            $table->integer('shift_no_id')->default(0)->comment('班号id');
            $table->integer('start_at')->nullable()->comment('课次开始时间');
            $table->integer('end_at')->nullable()->comment('课次结束时间');
            $table->string('name' , 255)->default('')->comment('课次名称');
            $table->decimal('class_hour', 10, 2)->nullable()->comment('课时');
            $table->tinyInteger('is_free')->default(0)->comment('是否收费(1代表是,0代表否)');
            $table->tinyInteger('is_bullet')->default(0)->comment('是否弹幕(1代表是,0代表否)');
            $table->tinyInteger('live_type')->default(6)->comment('选择模式(1语音云3大班5小班6大班互动)');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('shift_no_id' , 'index_shift_no_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_class_number` comment '课程课次表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_class_number');
    }
}
