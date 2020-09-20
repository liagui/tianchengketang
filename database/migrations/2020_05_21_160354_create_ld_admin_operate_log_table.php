<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdAdminOperateLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_admin_operate_log', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->string('module_name' , 255)->default('')->comment('模块名称');
            $table->string('route_url' , 255)->default('')->comment('路由路径');
            $table->string('operate_method' , 10)->default('')->comment('操作方式(insert,update,delete)');
            $table->text('content')->nullable()->comment('备注(增加或者更改或者删除了哪些内容)');
            $table->string('ip' , 60)->default('')->comment('ip地址');
            $table->dateTime('create_at')->comment('创建时间');
            
            //索引设置部分
            $table->index('admin_id' , 'index_admin_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_admin_operate_log` comment '后台操作日志表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_admin_operate_log');
    }
}
