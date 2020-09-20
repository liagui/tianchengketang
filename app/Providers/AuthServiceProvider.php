<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {

        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        //当使用auth中间件的api门卫的时候验证请求体
        $this->app['auth']->viaRequest('api', function ($request) {
    
             if ($request->input('jwt_token')) {
                $user = JWT::getUserByToken($request->input('jwt_token'));
                if ($user) {
                    Redis::setbit('active:user:'.date('Y-m-d', time()), $user->id, 1);
                }
                return $user;
            }
        });

    }
}
