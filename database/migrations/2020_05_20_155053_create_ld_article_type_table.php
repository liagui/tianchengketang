<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdArticleTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_article_type', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('school_id')->default(0)->comment('分校ID');
            $table->char('typename', 30)->comment('类型名称');
            $table->integer('user_id')->default(0)->comment('创建人ID');
            $table->string('description')->comment('简介');
            $table->smallInteger('status')->default(0)->comment('0禁用1启用');
            $table->smallInteger('is_del')->default(1)->comment('0无效1有效');
            $table->timestamp('create_at')->comment('创建时间')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('update_at')->nullable()->comment('修改时间');

            $table->index('school_id' , 'index_school_id');
        });
        //设置表注释
        DB::statement("alter table `ld_article_type` comment '文章类型'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_article_type');
    }
}
