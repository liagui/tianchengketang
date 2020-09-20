<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdStudentAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_student_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->default(0)->comment('用户ID');
            $table->string('order_number', 50)->nullable()->comment('订单号');
            $table->string('third_party_number', 50)->nullable()->comment('第三方订单号');
            $table->decimal('price', 10, 2)->comment('金额');
            $table->smallInteger('pay_type')->default(0)->comment('1微信2支付宝3汇聚微信4汇聚支付宝5ios内购');
            $table->smallInteger('order_type')->default(0)->comment('1充值2购买');
            $table->text('content')->nullable()->comment('返回的数据');
            $table->smallInteger('status')->default(0)->comment('0未支付1支付成功2支付失败');
            $table->integer('class_id')->default(0)->comment('课程ID');
            $table->timestamp('create_at')->comment('创建时间')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('update_at')->nullable()->comment('修改时间');

            $table->index('user_id' , 'index_user_id');
            $table->index('order_number' , 'index_order_number');
        });
        //设置表注释
        DB::statement("alter table `ld_student_accounts` comment '用户账户表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_student_accounts');
    }
}
