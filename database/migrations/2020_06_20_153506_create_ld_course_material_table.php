<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseMaterialTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_material', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('分校id');
            $table->integer('parent_id')->default(0)->comment('小节/班号/课次id');
            $table->integer('course_id')->default(0)->comment('课程id');
            $table->tinyInteger('type')->default(0)->comment('资料类型(1材料2辅料3其他)');
            $table->string('material_name' , 255)->default('')->comment('资料的名称');
            $table->string('material_size' , 255)->default('')->comment('资料的大小');
            $table->string('material_url' , 255)->default('')->comment('资料的url');
            $table->tinyInteger('mold')->default(0)->comment('资料类型(1课程小节2班号3课次)');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->timestamp('create_at')->comment('创建时间')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index(['parent_id', 'mold'], 'index_parent_mold');
            $table->index('course_id' , 'index_course_id');

            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_material` comment '课程资料表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_material');
    }
}
