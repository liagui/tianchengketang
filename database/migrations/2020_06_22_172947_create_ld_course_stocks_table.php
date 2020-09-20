<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_stocks', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_pid')->default(0)->comment('分配分校ID');
            $table->integer('school_id')->default(0)->comment('被分配分校ID');
            $table->integer('course_id')->default(0)->comment('课程id');
            $table->integer('current_number')->default(0)->comment('当前库存');
            $table->integer('add_number')->default(0)->comment('添加库存数');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('is_forbid')->default(0)->comment('是否禁用(1代表是,0代表否)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('school_id' , 'index_school_id');
            $table->index('course_id' , 'index_course_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_stocks` comment '授权课程库存表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_stocks');
    }
}
