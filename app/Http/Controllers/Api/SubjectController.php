<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use App\Models\Method;

class SubjectController extends Controller {

    /*
     * @param  科目列表
     * @param  current_count   count
     * @param  author  孙晓丽
     * @param  ctime   2020/5/1
     * return  array
     */
    public function index(Request $request){
        $subjects = Subject::where('parent_id', 0)
                ->select('id', 'subject_name as name', 'parent_id as pid')
                ->orderBy('create_at', 'desc')
                ->where('is_del',0)
                ->get();
        foreach ($subjects as $value) {
                $child = [['id' => 0, 'name' => '全部']];
                $value['childs'] = array_merge($child, Subject::where('parent_id', $value['id'])
                ->select('id', 'subject_name as name', 'parent_id as pid')
                ->orderBy('create_at', 'desc')
                ->get()->toArray());
        }
        $all = [['id' => 0, 'name' => '全部', 'pid' => 0, 'childs' => []]];
        $data['subjects'] = array_merge($all, json_decode($subjects));
        $data['sort'] = [
            ['sort_id' => 0, 'name' => '综合'],
            ['sort_id' => 1, 'name' => '按热度'],
            ['sort_id' => 2, 'name' => '按价格升', 'type' => ['asc']],
            ['sort_id' => 3, 'name' => '按价格降', 'type' => ['desc']],
        ];
        $data['method'] = [
            ['method_id' => 0, 'name' => '全部'],
            ['method_id' => 1, 'name' => '直播'],
            ['method_id' => 2, 'name' => '录播'],
            ['method_id' => 3, 'name' => '其他']
        ];
        return $this->response($data);
    }
}
