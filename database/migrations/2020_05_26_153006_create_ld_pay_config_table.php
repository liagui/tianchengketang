<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLdPayConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ld_pay_config', function (Blueprint $table) {
            $table->increments('id')->comment('自增id');
            $table->integer('admin_id')->default(0)->comment('操作员id');
            $table->integer('school_id')->default(0)->comment('学校id');
            $table->text('zfb_app_id')->nullable()->comment('AppID（支付宝）');
            $table->text('zfb_app_public_key')->nullable()->comment('应用公钥(支付宝)');
            $table->text('zfb_public_key')->nullable()->comment('支付宝公钥');
            $table->text('wx_app_id')->nullable()->comment('AppID（微信）');
            $table->text('wx_commercial_tenant_number')->nullable('')->comment('商户号（微信）');
            $table->text('wx_api_key')->nullable()->comment('API密钥(微信)');
            $table->text('hj_md_key')->nullable()->comment('MD5密钥');
            $table->text('hj_commercial_tenant_number')->nullable()->comment('商户号（汇聚）');
            $table->text('hj_wx_commercial_tenant_deal_number')->nullable()->comment('交易商户号（微信）');
            $table->text('hj_zfb_commercial_tenant_deal_number')->nullable()->comment('交易商户号（支付宝）');
            $table->tinyInteger('wx_pay_state')->default(0)->comment('微信支付状态 1 开启 -1关闭');
            $table->tinyInteger('zfb_pay_state')->default(0)->comment('支付宝支付状态 1 开启 -1关闭');
            $table->tinyInteger('hj_wx_pay_state')->default(0)->comment('汇聚微信支付状态 1 开启 -1关闭'); 
            $table->tinyInteger('hj_zfb_pay_state')->default(0)->comment('汇聚支付宝支付状态 1 开启 -1关闭');     
            $table->dateTime('create_at')->comment('创建时间');
            $table->dateTime('update_at')->nullable()->comment('修改时间');
            
            //索引设置部分
            $table->index('admin_id' , 'index_admin_id');
            $table->index('school_id' , 'index_school_id');
            
            //引擎设置部分
            $table->engine  = 'InnoDB';
        });
        //设置表注释
        DB::statement("alter table `ld_pay_config` comment '支付配置表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ld_pay_config');
    }
}
