<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdStudentEnrolmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_student_enrolment', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('分校id');
            $table->integer('student_id')->default(0)->comment('学员id');
            $table->integer('parent_id')->default(0)->comment('学科分类id');
            $table->integer('child_id')->default(0)->comment('学科二级分类得id');
            $table->integer('lession_id')->default(0)->comment('课程id');
            $table->decimal('lession_price' , 10 , 2)->default(0)->comment('课程原价');
            $table->decimal('student_price' , 10 , 2)->default(0)->comment('学员价格');
            $table->tinyInteger('payment_type')->default(0)->comment('付款类型(1代表定金,2代表尾款,3代表最后一次尾款,4代表全款)');
            $table->tinyInteger('payment_method')->default(0)->comment('付款方式(1代表微信,2代表支付宝,3代表银行转账)');
            $table->decimal('payment_fee' , 10 , 2)->default(0)->comment('付款金额');
            $table->dateTime('payment_time')->comment('付款时间');
            $table->tinyInteger('status')->default(0)->comment('报名状态(1代表手动报名,0代表自动报名)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('student_id' , 'index_student_id');
            $table->index('parent_id' , 'index_parent_id');
            $table->index('lession_id' , 'index_lession_id');
            $table->index(['parent_id', 'lession_id'], 'index_patent_lession');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_student_enrolment` comment '学员报名表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_student_enrolment');
    }
}
