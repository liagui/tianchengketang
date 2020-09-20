<?php
namespace App\Exports;

use App\Models\AdminLog;
use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class OrderExport implements FromCollection, WithHeadings {

    protected $where;
    public function __construct($invoices){
        $this->where = $invoices;
    }
    public function collection() {
        //用户权限
        $role_id = isset(AdminLog::getAdminInfo()->admin_user->role_id) ? AdminLog::getAdminInfo()->admin_user->role_id : 0;
        //如果不是总校管理员，只能查询当前关联的网校订单
        if($role_id != 1){
            $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
            $data['school_id'] = $school_id;
        }
        $school_id = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;
        $count = Order::where(['school_id'=>$school_id])->count();
        if($count < 0){
            return '';
        }
        $data = $this->where;
        $order = Order::select('ld_order.order_number','ld_order.order_type','ld_order.price','ld_order.pay_status','ld_order.pay_type','ld_order.status','ld_order.create_at','ld_order.oa_status','ld_student.phone','ld_student.real_name','ld_school.name')
            ->leftJoin('ld_student','ld_student.id','=','ld_order.student_id')
            ->leftJoin('ld_school','ld_school.id','=','ld_order.school_id')
            ->where(function($query) use ($data) {
                if(isset($data['school_id']) && !empty($data['school_id'])){
                    $query->where('ld_student.school_id',$data['school_id']);
                }
                if(isset($data['status'])&& !empty($data['status'])){
                    $query->where('ld_order.status',$data['status']);
                }
                if(isset($data['order_number'])&& !empty($data['order_number'])){
                    $query->where('ld_order.order_number',$data['order_number']);
                }
                if((!empty($data['state_time'])?$data['state_time']:"1999-01-01 12:12:12") && (!empty($data['end_time'])?$data['end_time']:"2999-01-01 12:12:12")){
                    $query->whereBetween('ld_order.create_at', [$data['state_time'], $data['end_time']]);
                }
            })
            ->orderByDesc('ld_order.id')
            ->get();
        foreach ($order as $k=>$v){
            //订单录入状态
            if($v['order_type'] == 1){
                $v['order_type'] = '后台录入';
            }else if($v['order_type'] == 2){
                $v['order_type'] = '在线支付';
            }
            //订单支付状态
            if($v['pay_status'] == 1){
                $v['pay_status'] = '定金';
            }else if($v['pay_status'] == 2){
                $v['pay_status'] = '尾款';
            }else if($v['pay_status'] == 3){
                $v['pay_status'] = '最后一次尾款';
            }else if($v['pay_status'] == 4){
                $v['pay_status'] = '全款';
            }
            //订单支付类型
            if($v['pay_type'] ==1){
                $v['pay_type'] = '微信支付';
            }else if($v['pay_type'] == 2){
                $v['pay_type'] = '支付宝支付';
            }
            //订单支付状态
            if($v['status'] ==0){
                $v['status'] = '未支付';
            }else if($v['status'] == 1){
                $v['status'] = '支付待审核';
            }else if($v['status'] == 2){
                $v['status'] = '审核通过';
            }else if($v['status'] == 4){
                $v['status'] = '已退款';
            }
            //订单oa状态
            if($v['oa_status'] == 1){
                $v['oa_status'] = '成功';
            }else if($v['status'] == 0){
                $v['oa_status'] = '失败';
            }
        }
        return $order;
    }

    public function headings(): array
    {
        return [
            '订单编号',
            '订单类型',
            '支付金额',
            '支付类型',
            '支付方式',
            '支付状态',
            '下单时间',
            'OA状态',
            '账号',
            '学员姓名',
            '网校名称',
        ];
    }
}
