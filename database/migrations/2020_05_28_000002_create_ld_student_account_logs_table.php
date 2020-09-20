<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdStudentAccountlogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_student_account_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->default(0)->comment('用户ID');
            $table->decimal('price', 10, 2)->comment('金额');
            $table->decimal('end_price', 10, 2)->comment('修改后金额');
            $table->tinyInteger('status')->default(0)->comment('1充值2消费');
            $table->integer('class_id')->default(0)->comment('课程ID');
            $table->timestamp('create_at')->comment('创建时间')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('update_at')->nullable()->comment('修改时间');

            $table->index('user_id' , 'index_user_id');
        });
        //设置表注释
        DB::statement("alter table `ld_student_account_logs` comment '用户账户日志'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_student_account_logs');
    }
}
