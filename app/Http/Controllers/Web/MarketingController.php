<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingController extends Controller {
    public function addMarketing(Request $request){
        $data = $request->all();
        unset($data['/web/marketing/addMarketing']);
        unset($data['school_dns']);
        unset($data['dns']);
        $data['create_at'] = date('Y-m-d H:i:s');
        $data['source'] = "消防设施操作员";
        $d = DB::table("ld_marketing")->insert($data);
        if($d){
            return response()->json(['code'=>200,'msg'=>'Success']);
        }else{
            return response()->json(['code'=>500,'msg'=>'数据错误']);
        }

    }
    public function MarketingList(){
        $data = DB::table("ld_marketing")->get()->toArray();
        print <<<EOT
    <style type="text/css">
    table.gridtable {
        font-family: verdana,arial,sans-serif;
        font-size:15px;
        color:#333333;
        border-width: 1px;
        border-color: #666666;
        border-collapse: collapse;
        table-layout:fixed;
        width:50%;
    }
    table.gridtable th {
        border-width: 1px;
        padding: 2px;
        border-style: solid;
        border-color: #666666;
        background-color: #dedede;
    }
    table.gridtable td {
        border-width: 1px;
        padding: 2px;
        border-style: solid;
        border-color: #666666;
        background-color: #ffffff;
    }
    table.gridtable tr {
        text-align:center
    }
    </style>

    <div style="text-align:center;margin:25 0 0 25">

    <table class="gridtable">
        <tr>
        <th>名字</th>
        <th>手机号</th>
        <th>报考省份</th>
        <th>单页标识</th>
        <th>创建时间</th>
        </tr>
EOT;
    foreach($data as $k => $v){
        echo "<tr>";
        echo "<td>".$v->name."</td><td>".$v->phone."</td><td>".$v->province."</td><td>".$v->source."</td>"."<td>".$v->create_at."</td>";
        echo "</tr>";
    }
        echo "</table>";
        echo "</div>";
    }
}
