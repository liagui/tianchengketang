<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\QuestionSubject as Subject;
use App\Models\Chapters;
use App\Models\School;
use App\Models\Teach;
use Illuminate\Support\Facades\DB;

class UpdateListSortController extends Controller {

    protected $model;
    protected $data;

    public function __construct(){
        $this->data = $_REQUEST;  //获取参数
        $this->model = $this->getModel($this->data['m']); //获取数据表
    }

    /*
     * @param  description   修改数据排序
     * @param  参数说明       body包含以下参数[
     *      id                数据表id， [1、2、3、4... ....]
     *      m                 数据表
     * ]
     * @param author    sxh
     * @param ctime     2020-10-22
     * return string
     */
    public function doUpdateListSort(){
        //验证参数,id
        $db_id = $this->validator();
        $model = $this->model;
        //print_r($db_id);die();
        try{
            $res = Chapters::doUpdateListSort($db_id);
            if($res['code'] == 200){
                return response()->json(['code' => 200 , 'msg' => '成功']);
            } else {
                return response()->json(['code' => 201 , 'msg' => '失败']);
            }
        } catch (Exception $ex) {
            return response()->json(['code' => 500 , 'msg' => $ex->getMessage()]);
        }
        //return $res;
    }

    private function validator(){
        $db_id =  $this->data['id'];
        //判断传过来的数组数据是否为空
        if(!$db_id || !is_array($db_id)){
            return ['code' => 202 , 'msg' => '传递数据不合法'];
        }

        return $db_id;
    }

    private function getModel($key){
        $model = array(
            '1' => 'Chapters',
        );
        if(!in_array($key,[1])){
            return response()->json(['code' => 500 , 'msg' => 'Wrong parameter information!']);
        }
        return  $model[$key];

    }
}
