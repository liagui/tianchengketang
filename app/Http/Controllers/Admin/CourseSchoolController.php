<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\CourseSchool;
class CourseSchoolController extends Controller {

	/**
     * @param  授权课程ID
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/6/29
     * @return  array  7.4 调整
     */
    public function courseIdList(){
    	$data = self::$accept_data;
    	$validator = Validator::make($data,
        [
        	'school_id' => 'required|integer',
            'is_public' => 'required|integer',
       	],
        CourseSchool::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $result = CourseSchool::courseIds(self::$accept_data);
        return response()->json($result);
    }
    /**
     * @param  授权课程列表
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/6/30
     * @return  array  7.4 调整
     */
    public function courseList(){
        $validator = Validator::make(self::$accept_data,
        [
            'school_id' => 'required|integer',
            'is_public' => 'required|integer' //是否为公开课  1公开课 0课程
        ],
        CourseSchool::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $result = CourseSchool::courseList(self::$accept_data);
        return response()->json($result);
    }
    /**
     * @param  批量授权添加课程
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/6/30
     * @return  array 7.4调整
     */
    public function store()
    {
        $validator = Validator::make(self::$accept_data,
        [
            'course_id' => 'required',
            'school_id' => 'required',
            'is_public' => 'required',
        ],
        CourseSchool::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }

        $result = CourseSchool::store(self::$accept_data);
        return response()->json($result);
    }
    /**
     * @param  批量取消授权课程
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/6/30
     * @return  array 7.4调整
     */
    public function courseCancel()
    {
        $validator = Validator::make(self::$accept_data,
        [
            'course_id' => 'required',
            'school_id' => 'required',
            'is_public' => 'required',
        ],
        CourseSchool::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }

        $result = CourseSchool::courseCancel(self::$accept_data);
        return response()->json($result);
    }

    /**
     * @param  授权更新
     * @param  school_id
     * @param  author  李银生
     * @param  ctime   2020/7/27
     * @return  array
     */
    public function authorUpdate()
    {
        $validator = Validator::make(self::$accept_data,
        [
            'is_public' => 'required',
            'school_id' => 'required',
        ],
        CourseSchool::message());
        if($validator->fails()) {
            return response()->json(json_decode($validator->errors()->first(),1));
        }
        $result = CourseSchool::authorUpdate(self::$accept_data);
        return response()->json($result);
    }







     /*
     * @param  description 授权课程列表学科大类
     * @param  参数说明       body包含以下参数[
     *      'id'=>学科id
     * ]
     * @param author    lys
     * @param ctime     2020-05-11
     */
     public function getNatureSubjectOneByid(){

            $validator = Validator::make(self::$accept_data,
                [
                    'school_id' => 'required|integer',
                    'is_public'=> 'required|integer',
                ],
                CourseSchool::message());
            if ($validator->fails()) {
                return response()->json(json_decode($validator->errors()->first(),1));
            }
            $result = CourseSchool::getNatureSubjectOneByid(self::$accept_data);
            return response()->json($result);
    }
    /*
     * @param  description 授权课程列表学科小类
     * @param  参数说明       body包含以下参数[
     *      'subjectOne'=>学科id
     * ]
     * @param author    lys
     * @param ctime     2020-7-4
     */
     public function getNatureSubjectTwoByid(){
            $validator = Validator::make(self::$accept_data,
                [
                    'subjectOne' => 'required',
                ],
                CourseSchool::message());
            if ($validator->fails()) {
                return response()->json(json_decode($validator->errors()->first(),1));
            }
            $result = CourseSchool::getNatureSubjectTwoByid(self::$accept_data);
            return response()->json($result);
    }



}
