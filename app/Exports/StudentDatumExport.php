<?php
namespace App\Exports;
use App\Models\School;
use App\Models\AdminLog;
use App\Models\Admin;
use App\Models\StudentDatum;
use App\Models\Course;
use App\Models\Region;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;
class StudentDatumExport implements FromCollection, WithHeadings {

    protected $where;
    protected $resultSetType = 'collection';
    public function __construct($invoices){
        $this->where = $invoices;
    }
    public function collection() {
        $body = $this->where;
        // DB::connection()->enableQueryLog();
        $Datum = StudentDatum::leftJoin('information','information.id','=','student_information.information_id')->where(function($query) use ($body) {
                    if(!empty($body['school_id'])){
                        $query->where('information.branch_school',$body['school_id']); //所属分校      
                    }
                    if(isset($body['subject']) && !empty($body['subject'])){
                        $query->where('student_information.project_id',$body['subject'][0]);
                        if(isset($body['subject'][1]) && $body['subject'][1] >0){
                            $query->where('student_information.subject_id',$body['subject'][1]);
                        }
                    }
                    $query->where('audit_status',1);//所属审核状态
                })->select('information.student_name','information.student_sex','information.student_phone','information.student_card','information.address_province_id','information.address_city_id','information.month','information.sign_region_province_id','information.sign_region_city_id','information.reference_region_province_id','information.reference_region_city_id','information.culture','information.graduated_school','information.professional','information.years','information.xx_account','information.xx_password','information.branch_school','information.photo','information.card_photo_front','information.card_photo_contrary','information.card_photo_scanning','information.diploma_photo','information.diploma_scanning')->get();
        
        $datumArr = [];
        if(!empty($Datum)){
            $adminArr = Admin::where(['is_del'=>1,'is_forbid'=>1])->select('id','real_name')->get()->toArray(); //学员id
            if(!empty($adminArr)){
                $adminArr  = array_column($adminArr,'real_name','id');
            }
            $courseArr = Course::where(['is_del'=>1])->select('id','course_name')->get()->toArray(); //课程
            if(!empty($courseArr)){
                $courseArr  = array_column($courseArr,'course_name','id');
            }
            $schoolArr = School::where(['is_del'=>0,'is_open'=>0])->select('id','school_name')->get()->toArray(); //学校id
            if(!empty($schoolArr)){
                $schoolArr  = array_column($schoolArr,'school_name','id');
            }
            $regionArr = Region::select('id','name')->get()->toArray(); //地区
            if(!empty($regionArr)){
                $regionArr  = array_column($regionArr,'name','id');
            }
            $cultureArr = ['初中','高中','大专','本科','研究生','博士','博士后'];
            foreach($Datum as $key=>&$v){
                $v['student_sex'] = $v['student_sex'] == 1?'女':'男';
                if(isset($regionArr[$v['address_province_id']]) && isset($regionArr[$v['address_city_id']]) ){
                    $v['address'] = $regionArr[$v['address_province_id']].'-'.$regionArr[$v['address_city_id']];
                }else{
                    $v['address'] = '';
                }
                if(isset($regionArr[$v['sign_region_province_id']]) && isset($regionArr[$v['sign_region_city_id']]) ){
                    $v['sign_region'] = $regionArr[$v['sign_region_province_id']].'-'.$regionArr[$v['sign_region_city_id']];
                }else{
                    $v['sign_region'] = '';
                }
                if(isset($regionArr[$v['reference_region_province_id']]) && isset($regionArr[$v['reference_region_city_id']]) ){
                    $v['reference_region'] = $regionArr[$v['reference_region_province_id']].'-'.$regionArr[$v['reference_region_city_id']];
                }else{
                    $v['reference_region'] = '';
                }
                $v['month'] = substr($v['month'],5,2).'月份';
                $v['culture']  = isset($cultureArr[$v['culture']])?$cultureArr[$v['culture']]:'';
                $v['years'] = substr($v['years'],0,7);
                $v['branch_school'] = isset($schoolArr[$v['branch_school']])?$schoolArr[$v['branch_school']]:'';
            }
    
            foreach($Datum as $kk=>$vv){
                $Datum[$kk]['student_name'] = $vv['student_name'];
                $Datum[$kk]['student_sex'] = $vv['student_sex'];
                $Datum[$kk]['student_phone'] = $vv['student_phone'];
                $Datum[$kk]['student_card'] = $vv['student_card'];
                $Datum[$kk]['address'] = $vv['address'];
                $Datum[$kk]['month'] = $vv['month'];
                $Datum[$kk]['sign_region'] = $vv['sign_region'];
                $Datum[$kk]['reference_region'] = $vv['reference_region'];
                $Datum[$kk]['culture'] = $vv['culture'];
                $Datum[$kk]['graduated_school'] = $vv['graduated_school'];
                $Datum[$kk]['professional'] = $vv['professional'];
                $Datum[$kk]['years'] = $vv['years'];
                $Datum[$kk]['xx_account'] = $vv['xx_account'];
                $Datum[$kk]['xx_password'] = $vv['xx_password'];
                $Datum[$kk]['branch_school'] = $vv['branch_school'];
                $Datum[$kk]['photo'] = $vv['photo'];
                $Datum[$kk]['card_photo_front'] = $vv['card_photo_front'];
                $Datum[$kk]['card_photo_contrary'] = $vv['card_photo_contrary'];
                $Datum[$kk]['card_photo_scanning'] = $vv['card_photo_scanning'];
                $Datum[$kk]['diploma_photo'] = $vv['diploma_photo'];
                $Datum[$kk]['diploma_scanning'] = $vv['diploma_scanning'];
                unset($vv['address_province_id']);
                unset($vv['address_city_id']);
                unset($vv['sign_region_province_id']);
                unset($vv['sign_region_city_id']);
                unset($vv['reference_region_province_id']);
                unset($vv['reference_region_city_id']);
            }
        }   
        
        return $Datum;
    }

    public function headings(): array
    {
        return ['学员','性别','手机号码','身份证号码','报考月份','文化程度','毕业学院','毕业专业','毕业年月','学信网账号','学信网密码','所属学校','2寸白底照片','身份证正面照片','身份证背面照片','身份证正反面扫描','毕业证照片','毕业证扫描件','户籍地区','报考地区','备考地区'];
        // return [
        //     '所属分校','学员姓名','报考地区','备考地区','学员姓名','学员性别','学员手机号','学员身份证号','户籍地址','报考月份','报考地区','备考地区','文化程度','毕业学院','毕业专业','毕业年月','学信网账号','学信网密码','2寸白底照片','身份证正面照片','身份证背面照片','身份证正反面扫描','毕业证照片','毕业证扫描件','本人手持身份证照片',
        // ];
    }
}
