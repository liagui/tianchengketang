<?php
namespace App\Http\Middleware\Web;
use App\Models\User;
use App\Models\Order;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SCookie;

class StudentOrderAuth {
    public function handle($request, Closure $next){
        $student_info = $request->get('json_info');
        if(empty($student_info)){
            return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
        }
        $orderIds = Order::where(['student_id'=>$student_info['user_id']])->pluck('id')->toArray();
        if(!empty($request->input('id')) && !in_array($request->input('id'),$orderIds)){
           return response()->json(['code' => 403 , 'msg' => '无权限！！！']);
        }
        return $next($request);
    }
}
