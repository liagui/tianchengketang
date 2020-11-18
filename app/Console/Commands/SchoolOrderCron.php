<?php

namespace App\Console\Commands;

use App\Models\SchoolOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Helpers;
use SebastianBergmann\CodeCoverage\Report\PHP;

class SchoolOrderCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'SchoolOrderUpdateInvalid';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '更改超过一天的未支付订单状态为无效';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 更改服务模块超过一天未支付的订单 状态为失效
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $time = time();
        $yesterday = date("Y-m-d H:i:s", strtotime("-1 day"));
        $wheres = [
            ['online', '=', 1],//线上订单,服务端订单
            ['status', '=', 1],//未支付
            ['apply_time', '>=', $yesterday ],
        ];
        //
        $res = SchoolOrder::where($wheres)->update(['status'=>3]);
        echo time() - $time;

    }




}
