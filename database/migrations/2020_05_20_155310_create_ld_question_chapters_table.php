<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateLdQuestionChaptersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_question_chapters', function (Blueprint $table) {
            //字段设置部分
            $table->increments('id')->comment('自增id');
            $table->integer('parent_id')->default(0)->comment('父级id');
            $table->integer('subject_id')->default(0)->comment('科目id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('bank_id')->default(0)->comment('题库id');
            $table->string('name' , 255)->default('')->comment('名称');
            $table->tinyInteger('type')->default(0)->comment('类型(0代表章1代表节2代表考点)');
            $table->tinyInteger('is_del')->default(0)->comment('是否删除(1代表删除,0代表正常)');
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('更新时间');
            
            //索引设置部分
            $table->index('parent_id' , 'index_parent_id');
            $table->index('bank_id' , 'index_bank_id');
            $table->index(['admin_id', 'parent_id','bank_id'], 'index_admin_bank');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_question_chapters` comment '题库章节表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_question_chapters');
    }
}
