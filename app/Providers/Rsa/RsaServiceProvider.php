<?php

namespace App\Providers\Rsa;

use Illuminate\Support\ServiceProvider;

class RsaServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('rsa',function(){
            return new RsaFactory();
        });
    }
}
