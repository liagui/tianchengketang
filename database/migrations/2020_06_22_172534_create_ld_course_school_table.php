<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdCourseSchoolTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_course_school', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('from_school_id')->default(0)->comment('授权学校id');
            $table->integer('to_school_id')->default(0)->comment('被授权学校id');
            $table->integer('course_id')->default(0)->comment('授权课程id');
            $table->string('title' , 255)->default('')->comment('课程标题');
            $table->string('keywords' , 255)->default('')->comment('课程关键词');
            $table->string('cover' , 255)->default('')->comment('课程封面');
            $table->decimal('pricing', 10, 2)->nullable()->comment('课程定价');
            $table->decimal('sale_price', 10, 2)->nullable()->comment('优惠价格');
            $table->integer('buy_num')->nullable()->default(0)->comment('购买基数');
            $table->integer('expiry')->nullable()->default(0)->comment('课程有效期');
            $table->text('describe')->nullable()->comment('课程描述');
            $table->text('introduce')->nullable()->comment('课程介绍');
            $table->tinyInteger('status')->default(0)->comment('课程状态(0代表未发布,1代表在售(已发布),2代表停售(已下架))');
            $table->integer('watch_num')->default(0)->comment('观看数');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->tinyInteger('is_recommend')->default(0)->comment('是否推荐(1代表推荐,0代表不推荐)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('from_school_id' , 'index_from_school_id');
            $table->index('to_school_id' , 'index_to_school_id');
            $table->index('course_id' , 'index_course_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_course_school` comment '课程授权表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_course_school');
    }
}
