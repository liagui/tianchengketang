<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdRegionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_region', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->string('name' , 50)->default('')->comment('地区名称');
            $table->integer('parent_id')->default(0)->comment('父级id');

            //索引设置部分
            $table->index('parent_id' , 'index_parent_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_region` comment '地区表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_region');
    }
}
