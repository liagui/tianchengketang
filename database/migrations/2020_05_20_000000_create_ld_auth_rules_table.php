<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class CreateLdAuthRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_auth_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 80)->comment('路由名称');
            $table->string('title', 20)->comment('路由描述');
            $table->smallInteger('parent_id')->default(0)->comment('父级ID');
            $table->string('icon', 50)->nullable()->comment('图标');
            $table->smallInteger('sort')->default(0)->comment('排序');
            $table->string('condition', 100)->nullable()->comment('身份');
            $table->smallInteger('is_show')->default(1)->comment('状态1否0是');
            $table->smallInteger('is_del')->default(1)->comment('删除1否0是');
            $table->smallInteger('is_forbid')->default(1)->comment('启用1否0是');

            $table->index('name' , 'index_name');
        });
        DB::statement("alter table `ld_auth_rules` comment '权限表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_auth_rules');
    }
}
