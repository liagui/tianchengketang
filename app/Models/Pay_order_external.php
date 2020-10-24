<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pay_order_external extends Model
{
    //指定别的表名
    public $table = 'pay_order_external';
    //时间戳设置
    public $timestamps = false;

    /*
         * @param  未支付列表
         * @param
         * @param  author  苏振文
         * @param  ctime   2020/9/9 10:32
         * return  array
         */
    public static function unpaidOrder($data){
        //科目id
        $where['pay_status'] = 0;
        //科目id&学科id
        if(!empty($data['project_id'])){
            $parent = json_decode($data['project_id'], true);
            $where['project_id'] = $parent[0];
            if(!empty($parent[1])){
                $where['subject_id'] = $parent[1];
            }
        }
        //每页显示的条数
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;

        //計算總數
        $count = self::where(function($query) use ($data) {
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
        })
        ->where($where)
        ->count();

        //数据
        $order = self::where(function($query) use ($data) {
            if(isset($data['order_no']) && !empty($data['order_no'])){
                $query->where('order_no',$data['order_no'])
                    ->orwhere('name',$data['order_no'])
                    ->orwhere('mobile',$data['order_no']);
            }
         })
        ->where($where)
        ->orderByDesc('id')
        ->offset($offset)->limit($pagesize)->get()->toArray();

        //循环查询分类
        if(!empty($order)){
            foreach ($order as $k=>&$v){
                if($v['pay_type'] == 1){
                    $v['pay_type_text'] = '支付宝扫码';
                }
                if($v['pay_type'] == 2){
                    $v['pay_type_text'] = '微信扫码';
                }
                if($v['pay_type'] == 3){
                    $v['pay_type_text'] = '银联快捷支付';
                }
                if($v['pay_type'] == 4){
                    $v['pay_type_text'] = '微信小程序';
                }
                if($v['pay_type'] == 5){
                    $v['pay_type_text'] = '线下录入';
                }
                //course  课程
                $course = Course::select('course_name')->where(['id'=>$v['course_id']])->first();
                $v['course_name'] = $course['course_name'];
                //Project  项目
                $project = Project::select('name')->where(['id'=>$v['project_id']])->first();
                $v['project_name'] = $project['name'];
                //Subject  学科
                $subject = Project::select('name')->where(['id'=>$v['subject_id']])->first();
                $v['subject_name'] = $subject['name'];
                if(!empty($v['education_id']) && $v['education_id'] != 0){
                    //查院校
                    $education = Education::select('education_name')->where(['id'=>$v['education_id']])->first();
                    $v['education_name'] = $education['education_name'];
                    //查专业
                    $major = Major::where(['id'=>$v['major_id']])->first();
                    $v['major_name'] = $major['major_name'];
                }
            }
        }
        $page=[
            'pagesize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        return ['code' => 200 , 'msg' => '查询成功','data'=>$order,'where'=>$data,'page'=>$page];
    }
}
