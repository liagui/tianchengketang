<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class CreateLdRoleAuthTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_role_auth', function (Blueprint $table) {
            $table->increments('id');
            $table->string('role_name')->comment('角色名称');
            $table->string('auth_desc')->comment('权限描述');
            $table->string('auth_id')->comment('权限组ID');
            $table->integer('admin_id')->comment('操作员ID');
            $table->dateTime('create_time')->comment('创建时间');
            $table->dateTime('update_time')->nullable()->comment('修改时间');
            $table->smallInteger('is_super')->default(1)->comment('超级管理员0否1是');
            $table->smallInteger('is_del')->default(1)->comment('删除0是1否');
            $table->integer('school_id')->comment('学校ID');


            $table->index('auth_id' , 'index_auth_id');
        });
        DB::statement("alter table `ld_role_auth` comment '角色表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_role_auth');
    }
}
