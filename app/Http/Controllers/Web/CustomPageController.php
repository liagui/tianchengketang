<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomPageConfig;
use App\Models\School;
use App\Models\WebLog;


class CustomPageController extends Controller {
	protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        // $this->school = School::where(['dns'=>$this->data['dns']])->first();//改前
        $this->school = School::where(['dns'=>$this->data['dns'],'is_del'=>1])->first(); //改前
        if(count($this->school)<=0){
             return ['code' => 201 , 'msg' => '该网校不存在,请联系管理员！'];exit;
        }
        //$this->school = $this->getWebSchoolInfo($this->data['dns']); //改后
    }

    /**
     * 自定义页面 - 自定义页面
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPageInfo()
    {

        $info = CustomPageConfig::query()
            ->where('school_id', $this->school->id)
            ->where('sign', $this->data['sign'])
//            ->where('page_type', 1)
            ->where('is_del', 0)
            ->select('id', 'name', 'text', 'update_time', 'parent_id', 'page_type', 'custom_type', 'title')
            ->first();


        if (empty($info)) {
            return response()->json(['code'=>201,'msg'=>'没有记录','data'=>[]]);
        } else {

            //子页面列表
            $childList = [];
            //自定义页面 - 精确搜索
            if ($info->page_type == 1 && $info->custom_type == 2) {
                if ($info->parent_id > 0) {
                    return response()->json(['code'=>201,'msg'=>'没有记录','data'=>[]]);
                } else {
                    $childList = CustomPageConfig::query()
                        ->where('school_id', $this->school->id)
                        ->where('parent_id', $info->id)
                        ->where('page_type', $info->page_type)
                        ->where('custom_type', $info->custom_type)
                        ->where('is_del', 0)
                        ->get()
                        ->toArray();
                }
            }

            $return = $info->toArray();
            //附加子页面
            $return['child_list'] = $childList;

            return response()->json(['code'=>200,'msg'=>'success','data'=> $return]);
        }
    }

    /**
     * 自定义页面 - 内容管理
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
