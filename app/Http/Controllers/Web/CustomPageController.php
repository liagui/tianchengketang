<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomPageConfig;
use App\Models\School;

class CustomPageController extends Controller {
	protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        $this->school = School::where(['dns'=>$this->data['dns']])->first();

    }

    /**
     * 自定义单页
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPageInfo()
    {

        $info = CustomPageConfig::query()
            ->where('school_id', $this->school->id)
            ->where('sign', $this->data['sign'])
            ->where('page_type', 1)
            ->where('is_del', 0)
            ->select('name', 'text', 'update_time')
            ->first();
        if (empty($info)) {
            return response()->json(['code'=>201,'msg'=>'没有记录','data'=>[]]);
        } else {
            return response()->json(['code'=>200,'msg'=>'success','data'=> $info->toArray()]);
        }
    }

    /**
     * 自定义单页
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContentInfo()
    {

        $info = CustomPageConfig::query()
            ->where('id', $this->data['id'])
            ->where('school_id', $this->school->id)
            ->where('page_type', 2)
            ->where('is_del', 0)
            ->select('name', 'text', 'update_time', 'parent_id')
            ->first();

        if (empty($info)) {
            return response()->json(['code'=>201,'msg'=>'没有记录','data'=>[]]);
        } else {
            $info = $info->toArray();

            $contentList = [];

            if ($info['parent_id'] > 0) {

                $contentList = CustomPageConfig::query()
                    ->where('school_id', $this->school->id)
                    ->where('parent_id', $info['parent_id'])
                    ->where('page_type', 2)
                    ->where('is_del', 0)
                    ->select('id', 'name', 'url', 'link_type', 'is_new_open', 'is_forbid')
                    ->get()
                    ->toArray();
            }
            return response()->json(['code'=>200,'msg'=>'success','data'=> ['info' => $info, 'list' => $contentList]]);
        }
    }


}
