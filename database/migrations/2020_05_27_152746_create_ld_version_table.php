<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdVersionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_version', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->string('version' , 255)->default('')->comment('版本号名称');
            $table->string('download_url' , 255)->default('')->comment('下载地址');
            $table->text('content')->nullable()->comment('更新内容');
            $table->tinyInteger('is_online')->default(0)->comment('是否在审核阶段(1代表是,0代表否)');
            $table->tinyInteger('is_mustup')->default(0)->comment('是否强制更新(1代表是,0代表否)');
            $table->dateTime('create_at')->nullable()->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_version` comment '版本升级表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_version');
    }
}
