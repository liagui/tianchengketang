<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class CreateLdAuthMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_auth_map', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 20)->comment('路由描述');
            $table->smallInteger('parent_id')->default(0)->comment('父级ID');
            $table->string('icon', 50)->nullable()->comment('图标');
            $table->longText('auth_id')->comment('权限组id');
            $table->smallInteger('sort')->default(0)->comment('排序');
            $table->smallInteger('is_show')->default(0)->comment('是否显示 0显示  1隐藏');
            $table->smallInteger('is_del')->default(0)->comment('是否删除 0未删除  1已删除');
            $table->smallInteger('is_forbid')->default(0)->comment('启用   0 启用 1禁用');
            $table->index('id' , 'index_id');
            $table->index('auth_id' , 'index_auth_id');
        });
        DB::statement("alter table `ld_auth_map` comment '权限映射表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_auth_map');
    }
}
