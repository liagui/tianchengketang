<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseRefOpenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_ref_open', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('from_school_id')->default(0)->comment('授权学校id');
            $table->integer('to_school_id')->default(0)->comment('被授权学校id');
            $table->integer('course_id')->default(0)->comment('公开课id');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('from_school_id' , 'index_from_school_id');
            $table->index('to_school_id' , 'index_to_school_id');
            $table->index('course_id' , 'index_course_id');
        });
        //设置表注释
        DB::statement("alter table `ld_course_ref_open` comment '公开课授权表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_ref_open');
    }
}
