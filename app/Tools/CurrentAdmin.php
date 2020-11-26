<?php
namespace App\Tools;

use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin;
use Redis;

/**
* LookMe Response class
*/
class CurrentAdmin
{
	public static function user()
	{
        try {
            $user = JWTAuth::user();
        } catch (\Exception $e) {
            return null;
        }
        $user->cur_admin_id = (int)app(Request::class)->header('CurAdminId', 0);
        if (empty($user->cur_admin_id)) {
            $user->cur_admin_id = $user->id;
        }
        return  $user;
	}

}
