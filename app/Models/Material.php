<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\MaterialListing;
use App\Models\Teacher;
use App\Models\School;
use App\Models\Category;
use App\Models\Course;

class Material extends Model {
    //指定别的表名
    public $table = 'material';
    //时间戳设置
    public $timestamps = false;

    public static function getMaterialList($data,$school_id){
        //总校
        //未处理物料条数
        $nocount = self::select('submit_time', 'create_name', 'school_id', 'status','id',"material.submit_name")->whereIn('material.school_id',$school_id['data'])->where('status',0)->count();
        //分校

        //每页显示的条数
        $pagesize = (int)isset($data['pagesize']) && $data['pagesize'] > 0 ? $data['pagesize'] : 20;
        $page     = isset($data['page']) && $data['page'] > 0 ? $data['page'] : 1;
        $offset   = ($page - 1) * $pagesize;
        //计算总数
        $count = self::select('material.submit_time', 'material.create_name', 'material.school_id', 'material.status', 'material.id',"material.submit_name")->where(function($query) use ($data,$school_id) {

            if(isset($data['school_id']) && !empty($data['school_id'])){
                $query->where('material.school_id',$data['school_id']);
            }else{
                $query->whereIn('material.school_id',$school_id['data']);
            }
            if(isset($data['status']) && $data['status'] != -1){
                $query->where('material.status',$data['status']);
            }
            if(isset($data['create_name']) && !empty($data['create_name'])){
                $query->where('material.create_name',$data['create_name']);
            }
        })->count();
        //分页数据
        $data = self::select('material.submit_time', 'material.create_name','create_name','create_id','material.school_id', 'material.status', 'material.id','material.courier_company','material.courier_number','material.courier_note','material.delivery_time')->where(function($query) use ($data,$school_id) {
            if(isset($data['school_id']) && !empty($data['school_id'])){
                $query->where('material.school_id',$data['school_id']);
            }else{
                $query->whereIn('material.school_id',$school_id['data']);
            }
            if(isset($data['status']) && $data['status'] != -1){
                $query->where('material.status',$data['status']);
            }
            if(isset($data['create_name']) && !empty($data['create_name'])){
                $query->where('material.create_name',$data['create_name']);
            }
        })->offset($offset)->limit($pagesize)->orderByDesc("id")->get()->toArray();

        $school_name = "";
        foreach($data as $key =>&$material){
            $desc = "";
            if($material['status'] == 1){
                $material['status_desc'] = "快递公司：".$material['courier_company']."-快递单号：".$material['courier_number']."-快递备注：".$material['courier_note']."-邮寄时间：".$material['delivery_time'];
            }
            $res = MaterialListing::where('material_id',$material['id'])->get()->toArray();
            foreach($res as $k => $v){

                if($v['material_type'] == 1){
                    $desc.= $v['project_name']."-".$v['subject_name']."-".$v['course_name']."-合同"."*".$v['contract_number'];
                }else if($v['material_type'] == 2){
                    $desc.= $v['invoice']."-".$v['invoice_price']."-发票"."*".$v['invoice_number'];
                }else{
                    $desc.= $v['receipt_desc']."-收据"."*".$v['receipt_number'];
                }
            }
            $material['desc'] = $desc;
            $school_name = School::select("school_name")->where("id",$material['school_id'])->first();
            $material['school_name'] = $school_name['school_name'];

            if($material['status'] == 1){
                $material['status_s'] = "已确认";
            }else{
                $material['status_s'] = "未确认";
            }

        }

        $page=[
            'pageSize'=>$pagesize,
            'page' =>$page,
            'total'=>$count
        ];
        if($data){
            return ['code' => 200, 'msg' => '查询成功', 'data' => $data,'page'=>$page,'nocount'=>$nocount];
        }else{
            return ['code' => 200, 'msg' => '查询暂无数据', 'data' => [],'page'=>$page,'nocount'=>0];
        }

    }
    public static function Materialadd($data){
        unset($data['/admin/Materialadd']);
        //获取登录id
        $admin = isset(AdminLog::getAdminInfo()->admin_user) ? AdminLog::getAdminInfo()->admin_user: [];
        $material = [
            //物料发起人id
            'create_id' => $admin['id'],
            //物料发起人名称
            'create_name' =>$admin['username'],
            //物料需求提交时间
            'submit_time' => date("Y-m-d H:i:s",time()),//$data['submit_time'],
            //分校id
            'school_id' => $data['school_id'],
        ];
        $res = json_decode($data['data'],true);
        $material_id = self::insertGetId($material);
        foreach($res as $key=>$material_list){
            if($material_list['type']  == 1){
                $material_listing1['material_id'] = $material_id;
                $material_listing1['material_type'] = 1;
                $material_listing1['project_id'] = $material_list['project_id'];
                $material_listing1['project_name'] = Category::select("name")->where("id",$material_list['project_id'])->first()['name'];
                $material_listing1['subject_id'] = $material_list['subject_id'];
                $material_listing1['subject_name'] = Category::select("name")->where("id",$material_list['subject_id'])->first()['name'];
                $material_listing1['course_id'] = $material_list['course_id'];
                $material_listing1['course_name'] = Course::select("course_name")->where("id",$material_list['course_id'])->first()['course_name'];
                $material_listing1['contract_number'] = $material_list['contract_number'];
                $res = MaterialListing::insert($material_listing1);
            }else if($material_list['type']  == 2){
                $material_listing2['material_id'] = $material_id;
                $material_listing2['invoice'] = $material_list['invoice'];
                $material_listing2['invoice_number'] = $material_list['invoice_number'];
                $material_listing2['invoice_price'] = $material_list['invoice_price'];
                $material_listing2['material_type'] = 2;
                $res = MaterialListing::insert($material_listing2);
            }else{
                $material_listing3['material_id'] = $material_id;
                $material_listing3['receipt_desc'] = $material_list['receipt_desc'];
                $material_listing3['receipt_number'] = $material_list['receipt_number'];
                $material_listing3['material_type'] = 3;
                $res = MaterialListing::insert($material_listing3);
            }
        }
        if($res){
            return ['code' => 200 , 'msg' => '创建物料需求成功'];
        }else{
            return ['code' => 202 , 'msg' => '创建物料需求失败'];
        }
    }
    //更新单条信息
    public static function updateMaterialOne($data){
        unset($data['/admin/updateMaterialOne']);
        $material_id = $data['material_id'];
        $res = json_decode($data['data'],true);
        foreach($res as $key=>$material_list){
            if($material_list['type']  == 1){
                $material_listing1['material_id'] = $material_id;
                $material_listing1['material_type'] = 1;
                $material_listing1['project_id'] = $material_list['project_id'];
                $material_listing1['project_name'] = Category::select("name")->where("id",$material_list['project_id'])->first()['name'];
                $material_listing1['subject_id'] = $material_list['subject_id'];
                $material_listing1['subject_name'] = Category::select("name")->where("id",$material_list['subject_id'])->first()['name'];
                $material_listing1['course_id'] = $material_list['course_id'];
                $material_listing1['course_name'] = Course::select("course_name")->where("id",$material_list['course_id'])->first()['course_name'];
                $material_listing1['contract_number'] = $material_list['contract_number'];
                $res = MaterialListing::where(["material_id"=>$material_id,"material_type"=>1])->update($material_listing1);
            }else if($material_list['type']  == 2){
                $material_listing2['material_id'] = $material_id;
                $material_listing2['invoice'] = $material_list['invoice'];
                $material_listing2['invoice_number'] = $material_list['invoice_number'];
                $material_listing2['invoice_price'] = $material_list['invoice_price'];
                $material_listing2['material_type'] = 2;
                $res = MaterialListing::where(["material_id"=>$material_id,"material_type"=>2])->update($material_listing2);
            }else{
                $material_listing3['material_id'] = $material_id;
                $material_listing3['receipt_desc'] = $material_list['receipt_desc'];
                $material_listing3['receipt_number'] = $material_list['receipt_number'];
                $material_listing3['material_type'] = 3;
                $res = MaterialListing::where(["material_id"=>$material_id,"material_type"=>3])->update($material_listing3);
            }
        }
        if($res){
            return ['code' => 200 , 'msg' => '更新物料需求成功'];
        }else{
            return ['code' => 202 , 'msg' => '更新物料需求失败'];
        }
    }
    //获取单条物料
    public static function getMaterialOne($data){
        $material_id = $data['material_id'];
        $res = MaterialListing::where("material_id",$material_id)->get();
        if($res){
            return ['code' => 200, 'msg' => '查询成功', 'data' => $res];
        }else{
            return ['code' => 200, 'msg' => '查询暂无数据', 'data' => []];
        }
    }
    //更新物料
    public static function Materialupdate($data){
        unset($data['/admin/Materialupdate']);
        //获取登录id
        $admin = isset(AdminLog::getAdminInfo()->admin_user) ? AdminLog::getAdminInfo()->admin_user: [];
        //物料id
        $update = [
            //快递公司
            'courier_company' => $data['courier_company'],
            //快递单号
            'courier_number' => $data['courier_number'],
            //快递备注
            'courier_note' => $data['courier_note'],
            //邮寄时间
            'delivery_time' => date("Y-m-d H:i:s",strtotime($data['delivery_time'])),
            //物料提交状态0未确认1已确认
            'status' => 1,
            //物料提交人id
            'submit_id' => $admin['id'],
            //物料提交人姓名
            'submit_name' => $admin['username'],
            //物料提交时间
            'status_time' => date("Y-m-d H:i:s",time()),
        ];
        $res = self::where('id',$data['id'])->update($update);
        if($res){
            return ['code' => 200 , 'msg' => '确认物料信息成功'];
        }else{
            return ['code' => 202 , 'msg' => '确认物料信息失败'];
        }
    }

    public static function getsubmit($data){
        //用户系统完善
        $res = Teacher::select("real_name","mobile","wx")->where("id",$data['submit_id'])->first();
        if($res){
            return ['code' => 200, 'msg' => '查询成功', 'data' => $res];
        }else{
            return ['code' => 200, 'msg' => '查询暂无数据', 'data' => []];
        }
    }
    //获取确认物料信息
    public static function getMaterial($data){
        $res = self::where('id',$data['id'])->first();
        if($res){
            return ['code' => 200, 'msg' => '查询成功', 'data' => $res];
        }else{
            return ['code' => 200, 'msg' => '查询暂无数据', 'data' => []];
        }
    }

}
