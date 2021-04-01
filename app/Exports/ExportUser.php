<?php
namespace App\Exports;

use App\Models\School;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class ExportUser implements FromCollection, WithHeadings {
//学员统计导出
    protected $data;
    public function __construct($post,$school_status,$school_id){
        $this->data = $post;
        $this->school_status = $school_status;
        $this->school_id = $school_id;
    }
    public function collection()
    {
        //导出数据
        $body = $this->data;
        //获取分校的状态和id
        $school_status = $this->school_status;
        $school_id = $this->school_id;
        $returnarr = [];

        //判断是否是总校的状态
        if ($school_status > 0 && $school_status == 1) {
            //获取学员的总数量
            $student_count = self::where(function ($query) use ($body) {
                //判断报名状态是否选择
                if (isset($body['enroll_status']) && strlen($body['enroll_status']) > 0 && in_array($body['enroll_status'], [1, 2])) {
                    //已报名
                    if ($body['enroll_status'] > 0 && $body['enroll_status'] == 1) {
                        $query->where('enroll_status', '=', 1);
                    } else if ($body['enroll_status'] > 0 && $body['enroll_status'] == 2) {
                        $query->where('enroll_status', '=', 0);
                    }
                }
                //判断开课状态是否选择
                if (isset($body['state_status']) && strlen($body['state_status']) > 0 && in_array($body['state_status'], [0, 1, 2])) {
                    $state_status = $body['state_status'] > 0 ? $body['state_status'] : 0;
                    $query->where('state_status', '=', $state_status);
                }
                //判断账号状态是否选择
                if (isset($body['is_forbid']) && !empty($body['is_forbid']) && in_array($body['is_forbid'], [1, 2])) {
                    $query->where('is_forbid', '=', $body['is_forbid']);
                }
                //判断搜索内容是否为空
                if (isset($body['search']) && !empty($body['search'])) {
                    $query->where('real_name', 'like', '%' . $body['search'] . '%')->orWhere('phone', 'like', '%' . $body['search'] . '%');
                }
            })->whereIn('is_forbid', [1, 2])->count();

            //判断学员数量是否为空
            if ($student_count > 0) {
                //学员列表
                $student_list = Student::where(function ($query) use ($body) {
                    //判断报名状态是否选择
                    if (isset($body['enroll_status']) && strlen($body['enroll_status']) > 0 && in_array($body['enroll_status'], [1, 2])) {
                        //已报名
                        if ($body['enroll_status'] > 0 && $body['enroll_status'] == 1) {
                            $query->where('enroll_status', '=', 1);
                        } else if ($body['enroll_status'] > 0 && $body['enroll_status'] == 2) {
                            $query->where('enroll_status', '=', 0);
                        }
                    }
                    //判断开课状态是否选择
                    if (isset($body['state_status']) && strlen($body['state_status']) > 0 && in_array($body['state_status'], [0, 1, 2])) {
                        $state_status = $body['state_status'] > 0 ? $body['state_status'] : 0;
                        $query->where('state_status', '=', $state_status);
                    }

                    //判断账号状态是否选择
                    if (isset($body['is_forbid']) && !empty($body['is_forbid']) && in_array($body['is_forbid'], [1, 2])) {
                        $query->where('is_forbid', '=', $body['is_forbid']);
                    }
                    //判断搜索内容是否为空
                    if (isset($body['search']) && !empty($body['search'])) {
                        $query->where('real_name', 'like', '%' . $body['search'] . '%')->orWhere('phone', 'like', '%' . $body['search'] . '%');
                    }
                })->whereIn('is_forbid', [1, 2])->orderByDesc('create_at')->get()->toArray();
                foreach ($student_list as $k => $v) {
                    //根据学校id获取学校名称
                    $school_name = School::where('id', $v['school_id'])->value('name');
                    if($v['sex'] == 1){
                        $sextest = '男';
                    }else{
                        $sextest = '女';
                    }
                    if($v['papers_type'] == 1){
                        $papers_type_test = '身份证';
                    }else if($v['papers_type'] == 2){
                        $papers_type_test = '护照';
                    }else if($v['papers_type'] == 3){
                        $papers_type_test = '港澳通行证';
                    }else if($v['papers_type'] == 4){
                        $papers_type_test = '台胞证';
                    }else if($v['papers_type'] == 5){
                        $papers_type_test = '军官证';
                    }else if($v['papers_type'] == 6){
                        $papers_type_test = '士官证';
                    }else {
                        $papers_type_test = '其他';
                    }
                    if($v['educational'] ==1){
                        $educational_text = '小学';
                    }else if($v['educational'] ==2){
                        $educational_text = '初中';
                    }else if($v['educational'] ==3){
                        $educational_text = '高中';
                    }else if($v['educational'] ==4){
                        $educational_text = '大专';
                    }else if($v['educational'] ==5){
                        $educational_text = '本科';
                    }else if($v['educational'] ==6){
                        $educational_text = '研究生';
                    }else if($v['educational'] ==7){
                        $educational_text = '博士生';
                    }else {
                        $educational_text = '博士后';
                    }
                    $newarr = [
                        'phone' => $v['phone'],
                        'nickname' => $v['nickname'],
                        'real_name' => $v['real_name'],
                        'sex' => $sextest,
                        'school_name' => $school_name,
                        'papers_type' => $papers_type_test,
                        'papers_num' => $v['papers_num'],
                        'birthday' => $v['birthday'],
                        'address_locus' => $v['address_locus'],
                        'age' => $v['age'],
                        'educational' => $educational_text,
                        'family_phone' => $v['family_phone'],
                        'office_phone' => $v['office_phone'],
                        'contact_people' => $v['contact_people'],
                        'contact_phone' => $v['contact_phone'],
                        'email' => $v['email'],
                        'qq' => $v['qq'],
                        'wechat' => $v['wechat'],
                        'sheng' => '',
                        'shi' => '',
                        'xian' => '',
                        'address' => $v['address'],
                        'remark' => $v['remark'],
                    ];
                    $returnarr[] = $newarr;
                }
                return $returnarr;
            }
            return $returnarr;
        } else {
            //获取学员的总数量
            $student_count = Student::where(function ($query) use ($body) {
                //判断报名状态是否选择
                if (isset($body['enroll_status']) && strlen($body['enroll_status']) > 0 && in_array($body['enroll_status'], [1, 2])) {
                    //已报名
                    if ($body['enroll_status'] > 0 && $body['enroll_status'] == 1) {
                        $query->where('enroll_status', '=', 1);
                    } else if ($body['enroll_status'] > 0 && $body['enroll_status'] == 2) {
                        $query->where('enroll_status', '=', 0);
                    }
                }
                //判断开课状态是否选择
                if (isset($body['state_status']) && strlen($body['state_status']) > 0 && in_array($body['state_status'], [0, 1, 2])) {
                    $state_status = $body['state_status'] > 0 ? $body['state_status'] : 0;
                    $query->where('state_status', '=', $state_status);
                }

                //判断账号状态是否选择
                if (isset($body['is_forbid']) && !empty($body['is_forbid']) && in_array($body['is_forbid'], [1, 2])) {
                    $query->where('is_forbid', '=', $body['is_forbid']);
                }

                //判断搜索内容是否为空
                if (isset($body['search']) && !empty($body['search'])) {
                    $query->where('real_name', 'like', '%' . $body['search'] . '%')->orWhere('phone', 'like', '%' . $body['search'] . '%');
                }
            })->where('school_id', $school_id)->whereIn('is_forbid', [1, 2])->count();

            //判断学员数量是否为空
            if ($student_count > 0) {
                //学员列表
                $student_list = self::where(function ($query) use ($body) {
                    //判断报名状态是否选择
                    if (isset($body['enroll_status']) && strlen($body['enroll_status']) > 0 && in_array($body['enroll_status'], [1, 2])) {
                        //已报名
                        if ($body['enroll_status'] > 0 && $body['enroll_status'] == 1) {
                            $query->where('enroll_status', '=', 1);
                        } else if ($body['enroll_status'] > 0 && $body['enroll_status'] == 2) {
                            $query->where('enroll_status', '=', 0);
                        }
                    }
                    //判断开课状态是否选择
                    if (isset($body['state_status']) && strlen($body['state_status']) > 0 && in_array($body['state_status'], [0, 1, 2])) {
                        $state_status = $body['state_status'] > 0 ? $body['state_status'] : 0;
                        $query->where('state_status', '=', $state_status);
                    }

                    //判断账号状态是否选择
                    if (isset($body['is_forbid']) && !empty($body['is_forbid']) && in_array($body['is_forbid'], [1, 2])) {
                        $query->where('is_forbid', '=', $body['is_forbid']);
                    }

                    //判断搜索内容是否为空
                    if (isset($body['search']) && !empty($body['search'])) {
                        $query->where('real_name', 'like', '%' . $body['search'] . '%')->orWhere('phone', 'like', '%' . $body['search'] . '%');
                    }
                })->where('school_id', $school_id)->whereIn('is_forbid', [1, 2])->orderByDesc('create_at')->get()->toArray();
                foreach ($student_list as $k => $v) {
                    //根据学校id获取学校名称
                    $school_name = School::where('id', $v['school_id'])->value('name');
                    if($v['sex'] == 1){
                        $sextest = '男';
                    }else{
                        $sextest = '女';
                    }
                    if($v['papers_type'] == 1){
                        $papers_type_test = '身份证';
                    }else if($v['papers_type'] == 2){
                        $papers_type_test = '护照';
                    }else if($v['papers_type'] == 3){
                        $papers_type_test = '港澳通行证';
                    }else if($v['papers_type'] == 4){
                        $papers_type_test = '台胞证';
                    }else if($v['papers_type'] == 5){
                        $papers_type_test = '军官证';
                    }else if($v['papers_type'] == 6){
                        $papers_type_test = '士官证';
                    }else {
                        $papers_type_test = '其他';
                    }
                    if($v['educational'] ==1){
                        $educational_text = '小学';
                    }else if($v['educational'] ==2){
                        $educational_text = '初中';
                    }else if($v['educational'] ==3){
                        $educational_text = '高中';
                    }else if($v['educational'] ==4){
                        $educational_text = '大专';
                    }else if($v['educational'] ==5){
                        $educational_text = '本科';
                    }else if($v['educational'] ==6){
                        $educational_text = '研究生';
                    }else if($v['educational'] ==7){
                        $educational_text = '博士生';
                    }else {
                        $educational_text = '博士后';
                    }
                    $newarr = [
                        'phone' => $v['phone'],
                        'nickname' => $v['nickname'],
                        'real_name' => $v['real_name'],
                        'sex' => $sextest,
                        'school_name' => $school_name,
                        'papers_type' => $papers_type_test,
                        'papers_num' => $v['papers_num'],
                        'birthday' => $v['birthday'],
                        'address_locus' => $v['address_locus'],
                        'age' => $v['age'],
                        'educational' => $educational_text,
                        'family_phone' => $v['family_phone'],
                        'office_phone' => $v['office_phone'],
                        'contact_people' => $v['contact_people'],
                        'contact_phone' => $v['contact_phone'],
                        'email' => $v['email'],
                        'qq' => $v['qq'],
                        'wechat' => $v['wechat'],
                        'sheng' => '',
                        'shi' => '',
                        'xian' => '',
                        'address' => $v['address'],
                        'remark' => $v['remark'],
                    ];
                    $returnarr[] = $newarr;
                }
                return $returnarr;
            }
            return $returnarr;
        }
    }
    public function headings(): array{
        return [
            '手机号',
            '用户名',
            '姓名',
            '性别',
            '所属分校',
            '证件类型',
            '证件号码',
            '出生日期',
            '户口所在地',
            '年龄',
            '最高学历',
            '家庭电话号码',
            '办公号码',
            '紧急联系人',
            '紧急联系人电话',
            '邮箱',
            'QQ号',
            '微信',
            '省',
            '市',
            '县',
            '详细地址',
            '备注'
        ];
    }
}
