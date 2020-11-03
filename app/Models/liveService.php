<?php
namespace App\Models;

use App\Models\AdminLog;
use App\Models\CourseStocks;
use App\Models\SchoolOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Log;

/**
 * 直播商管理
 * @author laoxian
 */
class liveService extends Model {
    //指定别的表名   权限表
    public $table = 'ld_live_service';
    //时间戳设置
    public $timestamps = false;

    protected $fillable = [
        'id','name','isshow','short','create_at'
    ];

    protected $hidden = [
        'delete_at'
    ];

    //可批量修改字段
    protected static $multiFields = [
        'delete_at','isshow'
    ];

    //错误信息
    public static function message()
    {
        return [
            'name.required'  => json_encode(['code'=>'201','msg'=>'直播服务商名称不能为空']),
            'isshow.integer'   => json_encode(['code'=>'202','msg'=>'状态参数不合法']),
        ];
    }

    //批量库存错误信息
    public static function stocksMessage()
    {
        return [
            'course_id.required'  => json_encode(['code'=>'201','msg'=>'课程不能为空']),
            'add_number.required'   => json_encode(['code'=>'202','msg'=>'库存数不能为空'])
        ];
    }

    /**
     * 添加
     * @param [
     *  name string 直播商家名称
     *  isshow int 1=可用,2=不可用
     *  short string 描述
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function add($params)
    {
        //补充参数并添加
        $lastid = self::insertGetId($params);
        if(!$lastid){
            return ['code'=>203,'msg'=>'添加失败, 请重试'];
        }

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/insert' ,
            'operate_method' =>  'insert' ,
            'content'        =>  '新增数据'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code'=>200,'msg'=>'success'];//成功
    }

    /**
     * 查看记录
     * @param [
     *  page int 页码
     *  pagesize int 页大小
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function getlist($params)
    {
        $page = (int) (isset($params['page']) && $params['page'])?$params['page']:1;
        $pagesize = (int) (isset($params['pagesize']) && $params['pagesize'])?$params['pagesize']:15;

        //固定条件
        $whereArr = [
            'delete_at'=>null
        ];

        //搜索条件
        if(isset($params['name']) && $params['name']){
            $whereArr[] = ['name','like','%'.$params['name'].'%'];
        }

        //总数
        $total = self::where($whereArr)->count();
        //结果集
        $list = self::where($whereArr)->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
        $data = [
            'total'=>$total,
            'list'=>$list
        ];
        return ['code'=>200,'msg'=>'success','data'=>$data];
    }

    /**
     * 获取单条
     * @param id int id标识
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function detail($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }

        $row = self::where('id',$id)->first();
        return ['code'=>200,'msg'=>'success','data'=>$row];
    }

    /**
     * 修改
     * @param [
     *  name string 直播商家名称
     *  isshow int 1=可用,2=不可用
     *  short string 描述
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function doedit($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->update($params);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/doedit' ,
            'operate_method' =>  'doedit' ,
            'content'        =>  '修改'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success,影响了'.$row.'行'];
    }

    /**
     * 删除
     * @param id int
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function dodelete($params){
        $id = isset($params['id'])?$params['id']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->update(['delete_at'=>time()]);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/delete' ,
            'operate_method' =>  'delete' ,
            'content'        =>  '删除数据'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success,影响了'.$row.'行'];
    }

    /**
     * 批量更新
     * @param ids string 逗号连接的id字符串
     * @param param string 字段
     * @param value string 值
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function domulti($params){
        $ids = isset($params['ids'])?$params['ids']:die();
        $key = isset($params['params'])?$params['params']:die();
        $value = isset($params['value'])?$params['value']:die();

        if(!in_array($key,self::$multiFields)){
            return ['code'=>201,'msg'=>'不合法的key'];
        }

        //整理
        $idarr = explode(',',$ids);
        $value = $key=='delete_at'?time():$value;

        $i = self::whereIn('id',$idarr)->update([$key=>$value]);
        /*foreach($idarr as $a){
            if(is_numeric($a)){
                $res = self::where('id',$id)->update([$key=>$value]);
                $i+=$res;
            }
        }*/
        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/domulti' ,
            'operate_method' =>  'update' ,
            'content'        =>  '修改数据'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);
        return ['code'=>200,'msg'=>'success,影响了'.$i.'行'];
    }

    /**
     * 修改网校直播商
     * @param [
     *  schoolid int 网校
     *  liveid int 直播类别
     * ]
     * @author laoxian
     * @time 2020/10/19
     * @return array
     */
    public static function updateLivetype($params){
        $id = isset($params['liveid'])?$params['liveid']:die();
        if(!is_numeric($id)){
            return ['code'=>205, 'msg'=>'参数不合法'];
        }
        $row = self::where('id',$id)->value('id');
        if(!$row){
            return ['code'=>202,'msg'=>'找不到当前直播信息'];
        }

        //执行修改
        $res = School::where('id',$params['schoolid'])->update(['livetype'=>$id]);

        //操作日志
        $admin_id = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;
        AdminLog::insertAdminLog([
            'admin_id'       =>  $admin_id ,
            'module_name'    =>  'liveService' ,
            'route_url'      =>  'admin/liveService/updateLivetype' ,
            'operate_method' =>  'update' ,
            'content'        =>  '修改数据'.json_encode($params) ,
            'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
            'create_at'      =>  date('Y-m-d H:i:s')
        ]);

        return ['code'=>200,'msg'=>'success'];
    }

    /**
     * 批量添加库存
     * @author laoxian
     * @return array
     */
    public static function doaddStocks($params)
    {
        //方法1, 顺序执行, 遇到错误停止, 并返回成功几行
        //方法2, 顺序执行, 遇到错误继续进行, 返回错误行
        //方法3, 使用事务, 遇到错误停止, 成功后插入订单表 √
        $params['admin_id'] = isset(AdminLog::getAdminInfo()->admin_user->id) ? AdminLog::getAdminInfo()->admin_user->id : 0;//当前登录账号id
        $params['school_pid'] = isset(AdminLog::getAdminInfo()->admin_user->school_id) ? AdminLog::getAdminInfo()->admin_user->school_id : 0;//当前登录学校id
        $params['school_id'] = $params['schoolid'];
        unset($params['schoolid']);
        //可用测试信息, 学校3, 已出售课程,75,63,135,12,63,52 //授权表,282,285,286,293,296
        $courseArr = explode(',',$params['course_id']);
        $stocksArrs = explode(',',$params['add_number']);
        if(count($courseArr)!=count($stocksArrs)){
            return ['code'=>203,'msg'=>'课程数目不等库存数'];
        }

        //将库存转换为授权id=>库存
        $stocksArr = [];
        foreach($stocksArrs as $k=>$v){
            $stocksArr[$courseArr[$k]] = $v;
        }
        $params['course_id'] = 0;//
        $params['add_number'] = 0;//
        $params['is_del'] = 1;//预定义不可用状态, 待审核通过后改为正常状态, is_del = 0;
        $params['is_forbid'] = 1;//预定义不可用状态, 待审核通过后改为正常状态, is_forbid = 0;

        //整理课程的真实课程id 与 总库存 与 销售量
        $record = self::courseStocksRecord($courseArr,$params['school_id'],$params['school_pid']);
        $courseidArr = $record['courseidArr'];//课程id组,授权id=>真实课程id
        $sum_current_numberArr = $record['sum_current_numberArr'];//课程总库存组
        $residue_numberArr = $record['residue_numberArr'];//课程销量组
        $priceArr = $record['prices'];//课程授权价格

        $oid = SchoolOrder::generateOid();
        $params['oid'] = $oid;

        try {
            //遍历添加库存 待计算金额
            $course_stocks_tmp = [];//储存可入库的库存二维数组
            $money = 0;//预定于订单总价
            foreach($courseidArr as $k=>$v){
                if((int)$stocksArr[$k] == 0){
                    return ['code'=>204,'msg'=>'添加库存数不能为0'];
                }

                //得到当前课程已销售数目 与 总库存
                $residue_number = isset($residue_numberArr[$v])?$residue_numberArr[$v]:0;
                $sum_current_number = isset($sum_current_numberArr[$v])?$sum_current_numberArr[$v]:0;
                //当前课程
                $params['current_number'] = $residue_number<=0 ?$sum_current_number:(int)$sum_current_number-(int)$residue_number;
                if((int)$params['current_number']+(int)$params['add_number'] <0){
                    return ['code'=>205,'msg'=>'添加库存数不能小于剩余库存数'];
                }
                $params['create_at'] = date('Y-m-d H:i:s');
                $params['course_id'] = $v;//课程
                $params['add_number'] = $stocksArr[$k];//本次添加库存数目
                $params['price'] = $priceArr[$v];//授权单价
                $money += $params['price']*$params['add_number'];//单课程的 价格*数量
                $course_stocks_tmp[] = $params;
                //$res = courseStocks::insert($params);
                //拼接执行成功的行
                //$result .= $res?($k+1).',':'';
                //$msg = $return?' ,第'.$result.'行添加成功':'';
            }
            //开启事务
            DB::beginTransaction();

            $res = courseStocks::insert($course_stocks_tmp);
            if(!$res){
                DB::rollBack();
                return ['code'=>206,'msg'=>'网络错误,请重试！'];
            }

            //遍历添加库存表完成(is_del=1,未生效的库存), 执行订单入库
            $order = [
                'oid' => $oid,
                'school_id' => $params['school_id'],
                'admin_id' => $params['admin_id'],
                'type' => 7,//批量添加库存
                'paytype' => 1,//内部支付
                'status' => 1,//待审核
                'online' => 0,//线下订单
                'money' => $money,
                'apply_time' => date('Y-m-d H:i:s')
            ];
            $lastid = SchoolOrder::doinsert($order);
            if(!$lastid){
                DB::rollBack();
                return ['code'=>208,'msg'=>'网络错误, 请重试'];
            }
            DB::commit();

            //将传输数据恢复原始模样插入日志表
            $parsms['course_id'] = implode(',',$courseArr);
            $parsms['add_number'] = implode(',',$stocksArrs);
            AdminLog::insertAdminLog([
                'admin_id'       =>  $params['admin_id'] ,
                'module_name'    =>  'SchoolCourseData' ,
                'route_url'      =>  'admin/SchoolCourseData/addMultiStocks' ,
                'operate_method' =>  'insert',
                'content'        =>  '批量添加库存'.json_encode($params),
                'ip'             =>  $_SERVER["REMOTE_ADDR"] ,
                'create_at'      =>  date('Y-m-d H:i:s')
            ]);
            Log::info('批量库存_库存表'.json_encode($course_stocks_tmp));
            return ['code'=>200,'msg'=>'添加成功'];

        }catch(\Exception $e){
            DB::rollback();
            return ['code'=>207,'msg'=>$e->getMessage()];
        }
    }

    /**
     * 获取课程库存
     * @param $courseArr array 授权课程id组
     * @param $school_id int 学校
     * @param $school_pid int 发起授权学校
     * @author laoxian
     * @TODO 有代码注释, 待打开
     * @return array
     */
    public static function courseStocksRecord($courseArr,$school_id,$school_pid)
    {

        //前端传的course_id 为ld_course_school自增id// 查询以授权id为key, courseid为value的数组
        $courseidArr = CourseSchool::whereIn('id',$courseArr)->pluck('course_id','id')->toArray();
        //print_r($courseidArr);die();

        //当前已经添加总库存
        $sum_current_numberArr = [];
        $sum_current_number = courseStocks::whereIn('course_id',$courseidArr)
            ->where('school_id',$school_id)
            //->where(['school_pid'=>$school_pid,'is_del'=>0])
            ->groupBy('course_id')->orderBy('id','desc')
            ->select(DB::raw("course_id,sum(add_number) as total_stocks"))->get()->toArray();
        //课程=>总库存
        foreach($sum_current_number as $k=>$v){
            $sum_current_numberArr[$v['course_id']] = $v['total_stocks'];
        }

        //购买量
        $residue_numberArr = [];
        $residue_numbers = Order::whereIn('class_id',$courseidArr)
            ->where('pay_status',[3,4])//尾款 or 全款
            //->where(['school_id'=>$school_id,'oa_status'=>1,'nature'=>1,'status'=>2])
            ->groupBy('class_id')
            ->select(DB::raw("class_id,count(id) as used_stocks"))->get()->toArray();

        //课程=>购买量
        foreach($residue_numbers as $k=>$v){
            $residue_numberArr[$v['class_id']] = $v['used_stocks'];
        }

        //天成单价 = 授权单价
        $priceArr = Coures::whereIn('id',$courseidArr)->pluck('impower_price as price','id')->toArray();

        return [
            'sum_current_numberArr'=>$sum_current_numberArr,
            'residue_numberArr'=>$residue_numberArr,
            'courseidArr'=>$courseidArr,
            'prices'=>$priceArr,
        ];
    }



}