<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_videos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('admin_id')->default(0)->comment('操作员ID');
            $table->string('name')->comment('视频名称');
            $table->integer('category')->nullable()->default(0)->comment('类型:1视频2音频3课件4文档');
            $table->string('url')->comment('资源地址');
            $table->tinyInteger('is_del')->nullable()->default(0)->comment('是否删除：0否1是');
            $table->tinyInteger('is_forbid')->nullable()->default(0)->comment('是否禁用：0否1是');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_videos');
    }
}
