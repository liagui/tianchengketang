<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdFooterConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_footer_config', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('parent_id')->default(0)->comment('父级id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('分校id');
            $table->string('logo' , 255)->default('')->comment('logo');
            $table->string('name' , 255)->default('')->comment('名称');
            $table->string('url' , 255)->default('')->comment('URL');
            $table->tinyInteger('is_open')->default(0)->comment('是否开关(1代表关闭,0代表开启)');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->timestamp('create_at')->comment('创建时间')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('update_at')->nullable()->comment('更新时间');

            //索引设置部分
            $table->index('parent_id' , 'index_parent_id');
            $table->index('admin_id' , 'index_admin_id');
            $table->index('school_id' , 'index_school_id');

            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_footer_config` comment '页脚配置表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_footer_config');
    }
}
