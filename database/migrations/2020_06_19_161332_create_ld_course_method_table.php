<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseMethodTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_method', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('course_id')->default(0)->comment('课程id');
            $table->integer('method_id')->default(0)->comment('授课方式(1直播2录播3其他)');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->timestamp('create_at')->comment('创建时间')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('method_id' , 'index_method_id');
            $table->index(['course_id', 'method_id'], 'index_admin_method');

            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_method` comment '课程授课方式表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_method');
    }
}
