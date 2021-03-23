<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolBottomConfig;
use App\Models\SchoolConfig;
use App\Models\SchoolSeoConfig;

class ConfigController extends Controller {
    protected $school;
    protected $data;
    public function __construct(){
        $this->data = $_REQUEST;
        // $this->school = School::where(['dns'=>$this->data['school_dns']])->first();
        $this->school = School::where(['dns'=>$this->data['dns'],'is_del'=>1])->first(); //改前
        if(count($this->school)<=0){
             return ['code' => 201 , 'msg' => '该网校不存在,请联系管理员！'];exit;
        }
        // $this->school = School::where(['dns'=>$this->data['dns']])->first(); //改前
        //$this->school = $this->getWebSchoolInfo($this->data['dns']); //改后
    }

    /**
     * 获取使用版本 是否是新版
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVersion()
    {
        $total = SchoolConfig::query()
            ->where('school_id', $this->school->id)
            ->count();
        $isNewVersion = 2;
        if ($total > 0) {
            $isNewVersion = 1;
        }

        return response()->json(['code'=>200,'msg'=>'Success','data'=> ['is_new_version' => $isNewVersion]]);
    }

    /**
     * 首页
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIndex()
    {
        $indexConfig = SchoolConfig::query()
            ->where('school_id', $this->school->id)
            ->value('index_config');
        if (empty($indexConfig)) {
            $indexConfig = '';
        }

        return response()->json(['code'=>200,'msg'=>'Success','data'=> ['index_config' => $indexConfig]]);
    }

    /**
     * 头部
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTop()
    {
        $topConfig = SchoolConfig::query()
            ->where('school_id', $this->school->id)
            ->value('top_config');

        if (empty($topConfig)) {
            $topConfig = '';
        }
        return response()->json(['code'=>200,'msg'=>'Success','data'=> ['top_config' => $topConfig]]);
    }

    /**
     * 关于我们
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAbout()
    {
        $aboutConfig = SchoolConfig::query()
            ->where('school_id', $this->school->id)
            ->value('about_config');

        if (empty($aboutConfig)) {
            $aboutConfig = '';
        }
        return response()->json(['code'=>200,'msg'=>'Success','data'=> ['data' => $aboutConfig]]);
    }


    /**
     * 底部
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBottom()
    {
        $bottomTypeSelected = SchoolConfig::query()
            ->where('school_id', $this->school->id)
            ->value('bottom_type_selected');

        $bottomConfig = '';
        if (! empty($bottomTypeSelected)) {
            $bottomConfig = SchoolBottomConfig::query()
                ->where('school_id', $this->school->id)
                ->where('bottom_type', $bottomTypeSelected)
                ->value('bottom_config');

            if (empty($bottomConfig)) {
                $bottomConfig = '';
            }

        } else {
            $bottomTypeSelected = 0;
        }

        return response()->json(['code'=>200,'msg'=>'Success','data'=> ['bottom_type_selected' => $bottomTypeSelected, 'bottom_config' => $bottomConfig]]);
    }

    /**
     * 浏览器图标
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFavicon()
    {

        $faviconConfig = SchoolConfig::query()
            ->where('school_id', $this->school->id)
            ->select('is_forbid_favicon', 'favicon_config')
            ->first();
        if (empty($faviconConfig)) {
            $faviconConfig = [
                'is_forbid_favicon' => 1,
                'favicon_config' => ''
            ];
        } else {
            $faviconConfig = $faviconConfig->toArray();
        }

        return response()->json(['code'=>200,'msg'=>'Success','data'=> $faviconConfig]);
    }


    /**
     * 页面SEO配置
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPageSEO()
    {
        $seoConfigData = [
            'page_type' => '',
            'title' => '',
            'keywords' => '',
            'description' => '',
            'is_forbid' => 1
        ];

        if (empty($this->data['page_type'])) {
            return response()->json(['code'=>200,'msg'=>'Success','data'=> $seoConfigData]);
        } else {

            $seoConfig = SchoolSeoConfig::query()
                ->where('school_id', $this->school->id)
                ->where('page_type', $this->data['page_type'])
                ->select('page_type','title', 'keywords', 'description', 'is_forbid')
                ->first();
            if (empty($seoConfig)) {
                $seoConfigData['page_type'] = $this->data['page_type'];
            } else {
                $seoConfigData = $seoConfig->toArray();
            }
            return response()->json(['code'=>200,'msg'=>'Success','data'=> $seoConfigData]);
        }

    }

}
