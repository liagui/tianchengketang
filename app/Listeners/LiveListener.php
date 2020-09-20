<?php

namespace App\Listeners;

use App\Tools\MTCloud;
use App\Models\CourseLiveClassChild;
use App\Models\OpenLivesChilds;
use Log;

class LiveListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\ExampleEvent  $event
     * @return void
     */
    public function handler($cmd, $params)
    {
        if($cmd === 'live.start'){
            Log::info('直播开始:'.json_encode($params));
            $live = CourseLiveClassChild::where(['course_id' => $params['course_id']])->first();
            if(empty($live)){
                $live =  OpenLivesChilds::where(['course_id' => $params['course_id']])->first();//公开课
            }
            $live->status = 2;
            $live->save();

            $response = [
                'code'=> MTCloud::CODE_SUCCESS,
                'data'=> [
                    'role'=> MTCloud::ROLE_ADMIN
                ],
            ];
        }else if($cmd === 'live.stop'){
            Log::info('直播结束:'.json_encode($params));
            $live = CourseLiveClassChild::where(['course_id' => $params['course_id']])->first();
            if(empty($live)){
                $live =  OpenLivesChilds::where(['course_id' => $params['course_id']])->first(); //公开课
            }
            $live->status = 3;
            $live->save();
            $response = [
                'code'=> MTCloud::CODE_SUCCESS,
                'data'=> [
                    'role'=> MTCloud::ROLE_ADMIN
                ],
            ];
        }else if($cmd === 'live.playback'){
            Log::info('直播回放生成:'.json_encode($params));
            $live = CourseLiveClassChild::where(['course_id' => $params['course_id']])->first();
            if(empty($live)){
                $live =  OpenLivesChilds::where(['course_id' => $params['course_id']])->first();//公开课
            }
            $live->playback = 1;
            $live->playbackUrl = $params['url'];
            $live->duration = $params['duration'];
            $live->save();
            $response = [
                'code'=> MTCloud::CODE_SUCCESS,
                'data'=> [
                    'role'=> MTCloud::ROLE_ADMIN
                ],
            ];
        }else if($cmd === 'user.login'){
            $response = [
                'code'=> MTCloud::CODE_SUCCESS,
                'data'=> [
                    'role'=> MTCloud::ROLE_ADMIN
                ],
            ];
        }else if($cmd === 'video.convert'){
            Log::info('转码结束:'.json_encode($params));
            $response = [
                'code'=> MTCloud::CODE_SUCCESS,
                'data'=> [
                    'role'=> MTCloud::ROLE_ADMIN
                ],
            ];
        }


        return $response;
    }
}
