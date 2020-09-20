<?php

namespace App\Providers\Ali;

use Illuminate\Support\ServiceProvider;

class AliServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
    }
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('ali',function(){
            return new AlipayFactory();
        });
    }
}
