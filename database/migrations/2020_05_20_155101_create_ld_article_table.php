<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdArticleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_article', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('school_id')->default(0)->comment('分校ID');
            $table->integer('article_type_id')->default(0)->comment('文章类型ID');
            $table->char('title', 30)->comment('标题');
            $table->string('image', 100)->comment('封面');
            $table->string('key_word', 50)->comment('关键词');
            $table->string('sources', 50)->comment('来源');
            $table->string('accessory')->comment('附件');
            $table->string('description')->comment('摘要');
            $table->text('text')->comment('正文');
            $table->integer('user_id')->nullable()->comment('创建人ID');
            $table->integer('share')->nullable()->comment('分享次数');
            $table->smallInteger('status')->default(0)->comment('0禁用1开启');
            $table->smallInteger('is_del')->default(1)->comment('0无效1有效');
            $table->timestamp('create_at')->comment('创建时间')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('update_at')->nullable()->comment('修改时间');

            //索引设置部分
            $table->index('school_id' , 'index_school_id');
            $table->index('article_type_id' , 'index_article_type_id');
        });
        //设置表注释
        DB::statement("alter table `ld_article` comment '文章表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_article');
    }
}
