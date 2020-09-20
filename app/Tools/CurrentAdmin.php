<?php
namespace App\Tools;

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
        return  $user = JWTAuth::user();
	}

}