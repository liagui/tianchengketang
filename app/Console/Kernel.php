<?php

namespace App\Console;

use App\Console\Commands\EmpowerCron;
use App\Console\Commands\TestCodeCron;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Redis;
use App\Tools\MTCloud;
use Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\CCRoomLiveAnalysisRecodeAllCron::class,
        \App\Console\Commands\CCRoomLiveAnalysisLiveAllCron::class,
        \App\Console\Commands\CCRoomLiveAnalysisLiveCron::class,
        \App\Console\Commands\CCRoomLiveAnalysisRecodeCron::class,
        \App\Console\Commands\CCTrafficCron::class,
        \App\Console\Commands\SchoolOrderCron::class,
        \App\Console\Commands\CCConnectionsCron::class,
        \App\Console\Commands\CourseSalesCron::class,
        \App\Console\Commands\SchoolOrderCron::class,
        \App\Console\Commands\EmpowerCron::class,
        \App\Console\Commands\Message\CheckTodayRoomIdCron::class,
        \App\Console\Commands\Message\SendMessageForClassIdCron::class,
        \App\Console\Commands\Message\SendMessageForLiveChanageCron::class,

        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        //
        $schedule->call(function () {

            //Redis::del('num');
            $num = Redis::incr('cccc');
            Log::info('User failed to login.', [ 'num' => $num ]);
            $pagesize = 200;
            $page = $num;
            $offset = ($page - 1) * $pagesize;
            Log::info('User failed to login.', [ '页数' => $page ]);
            Log::info('User failed to login.', [ '条数' => $pagesize ]);
            $testt = DB::table("testt")->offset($offset)->limit($pagesize)->get()->toArray();
            Log::info('User failed to login.', [ '数据' => $testt ]);
            foreach ($testt as $k => $v) {
                // $data['course_id'] = $v[0];
                // $data['videoId'] = $v[1];
                // $res = DB::table("testt")->insert($data);
                $MTCloud = new MTCloud();
                $res = $MTCloud->videoGet($v->videoId);
                //dd($res);
                if ($res[ 'code' ] == 0) {
                    $test[ 'mt_video_id' ] = $res[ 'data' ][ 'videoId' ];
                    $test[ 'mt_video_name' ] = $res[ 'data' ][ 'title' ];
                    $test[ 'mt_url' ] = $res[ 'data' ][ 'videoUrl' ];
                    $test[ 'mt_duration' ] = $res[ 'data' ][ 'duration' ];
                    $test[ 'resource_size' ] = $res[ 'data' ][ 'filesize' ];
                    $d = DB::table("test")->insert($test);
                    Log::info('数据', [ 'res' => $d, 'videoId' => $v->videoId ]);
                } else {
                    Log::info('数据', [ 'res' => $res[ 'code' ], 'videoId' => $v->videoId ]);
                }
            }
        })->everyMinute();

//        //todo： 测试代码无需修改
//        $schedule->call(function () {
//
//            //Redis::del('num');
//            $num = Redis::incr('cccc');
//            Log::info('User failed to login.', ['num' => $num]);
//            $pagesize = 200;
//            $page     = $num;
//            $offset   = ($page - 1) * $pagesize;
//            Log::info('User failed to login.', ['页数' => $page]);
//            Log::info('User failed to login.', ['条数' => $pagesize]);
//            $testt = DB::table("testt")->offset($offset)->limit($pagesize)->get()->toArray();
//            Log::info('User failed to login.', ['数据' => $testt]);
//            foreach ($testt as $k=>$v){
//                // $data['course_id'] = $v[0];
//                // $data['videoId'] = $v[1];
//                // $res = DB::table("testt")->insert($data);
//                $MTCloud = new MTCloud();
//                $res = $MTCloud->videoGet($v->videoId);
//                //dd($res);
//                if($res['code']  == 0){
//                    $test['mt_video_id'] = $res['data']['videoId'];
//                    $test['mt_video_name'] = $res['data']['title'];
//                    $test['mt_url'] = $res['data']['videoUrl'];
//                    $test['mt_duration'] = $res['data']['duration'];
//                    $test['resource_size'] = $res['data']['filesize'];
//                    $d = DB::table("test")->insert($test);
//                    Log::info('数据', ['res' => $d,'videoId'=>$v->videoId]);
//                }else{
//                    Log::info('数据', ['res' => $res['code'],'videoId'=>$v->videoId]);
//                }
//            }
//        })->everyMinute();


    }
}
