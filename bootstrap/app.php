<?php

require_once __DIR__.'/../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(
    dirname(__DIR__)
);

$app->withFacades(true , [
   'Tymon\JWTAuth\Facades\JWTAuth'    => 'JWTAuth',
   'Tymon\JWTAuth\Facades\JWTFactory' => 'JWTFactory'
]);

$app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

/*
|--------------------------------------------------------------------------
| Register Config Files
|--------------------------------------------------------------------------
|
| Now we will register the "app" configuration file. If the file exists in
| your configuration directory it will be loaded; otherwise, we'll load
| the default version. You may register other files below as needed.
|
*/

$app->configure('app');
$app->configure('auth');
$app->configure('jwt');

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

$app->middleware([
     App\Http\Middleware\CorsMiddleware::class
]);

$app->routeMiddleware([
    'auth' => App\Http\Middleware\Authenticate::class,
    'api' => App\Http\Middleware\ApiAuthToken::class,
    'user'=> App\Http\Middleware\UserAuthToken::class,
    // 'jwt.role' => App\Http\Middleware\JWTRoleAuth::class,
    'cors' => App\Http\Middleware\Cors::class,
    'user.web' => App\Http\Middleware\UserToken::class,
    'student.order.auth' => App\Http\Middleware\Web\StudentOrderAuth::class,
    'school.admin.auth' => App\Http\Middleware\Admin\AdminSchoolAuth::class,
    'user.admin.auth' => App\Http\Middleware\Admin\AdminUserAuth::class,
    'school.order.admin.auth' => App\Http\Middleware\Admin\AdminSchoolOrderAuth::class,
    'student.admin.auth' => App\Http\Middleware\Admin\AdminStudentAuth::class,
    'teacher.admin.auth' => App\Http\Middleware\Admin\AdminTeacherAuth::class,
    'admin.token' =>App\Http\Middleware\AdminToken::class
]);
/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/

$app->register(App\Providers\AppServiceProvider::class);
$app->register(App\Providers\AuthServiceProvider::class);
$app->register(App\Providers\EventServiceProvider::class);
//数据加密IOC容器
$app->register(App\Providers\Rsa\RsaServiceProvider::class);

// JWT
$app->register(\Tymon\JWTAuth\Providers\LumenServiceProvider::class);
//加载redis服务
$app->register(\Illuminate\Redis\RedisServiceProvider::class);
//加载注册excel
$app->register(\Maatwebsite\Excel\ExcelServiceProvider::class);
//阿里云短信
$app->register(\Lysice\Sms\SmsServiceProvider::class);
//图片验证码
$app->register(Youngyezi\Captcha\CaptchaServiceProvider::class);
// 添加别名
$app->alias('captcha', 'Youngyezi\Captcha\CaptchaServiceProvider');

/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/

//项目路由
$app->router->group([
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    require __DIR__.'/../routes/web.php';
});

return $app;
