<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->post('/', function () use ($router) {
    return $router->app->version();
});
//客户端(ios,安卓)不需要登录路由接口
$router->group(['prefix' => 'api', 'namespace' => 'Api'], function () use ($router) {
    /*
     * 科目模块(sxl)
    */
    $router->post('subject', 'SubjectController@index');
    //录播章节列表
    $router->post('lesson', 'LessonController@index');
    $router->post('lessonShow', 'LessonController@show');
    $router->post('lessonChild', 'LessonChildController@index');
    $router->post('lessonChildShow', 'LessonChildController@show');
    //课程直播目录
    $router->post('lessonLive', 'LiveChildController@index');

    $router->post('doUserRegister','AuthenticateController@doUserRegister');    //APP注册接口
    $router->post('doVisitorLogin','AuthenticateController@doVisitorLogin');    //APP游客登录接口
    $router->post('doUserLogin','AuthenticateController@doUserLogin');          //APP登录接口
    $router->post('doSendSms','AuthenticateController@doSendSms');              //APP发送短信接口
    $router->post('doUserForgetPassword','AuthenticateController@doUserForgetPassword');              //APP忘记密码接口

    //首页相关接口
    $router->group(['prefix' => 'index'], function () use ($router) {
        $router->post('getChartList','IndexController@getChartList');                             //APP首页轮播图接口
        $router->post('getOpenClassList','IndexController@getOpenClassList');                     //APP首页公开课接口
        $router->post('getTeacherList','IndexController@getTeacherList');                         //APP首页讲师接口
        $router->post('getOpenPublicList','IndexController@getOpenPublicList');                   //APP公开课列表接口
        $router->post('checkVersion','IndexController@checkVersion');                             //APP版本升级接口
        $router->post('getSubjectList','IndexController@getSubjectList');                         //APP首页学科接口
        $router->post('getLessonList','IndexController@getLessonList');                           //APP首页课程接口
        $router->post('getFamousTeacherList','IndexController@getFamousTeacherList');             //APP名师列表接口
        $router->post('getFamousTeacherInfo','IndexController@getFamousTeacherInfo');             //APP名师详情接口
        $router->post('getTeacherLessonList','IndexController@getTeacherLessonList');             //APP名师课程列表接口
    });

    //回调
    $router->group(['prefix' => 'notify'], function () use ($router) {
        $router->post('iphonePaynotify','NotifyController@iphonePaynotify');   //苹果内部支付
        $router->post('hjAlinotify','NotifyController@hjAlinotify');   //汇聚支付宝 购买回调地址
        $router->post('hjWxnotify','NotifyController@hjWxnotify');   //汇聚weixin 购买回调地址
        $router->post('wxnotify', 'NotifyController@wxnotify');//微信 购买回调
        $router->post('alinotify', 'NotifyController@alinotify');//支付宝 购买回调

        $router->post('hjAliTopnotify','NotifyController@hjAliTopnotify');   //汇聚支付宝 充值回调地址
        $router->post('hjWxTopnotify','NotifyController@hjWxTopnotify');   //汇聚weixin 充值回调地址
        $router->post('wxTopnotify', 'NotifyController@wxTopnotify');//微信 充值回调
        $router->post('aliTopnotify', 'NotifyController@aliTopnotify');//支付宝 充值回调
    });
});
//客户端(ios,安卓)需要登录路由接口
$router->group(['prefix' => 'api', 'namespace' => 'Api', 'middleware'=> 'user'], function () use ($router) {
    //zzk  公开课直播token
    $router->post('lessonOpenCourse', 'LessonController@OpenCourse');
    //直播课程
    $router->post('courseAccess', 'LiveChildController@courseAccess');

    //收藏模块
    $router->post('collection','CollectionController@index');          //课程收藏列表
    $router->post('addCollection','CollectionController@store');       //添加收藏课程
    $router->post('cancelCollection','CollectionController@cancel');   //取消收藏课程

    //用户学员相关接口
    $router->group(['prefix' => 'user'], function () use ($router) {
        $router->post('getUserInfoById','UserController@getUserInfoById');          //APP学员详情接口
        $router->post('doUserUpdateInfo','UserController@doUserUpdateInfo');        //APP用户更新信息接口
        $router->post('doLoginOut','UserController@doLoginOut');                    //APP用户退出登录接口
        $router->post('getUserMoreSchoolList','UserController@getUserMoreSchoolList');            //APP网校列表接口
        $router->post('doSetDefaultSchool','UserController@doSetDefaultSchool');                  //APP设置默认网校接口
    });
    //支付
    $router->group(['prefix' => 'order'], function () use ($router) {
        $router->post('createOrder','OrderController@createOrder');   //用户生成支付预订单
        $router->post('orderPay','OrderController@orderPay');   //进行支付
        $router->post('iphoneTopOrder','OrderController@iphonePayCreateOrder');   //苹果内部支付充值 生成预订单
        $router->post('iosPolling','OrderController@iosPolling');   //轮询订单信息
        $router->post('myOrderlist','OrderController@myOrderlist');   //我的订单
        $router->post('myPricelist','OrderController@myPricelist');   //我的余额记录
        $router->post('myLessionlist','OrderController@myLessionlist');   //我的课程
        $router->post('myPutclassList','OrderController@myPutclassList');   //我的课程
    });
});

//PC端路由接口
$router->group(['prefix' => 'web' , 'namespace' => 'Web'], function () use ($router) {
    $router->post('csaliss', 'OrderController@csali');//订单通过学员查询
    $router->group(['prefix' => 'marketing'], function () use ($router) {
        $router->post('addMarketing','MarketingController@addMarketing');//添加营销数据
        $router->get('MarketingList','MarketingController@MarketingList');//营销数据列表
    });

    //begin (lys)
    //首页
     $router->group(['prefix' => 'index'], function () use ($router) {
        $router->post('getChartList','IndexController@getChartList');                             //APP首页轮播图接口
        $router->post('teacher','IndexController@teacherList');//我们的团队
        $router->post('news','IndexController@newInformation');//新闻资讯
        $router->post('index','IndexController@index');//首页内容
        $router->post('course','IndexController@course');//精品课程
        $router->post('getCompany','IndexController@getCompany'); //对公信息扫码
        $router->post('getPay','IndexController@getPay'); //对公信息扫码
    });

    $router->group(['prefix' => 'footer'], function () use ($router) {
        $router->post('details','FooterController@details');//首页   页脚跳转
    });
    //新闻资讯
     $router->group(['prefix' => 'news'], function () use ($router) {
        $router->post('List','NewsController@getList');//新闻资讯列表
        $router->post('hotList','NewsController@hotList');//热门新闻
        $router->post('newestList','NewsController@newestList');//最新文章
        $router->post('details','NewsController@details');//查看详情
    });
     //公开课
    $router->group(['prefix' => 'openclass'], function () use ($router) {
        $router->post('getList','OpenCourseController@getList');//公开课列表 （h5）
        $router->post('hotList','OpenCourseController@hotList');//大家都在看
        $router->post('preStart','OpenCourseController@preStart');//预开始
        $router->post('underway','OpenCourseController@underway');//直播中
        $router->post('finish','OpenCourseController@end');//往期公开课程 (暂时没做分页)
    });
    //教师
    $router->group(['prefix' => 'teacher'], function () use ($router) {
        $router->post('List','TeacherController@getList');//查看详情
        $router->post('dateils','TeacherController@dateils');//查看详情
    });
    //H5/APP 我的
    $router->group(['prefix' => 'my','middleware'=>'user.web'], function () use ($router) {
        $router->post('about','MyController@getAbout');//关于我们
        $router->post('contact','MyController@getContact');//联系客服
    });
    //app/h5 首页-新闻
    $router->group(['prefix' => 'new','middleware'=>'user.web'], function () use ($router) {
        $router->post('List','NewController@getList');//新闻资讯列表
    });
    //end (lys)



    $router->post('doUserRegister','AuthenticateController@doUserRegister');    //WEB注册接口
    $router->post('doUserLogin','AuthenticateController@doUserLogin');          //WEB登录接口
    $router->post('doSendSms','AuthenticateController@doSendSms');              //WEB发送短信接口

    $router->post('doUserVerifyPassword','AuthenticateController@doUserVerifyPassword');              //忘记密码验证接口
    $router->post('doUserForgetPassword','AuthenticateController@doUserForgetPassword');              //找回密码接口
    $router->post('captchaInfo','AuthenticateController@captchaInfo');          //WEB生成图片验证码接口
    $router->post('orderOAtoPay','PublicpayController@orderOAtoPay');   //OA流转订单
     //公开课
    $router->group(['prefix' => 'openclass','middleware'=> 'user'], function () use ($router) {
        $router->post('details','OpenCourseController@details');//查看详情
    });
    //题库部分
    $router->post('getBankList','BankController@getBankList');                  //全部题库接口
    $router->group(['prefix' => 'bank' , 'middleware'=> 'user'], function () use ($router) {
        $router->post('getBankChaptersList','BankController@getBankChaptersList');  //题库章节接口
        $router->post('getExamSet','BankController@getExamSet');                    //做题设置接口
        $router->post('doRandExamList','BankController@doRandExamList');            //随机生成试题接口
        $router->post('getExamPapersList','BankController@getExamPapersList');      //模拟真题试卷列表接口
        $router->post('doCollectQuestion','BankController@doCollectQuestion');      //试题收藏/取消收藏接口
        $router->post('doTabQuestion','BankController@doTabQuestion');              //试题标记/取消标记接口
        $router->post('doBankMakeExam','BankController@doBankMakeExam');            //做题接口
        $router->post('getCollectErrorExamCount','BankController@getCollectErrorExamCount');  //我的收藏/错题本/做题记录数量接口
        $router->post('getMyCollectExamList','BankController@getMyCollectExamList');  //我的收藏列表接口
        $router->post('getMyErrorExamList','BankController@getMyErrorExamList');      //错题本列表接口
        $router->post('doMyErrorExam','BankController@doMyErrorExam');            //错题本做题接口
        $router->post('getMyMakeExamList','BankController@getMyMakeExamList');        //做题记录列表接口
        $router->post('getMyMakeExamPageList','BankController@getMyMakeExamPageList'); //做题记录列表分页接口
        $router->post('getPapersIdByMoId','BankController@getPapersIdByMoId');         //获取试卷做题记录的id接口
        $router->post('getAnalogyExamStop','BankController@getAnalogyExamStop');       //模拟真题暂停接口
        $router->post('getNewMakeExamInfo','BankController@getNewMakeExamInfo');      //章节练习/快速做题/模拟真题最新做题接口
        $router->post('getMakeExamInfo','BankController@getMakeExamInfo');            //做题记录详情接口
        $router->post('doHandInPapers','BankController@doHandInPapers');              //做题交卷接口
        $router->post('getMyBankList','BankController@getMyBankList');                //我的题库
    });


    //szw    我的
    $router->group(['prefix' => 'user' , 'middleware'=> 'user'], function () use ($router) {
        //个人设置模块
        $router->post('userDetail','UserController@userDetail');//个人信息
        $router->post('userUpPhone','UserController@userUpPhone');//修改手机号
        $router->post('userUpEmail','UserController@userUpEmail');//修改邮箱
        $router->post('address','UserController@address');//地址二级
        $router->post('userUpDetail','UserController@userUpDetail');//修改基本信息
        $router->post('userUpRelation','UserController@userUpRelation');//修改联系方式
        $router->post('userUpImg','UserController@userUpImg');//修改头像
        $router->post('userUpPass','UserController@userUpPass');//修改密码
        //个人信息模块
        $router->post('myOrder','UserController@myOrder');//我的订单
        $router->post('orderFind','UserController@orderFind');//我的订单单条记录
        $router->post('myCollect','UserController@myCollect');//我的收藏
        $router->post('myCourse','UserController@myCourse');//我的课程
        $router->post('doLoginOut','UserController@doLoginOut');//Web端退出登录接口
		$router->post('myMessage','UserController@myMessage');//我的消息
		$router->post('myCommen','UserController@myCommen');//评论列表
		$router->post('myAnswers','UserController@answersList');//问答列表-我的提问
        $router->post('myReply','UserController@replyList');//问答列表-我的回答
    });
    //课程（szw）
    $router->group(['prefix' => 'course', 'middleware'=> 'user'], function () use ($router) {
        $router->post('collect','CourseController@collect');//课程收藏
        $router->post('livearr','CourseController@livearr');//课程直播列表
        $router->post('recordedarr','CourseController@recordedarr');//课程录播列表
        $router->post('material','CourseController@material');//课程资料列表
        $router->post('userPay','CourseController@userPay');//用户生成订单
        $router->post('userPaying','CourseController@userPaying');//用户进行支付
        $router->post('courseToUser','CourseController@courseToUser');//用户与课程关系
        $router->post('recordeurl','CourseController@recordeurl');//课程录播url
        $router->post('liveurl','CourseController@liveurl');//课程直播url
		$router->post('comment','CourseController@comment');//评论课程
		$router->post('commentList','CourseController@commentList');//评论课程列表
    });
    //站内支付
    $router->group(['prefix' => 'order', 'middleware'=> 'user'], function () use ($router) {
        $router->post('userPay','OrderController@userPay');//用户生成订单
        $router->post('userPaying','OrderController@userPaying');//用户进行支付
        $router->post('webajax','OrderController@webajax');//前端轮询查询接口
        $router->post('chargeOrder','OrderController@chargeOrder');//0元购买接口
        //汇聚扫码支付
        $router->post('scanPay', 'OrderController@scanPay');//扫码支付页面信息
        $router->post('converge', 'OrderController@converge');//汇聚扫码
        //h5 支付
        $router->post('hfivePay', 'OrderController@hfivePay');//汇聚扫码
    });
	//问答模块
    $router->group(['prefix' => 'answers','middleware'=> 'user'], function () use ($router) {
        $router->post('list','AnswersController@list');//问答列表
		$router->post('details','AnswersController@details');//查看详情
		$router->post('reply','AnswersController@reply');//回复
		$router->post('add','AnswersController@addAnswers');//提问
    });
    //课程 无需token
    $router->group(['prefix' => 'course'], function () use ($router) {
        $router->post('subjectList','CourseController@subjectList');//学科列表
        $router->post('courseList','CourseController@courseList');//课程列表
        $router->post('courseDetail','CourseController@courseDetail');//课程详情
        $router->post('courseIntroduce','CourseController@courseIntroduce');//课程简介
        $router->post('courseTeacher','CourseController@courseTeacher');//课程讲师信息
        $router->post('urlcode','CourseController@urlcode');//二维码测试
        $router->post('alinotify', 'NotifyController@alinotify');//web端直接购买支付宝 购买回调
        $router->post('convergecreateNotifyPcPay', 'NotifyController@convergecreateNotifyPcPay');//web端扫码购买支付宝 购买回调
        $router->get('hjnotify', 'NotifyController@hjnotify');//汇聚 支付回调
        $router->get('ylnotify', 'NotifyController@ylnotify');//银联 支付回调
        $router->post('yltest', 'OrderController@yltest');//银联测试支付
    });
});
//后台端路由接口
/*****************start**********************/
//无需任何验证 操作接口
$router->group(['prefix' => 'admin' , 'namespace' => 'Admin'], function () use ($router) {
    $router->get('orderForExceil', 'OrderController@orderForExceil');//导出订单exceil

    ////////////////////服务->充值模块
    //支付宝回调
    $router->addRoute(['GET','POST'],'service/aliNotify', 'ServiceController@aliNotify');
    //微信回调
    $router->addRoute(['GET','POST'],'service/wxNotify', 'ServiceController@wxNotify');
    //轮询支付结果
    $router->addRoute(['GET','POST'],'service/recharge_res', 'ServiceController@recharge_res');
});

//后端登录注册接口
$router->group(['prefix' => 'admin' , 'namespace' => 'Admin', 'middleware'=> 'cors'], function () use ($router) {
    $router->post('register', 'AuthenticateController@register');
    $router->post('login', 'AuthenticateController@postLogin');
    $router->post('diff', 'TestController@diff');
    $router->post('test', 'TestController@index');
    $router->post('ArticleLead', 'ArticleController@ArticleLead');//文章导入
    $router->post('ArticleTypeLead', 'ArticletypeController@ArticleTypeLead');//文章分类导入
    $router->post('ArticleToType', 'ArticleController@ArticleToType');//文章关联分类
    $router->get('liveCallBack', 'LiveChildController@listenLive');
    $router->post('liveCallBack', 'LiveChildController@listenLive');//直播回调状态
    $router->post('orderUpOaForId', 'OrderController@orderUpOaForId');//订单修改oa状态
    $router->post('orderUpinvalid', 'OrderController@orderUpinvalid');//订单无效修改
    $router->post('listType', 'ArticleController@listType');//分类列表
    $router->post('schoolLists', 'ArticleController@schoolLists');//学校列表
    $router->post('courseType', 'CourseController@courseType');//根据分类查课程
    $router->post('orderForStudent', 'OrderController@orderForStudent');//订单通过学员查询
});

//后端登录权限认证相关接口
//$router->group(['prefix' => 'admin' , 'namespace' => 'Admin' , 'middleware'=> ['jwt.auth', 'cors','api']], function () use ($router) {
    $router->group(['prefix' => 'admin' , 'namespace' => 'Admin' , 'middleware'=> ['jwt.auth', 'cors']], function () use ($router) {
    /*
     * 授课方式(sxl)
    */
    $router->post('method', 'MethodController@index');
    $router->post('method/add', 'MethodController@store');
    $router->post('updateMethod', 'MethodController@update');
    $router->post('deleteMethod', 'MethodController@destroy');

    /*
     * 课程模块(sxl)
    */

    $router->post('lesson', 'LessonController@index');
    $router->post('schoolLesson', 'LessonController@schoolLesson');
    $router->post('lesson/add', 'LessonController@store');
    $router->post('lesson/relatedLive', 'LessonController@relatedLive');
    $router->post('UpdateLessonStatus', 'LessonController@status');
    $router->post('lessonShow', 'LessonController@show');
    $router->post('updateLesson', 'LessonController@update');
    $router->post('addLessonUrl', 'LessonController@edit');
    $router->post('lessonDelete', 'LessonController@destroy');
    $router->post('lessonIsRecommend', 'LessonController@isRecommend');

    /*
     * 章节模块(sxl)
    */
    $router->post('lessonChild', 'LessonChildController@index');
    $router->post('lessonChild/add', 'LessonChildController@store');
    $router->post('lessonChildShow', 'LessonChildController@show');
    $router->post('updateLessonChild', 'LessonChildController@update');
    $router->post('deleteLessonChild', 'LessonChildController@destroy');

    /*
     * 分校课程(sxl)
    */
    $router->post('lessonSchool', 'LessonSchoolController@index');
    $router->post('lessonSchool/add', 'LessonSchoolController@store');
    $router->post('lessonSchoolShow', 'LessonSchoolController@show');
    $router->post('updateLessonSchool', 'LessonSchoolController@update');
    $router->post('deleteLessonSchool', 'LessonSchoolController@destroy');
    $router->post('lessonIdList', 'LessonSchoolController@lessonIdList');


    /*
     * 库存(sxl)
    */
    $router->post('lessonStock', 'LessonStockController@index');
    $router->post('lessonStock/add', 'LessonStockController@store');


    /*
     * 科目模块(sxl)
    */
    $router->post('subject', 'CourseController@subject');//课程学科列表(szw改)


//    $router->post('subject', 'SubjectController@searchList');
    $router->post('subjectList', 'SubjectController@index');
    $router->post('subject/add', 'SubjectController@store');
    $router->post('subjectShow', 'SubjectController@show');
    $router->post('updateSubject', 'SubjectController@update');
    $router->post('deleteSubject', 'SubjectController@destroy');
    $router->post('updateSubjectStatus', 'SubjectController@status');

    /*
     * 录播模块(zzk)
    */
    $router->post('videoList', 'VideoController@list');
    $router->post('video', 'VideoController@index');
    $router->post('video/add', 'VideoController@store');
    $router->post('videoShow', 'VideoController@show');
    $router->post('updateVideo', 'VideoController@update');
    $router->post('updateVideoStatus', 'VideoController@status');
    $router->post('deleteVideo', 'VideoController@destroy');
    $router->post('videoUploadUrl', 'VideoController@uploadUrl');
    $router->post('ccvideoUploadUrl', 'VideoController@ccuploadUrl');



    /*
     * 直播模块(zzk)
    */
    $router->post('liveLessonId', 'LiveController@lessonId');
    $router->post('liveList', 'LiveController@list');
    $router->post('live', 'LiveController@index');
    $router->post('live/add', 'LiveController@store');
    $router->post('liveClassList', 'LiveController@classList');
    $router->post('liveShow', 'LiveController@show');
    $router->post('updateLive', 'LiveController@update');
    $router->post('deleteLive', 'LiveController@destroy');
    $router->post('updateLiveStatus', 'LiveController@status');
    $router->post('liveRelationLesson', 'LiveController@lesson');
    $router->post('lessonList', 'LiveController@lessonList');



    /*
     * 直播班号(zzk)
    */
    $router->post('liveClass', 'LiveClassController@index');
    $router->post('oneLiveClass', 'LiveClassController@oneList');
    $router->post('liveClass/add', 'LiveClassController@store');
    $router->post('updateLiveClass', 'LiveClassController@update');
    $router->post('deleteLiveClass', 'LiveClassController@destroy');
    $router->post('updateLiveClassStatus', 'LiveClassController@status');
    $router->post('uploadLiveClass', 'LiveClassController@uploadLiveClass');
    $router->post('getListLiveClassMaterial', 'LiveClassController@getListLiveClassMaterial');
    $router->post('deleteLiveClassMaterial', 'LiveClassController@deleteLiveClassMaterial');


    /*
     * 直播课次模块(zzk)
    */
    $router->post('liveChildList', 'LiveChildController@liveList');
    $router->post('liveChild', 'LiveChildController@index');
    $router->post('liveChildShowOne', 'LiveChildController@showOne');
    $router->post('liveChild/add', 'LiveChildController@store');
    $router->post('updateLiveChild', 'LiveChildController@UpdateChild');
    $router->post('deleteLiveChild', 'LiveChildController@destroy');
    $router->post('editLiveChildStatus', 'LiveChildController@edit');
    $router->post('startLiveChild', 'LiveChildController@startLive');
    $router->post('teacherLiveChild', 'LiveChildController@ClassChildRelevance');
    $router->post('creationLive', 'LiveChildController@creationLive');
    $router->post('uploadLiveClassChild', 'LiveChildController@uploadLiveClassChild');
    $router->post('getLiveClassMaterial', 'LiveChildController@getLiveClassMaterial');
    $router->post('deleteLiveClassChildMaterial', 'LiveChildController@deleteLiveClassChildMaterial');


    //上传图片OSS公共参数接口
    $router->post('getImageOssConfig', 'CommonController@getImageOssConfig');

    //上传到本地图片接口
    $router->post('doUploadImage', 'CommonController@doUploadImage');

    //上传到OSS图片接口
    $router->post('doUploadOssImage', 'CommonController@doUploadOssImage');

    //上传到OSS文件接口
    $router->post('doUploadOssFile', 'CommonController@doUploadOssFile');

    //上传到本地服务器接口
    $router->post('doUploadCaFile', 'CommonController@doUploadCaFile');

    //用户学员相关模块(dzj)
    $router->group(['prefix' => 'student'], function () use ($router) {
        $router->post('doInsertStudent', 'StudentController@doInsertStudent');        //添加学员的方法
        $router->post('doUpdateStudent', 'StudentController@doUpdateStudent');        //更改学员的方法
        $router->post('doForbidStudent', 'StudentController@doForbidStudent');        //启用/禁用学员的方法
        $router->post('doStudentEnrolment', 'StudentController@doStudentEnrolment');  //学员报名的方法
        $router->post('getStudentInfoById', 'StudentController@getStudentInfoById');   //获取学员信息
        $router->post('getStudentList', 'StudentController@getStudentList');           //获取学员列表
        $router->post('getStudentCommonList', 'StudentController@getStudentCommonList');  //学员公共参数列表
        $router->post('importUser', 'StudentController@doImportUser');                    //导入学员excel功能
        $router->post('getStudentTransferSchoolList', 'StudentController@getStudentTransferSchoolList');      //学员转校列表
        $router->post('doTransferSchool', 'StudentController@doTransferSchool');                              //学员转校
        $router->post('getStudentStudyList', 'StudentController@getStudentStudyList');           //获取学员学校进度列表
		$router->post('getStudentBankList', 'StudentController@getStudentBankList');     //学员做题记录
        $router->post('getStudentBankSearchInfo', 'StudentController@getStudentBankSearchInfo');     //筛选学员做题记录条件
		$router->post('exportExcelStudentBankList', 'StudentController@exportExcelStudentBankList');     //导出学员做题记录功能
		$router->post('getStudentBankDetails', 'StudentController@getStudentBankDetails');     //学员做题记录详情
		//$router->post('getStudentStudyList', 'StudentController@getStudentStudyList');     //学员学习记录
    });


    //讲师教务相关模块(dzj)
    $router->group(['prefix' => 'teacher'], function () use ($router) {
        $router->post('doInsertTeacher', 'TeacherController@doInsertTeacher');        //添加讲师教务的方法
        $router->post('doUpdateTeacher', 'TeacherController@doUpdateTeacher');        //更改讲师教务的方法
        $router->post('doForbidTeacher', 'TeacherController@doForbidTeacher');        //启用/禁用讲师/教务的方法
        $router->post('doDeleteTeacher', 'TeacherController@doDeleteTeacher');        //删除讲师教务的方法
        $router->post('doRecommendTeacher', 'TeacherController@doRecommendTeacher');  //推荐讲师的方法
        $router->post('getTeacherInfoById', 'TeacherController@getTeacherInfoById');  //获取老师信息
        $router->post('getTeacherList', 'TeacherController@getTeacherList');          //获取老师列表
        $router->post('getTeacherSearchList', 'TeacherController@getTeacherSearchList'); //讲师或教务搜索列表
        $router->post('getTeacherIsAuth', 'TeacherController@getTeacherIsAuth');         //是否授权讲师教务
    });

    //题库相关模块(dzj)
    $router->group(['prefix' => 'question'], function () use ($router) {
        /****************题库科目部分  start****************/
        $router->post('doInsertSubject', 'QuestionController@doInsertSubject');        //添加题库科目的方法
        $router->post('doUpdateSubject', 'QuestionController@doUpdateSubject');        //更改题库科目的方法
        $router->post('doDeleteSubject', 'QuestionController@doDeleteSubject');        //删除题库科目的方法
        $router->post('getSubjectList', 'QuestionController@getSubjectList');          //获取题库科目列表
		$router->post('doUpdateSubjectListSort', 'QuestionController@doUpdateSubjectListSort'); //更改科目排序
        /****************题库科目部分  end****************/

        /****************章节考点部分  start****************/
        $router->post('doInsertChapters', 'QuestionController@doInsertChapters');           //添加章节考点的方法
        $router->post('doUpdateChapters', 'QuestionController@doUpdateChapters');           //更改章节考点的方法
        $router->post('doDeleteChapters', 'QuestionController@doDeleteChapters');           //删除章节考点的方法
        $router->post('getChaptersList', 'QuestionController@getChaptersList');             //获取章节考点列表
        $router->post('getChaptersSelectList', 'QuestionController@getChaptersSelectList'); //获取章节考点下拉选择列表
		$router->post('doUpdateListSort', 'QuestionController@doUpdateListSort');           //更改列表排序
        /****************章节考点部分  end****************/

        /****************题库部分  start****************/
        $router->post('doInsertBank', 'BankController@doInsertBank');                    //添加题库的方法
        $router->post('doUpdateBank', 'BankController@doUpdateBank');                    //更新题库的方法
        $router->post('doDeleteBank', 'BankController@doDeleteBank');                    //删除题库的方法
        $router->post('doOpenCloseBank', 'BankController@doOpenCloseBank');              //题库开启/关闭的方法
        $router->post('getBankInfoById', 'BankController@getBankInfoById');              //获取题库详情信息
        $router->post('getBankList', 'BankController@getBankList');                      //获取题库列表
        $router->post('getBankCommonList', 'BankController@getBankCommonList');          //题库公共参数列表
        $router->post('getBankIsAuth', 'BankController@getBankIsAuth');                  //是否授权题库
        /****************题库部分  end****************/


        /****************试卷部分  start****************/
        $router->post('doInsertPapers', 'PapersController@doInsertPapers');              //添加试卷的方法
        $router->post('doUpdatePapers', 'PapersController@doUpdatePapers');              //更新试卷的方法
        $router->post('doDeletePapers', 'PapersController@doDeletePapers');              //删除试卷的方法
        $router->post('doPublishPapers', 'PapersController@doPublishPapers');            //发布/取消发布试卷的方法
        $router->post('getPapersInfoById', 'PapersController@getPapersInfoById');        //获取试卷详情信息
        $router->post('getPapersList', 'PapersController@getPapersList');                //获取题库列表
        $router->post('getRegionList', 'PapersController@getRegionList');                //获取所属区域列表
        /****************试卷部分  end****************/


        //试题选择试卷（zzk）
        /****************试卷选择试题部分  start****************/
        $router->post('InsertTestPaperSelection', 'ExamController@InsertTestPaperSelection');           //添加试题到试卷
        $router->post('doTestPaperSelection', 'ExamController@doTestPaperSelection');                   //试卷已添加试题的列表
        $router->post('getExamSignleScore', 'ExamController@getExamSignleScore');                       //获取试卷中的试题类型分数
        $router->post('ListTestPaperSelection', 'ExamController@ListTestPaperSelection');               //添加试题到试卷的列表
        $router->post('RepetitionTestPaperSelection', 'ExamController@RepetitionTestPaperSelection');   //检测试卷试题
        $router->post('oneTestPaperSelection', 'ExamController@oneTestPaperSelection');                 //获取试题详情
        $router->post('deleteTestPaperSelection', 'ExamController@deleteTestPaperSelection');           //删除试题
        $router->post('questionsSort', 'ExamController@questionsSort');           //试卷中试题排序
        /****************试卷选择试题部分  end****************/


        /****************试题部分  start****************/
        $router->post('doInsertExam', 'ExamController@doInsertExam');                    //添加试题的方法
        $router->post('doUpdateExam', 'ExamController@doUpdateExam');                    //修改试题的方法
        $router->post('doDeleteExam', 'ExamController@doDeleteExam');                    //删除试题的方法
        $router->post('doPublishExam', 'ExamController@doPublishExam');                  //发布试题的方法
        $router->post('getExamInfoById', 'ExamController@getExamInfoById');              //试题详情的方法
        $router->post('getExamList', 'ExamController@getExamList');                      //试题列表的方法
        $router->post('getMaterialList', 'ExamController@getMaterialList');              //查看材料题的方法
        $router->post('getExamCommonList', 'ExamController@getExamCommonList');          //试题公共参数列表
        $router->post('importExam', 'ExamController@doImportExam');                      //导入试题excel功能
        $router->post('doExamineExcelData', 'ExamController@doExamineExcelData');        //校验excel表格接口
        /****************试题部分  end****************/

        $router->get('export', 'CommonController@doExportExamLog'); //导入导出demo
    });

     $router->post('subjects', 'CourseController@subjects');//学科列表(szw改)
    //学科模块（重构）（szw）
    $router->group(['prefix' => 'coursesubject'], function () use ($router) {
        $router->post('subjectList', 'CoursesubjectController@subjectList');//学科列表
        $router->post('subjectAdd', 'CoursesubjectController@subjectAdd');//学科添加
        $router->post('subjectDel', 'CoursesubjectController@subjectDel');//学科删除
        $router->post('subjectOnes', 'CoursesubjectController@subjectOnes');//学科单条信息
        $router->post('subjectUpdate', 'CoursesubjectController@subjectUpdate');//学科修改
        $router->post('subjectForStatus', 'CoursesubjectController@subjectForStatus');//学科状态修改
		$router->post('subjectListSort', 'CoursesubjectController@subjectListSort');//学科列表排序
    });
    //课程模块（重构）（szw）
    $router->group(['prefix' => 'course'], function () use ($router) {
       // $router->post('subject', 'CourseController@subject');//学科列表   7 11 lys
        $router->post('courseList', 'CourseController@courseList');//课程列表
        $router->post('courseAdd', 'CourseController@courseAdd');//课程添加
        $router->post('courseDel', 'CourseController@courseDel');//课程删除
        $router->post('courseFirst', 'CourseController@courseFirst');//课程单条信息
        $router->post('courseUpdate', 'CourseController@courseUpdate');//课程修改
        $router->post('courseRecommend', 'CourseController@courseRecommend');//课程推荐
        $router->post('courseUpStatus', 'CourseController@courseUpStatus');//课程发布
        //录播课程
        $router->post('chapterList', 'CourseController@chapterList');//章/节列表
        $router->post('chapterAdd', 'CourseController@chapterAdd');//章添加
        $router->post('chapterDel', 'CourseController@chapterDel');//章/节删除
        $router->post('chapterUpdate', 'CourseController@chapterUpdate');//章修改
        $router->post('sectionFirst', 'CourseController@sectionFirst');//节详情
        $router->post('sectionAdd', 'CourseController@sectionAdd');//节添加
        $router->post('sectionUpdate', 'CourseController@sectionUpdate');//节修改
        $router->post('sectionDataDel', 'CourseController@sectionDataDel');//节资料删除
		$router->post('updateChapterListSort', 'CourseController@updateChapterListSort');//章节排序

        //直播课程
        $router->post('liveCourses', 'CourseController@liveCourses');//直播课程单元列表
        $router->post('liveCoursesDel', 'CourseController@liveCoursesDel');//直播课程删除资源
        $router->post('liveCoursesUp', 'CourseController@liveCoursesUp');//直播课程修改资源
        $router->post('liveToCourse', 'CourseController@liveToCourse');//直播课程取消或选择资源
        $router->post('liveToCourseList', 'CourseController@liveToCourseList');//直播课程排课
        $router->post('liveToCourseshift', 'CourseController@liveToCourseshift');//直播课程进行排课

        //转班
        $router->post('consumerUser', 'CourseController@consumerUser');//用户订单详情
        $router->post('courseDetail', 'CourseController@courseDetail');//课程详情
        $router->post('coursePay', 'CourseController@coursePay');//转班费用
        $router->post('classTransfer', 'CourseController@classTransfer');//进行转班

		//复制课程
        $router->post('getCopyCourseSubjectInfo', 'CourseController@getCopyCourseSubjectInfo');//获取复制课程学科信息
        $router->post('getCopyCourseInfo', 'CourseController@getCopyCourseInfo');//获取复制课程
        $router->post('copyCourse', 'CourseController@copyCourseInfo');//复制课程
    });
    //运营模块(szw)`
    $router->group(['prefix' => 'article'], function () use ($router) {
        /*------------文章模块---------------------*/
        $router->post('getArticleList', 'ArticleController@getArticleList');//获取文章列表
        $router->post('schoolList', 'ArticleController@schoolList');//学校列表
        $router->post('addArticle', 'ArticleController@addArticle');//新增文章
        $router->post('editStatusToId', 'ArticleController@editStatusToId');//文章启用&禁用
        $router->post('editDelToId', 'ArticleController@editDelToId');//文章删除
        $router->post('findToId', 'ArticleController@findToId');//获取单条文章数据
        $router->post('exitForId', 'ArticleController@exitForId');//文章修改
        $router->post('recommendId', 'ArticleController@recommendId');//课程发布
        /*------------文章分类模块------------------*/
        $router->post('addType', 'ArticletypeController@addType');//文章分类添加
        $router->post('getTypeList', 'ArticletypeController@getTypeList');//获取文章分类列表
        $router->post('editStatusForId', 'ArticletypeController@editStatusForId');//文章分类禁用&启用
        $router->post('exitDelForId', 'ArticletypeController@exitDelForId');//文章分类删除
        $router->post('exitTypeForId', 'ArticletypeController@exitTypeForId');//文章分类修改
        $router->post('OnelistType', 'ArticletypeController@OnelistType');//单条查询
		/*------------评论回复模块------------------*/
        $router->post('getCommentList', 'ArticleController@getCommentList');//评论列表
        $router->post('editCommentToId', 'ArticleController@editCommentToId');//文章启用&禁用
		/*------------问答模块------------------*/
        $router->post('getAnswersList', 'ArticleController@getAnswersList');//问答列表
        $router->post('editAnswersTopStatus', 'ArticleController@editAnswersTopStatus');//置顶
        $router->post('addAnswersReply', 'ArticleController@addAnswersReply');//回复问答
        $router->post('editAnswersReplyStatus', 'ArticleController@editAnswersReplyStatus');//回复状态
        $router->post('editAnswersStatus', 'ArticleController@editAnswersStatus');//问答审核
		$router->post('editAllAnswersIsCheckStatus', 'ArticleController@editAllAnswersIsCheckStatus');//问答一键审核状态
		$router->post('delAllAnswersStatus', 'ArticleController@delAllAnswersStatus');//批量删除
    });
    //订单&支付模块(szw)
    $router->group(['prefix' => 'order'], function () use ($router) {
        $router->post('orderList', 'OrderController@orderList');//订单列表
        $router->post('findOrderForId', 'OrderController@findOrderForId');//订单详情
        $router->post('auditToId', 'OrderController@auditToId');//订单审核通过/不通过
        $router->post('ExcelExport', 'OrderController@ExcelExport');//订单导出
        $router->post('buttOa', 'OrderController@buttOa');//对接oa
        $router->post('orderBack', 'OrderController@orderBack');//退回
        //扫码支付模块
        $router->post('scanOrderList', 'OrderController@scanOrderList ');//扫码支付列表
    });
    //数据模块（szw）
    $router->group(['prefix' => 'statistics'], function () use ($router) {
        $router->post('StudentList', 'StatisticsController@StudentList');//学员统计
        $router->post('TeacherList', 'StatisticsController@TeacherList');//教师统计
        $router->post('TeacherClasshour', 'StatisticsController@TeacherClasshour');//教师课时详情
//        $router->post('LiveList', 'StatisticsrController@LiveList');//直播统计
//        $router->post('LiveDetails', 'StatisticsrController@LiveDetails');//直播详情
    });

    /*begin 系统管理   lys   */
    //系统用户管理模块
    $router->group(['prefix' => 'adminuser'], function () use ($router) {
        $router->post('getAdminUserList', 'AdminUserController@getAdminUserList');            //获取后台用户列表方法 √ 5.8
        $router->post('upUserForbidStatus', 'AdminUserController@upUserForbidStatus');        //更改账号状态方法（启用禁用） √√√ +1
        $router->post('upUserDelStatus', 'AdminUserController@upUserDelStatus');              //更改账号状态方法 (删除)  √√√  +1
        $router->post('getInsertAdminUser', 'CommonController@getInsertAdminUser');           //获取添加账号信息（school，roleAuth）方法 √
        $router->post('doInsertAdminUser', 'AdminUserController@doInsertAdminUser');          //添加账号方法 √  +1
        $router->post('getAdminUserUpdate', 'AdminUserController@getAdminUserUpdate');        //获取账号信息（编辑） √√√
        $router->post('doAdminUserUpdate', 'AdminUserController@doAdminUserUpdate');          //编辑账号信息  √√  5.9  +1
        $router->post('doAdminUserUpdatePwd', 'AdminUserController@doAdminUserUpdatePwd');    //修改用户密码的接口

        $router->post('getAuthList', 'RoleController@getRoleList');                           //获取后台角色列表方法
        $router->post('getLoginUserInfo', 'AuthenticateController@getLoginUserInfo');
    });

    $router->group(['prefix' => 'payset'], function () use ($router) {
        $router->post('getList', 'PaySetController@getList');                                 //获取支付配置列表
        $router->post('doUpdatePayState', 'PaySetController@doUpdatePayState');               //更改支付状态
        $router->post('doUpdateWxState', 'PaySetController@doUpdateWxState');                 //更改微信状态
        $router->post('doUpdateZfbState', 'PaySetController@doUpdateZfbState');               //更改支付宝状态
        $router->post('doUpdateHjState', 'PaySetController@doUpdateHjState');                 //更改汇聚状态
        $router->post('doUpdateYlState', 'PaySetController@doUpdateYlState');                 //更改银联状态
        $router->post('doUpdateHfState', 'PaySetController@doUpdateHfState');                 //更改汇付状态
        $router->post('getZfbById', 'PaySetController@getZfbConfig');                       //添加支付宝配置(获取)
        $router->post('getWxById', 'PaySetController@getWxConfig');                         //添加微信配置(获取)
        $router->post('getHjById', 'PaySetController@getHjConfig');                         //添加汇聚配置(获取)
        $router->post('getYlById', 'PaySetController@getYlConfig');                         //添加银联配置(获取)
        $router->post('getHfById', 'PaySetController@getHfConfig');                         //添加汇付配置(获取)
        $router->post('doZfbUpdate', 'PaySetController@doZfbConfig');                       //添加/修改支付宝配置
        $router->post('doWxUpdate', 'PaySetController@doWxConfig');                         //添加/修改微信配置
        $router->post('doHjUpdate', 'PaySetController@doHjConfig');                         //添加/修改汇聚配置
        $router->post('doYlUpdate', 'PaySetController@doYlConfig');                         //添加/修改银联配置
        $router->post('doHfUpdate', 'PaySetController@doHfConfig');                         //添加/修改汇付配置

    });

    //系统角色管理模块
    $router->group(['prefix' => 'role'], function () use ($router) {
        $router->post('doRoleDel', 'RoleController@doRoleDel');                                //修改状态码(删除) √   +1
        $router->post('getRoleAuthInsert', 'RoleController@getRoleInsert');                   //获取role_auth列表 √√
        $router->post('doRoleAuthInsert', 'RoleController@doRoleInsert');                     //添加角色方法 √√ +1
        $router->post('getRoleAuthUpdate', 'RoleController@getRoleInfo');               // 获取角色信息（编辑）√√
        $router->post('doRoleAuthUpdate', 'RoleController@doRoleUpdate');                 //编辑角色信息  √√ +1
    });
    /*end 系统管理  */

    $router->group(['prefix' => 'user'], function () use ($router) { //用户学员相关模块方法
        $router->post('postUserList', 'UserController@postUserList'); //获取学员列表方法
    });

    /*begin 网校系统  lys*/

    $router->group(['prefix' => 'school'], function () use ($router) {
        $router->post('getSchoolList', 'SchoolController@getSchoolList');                    //获取网校列表方法 √√√
        $router->post('doSchoolForbid', 'SchoolController@doSchoolForbid');                  //修改学校状态 （禁启)√√
        $router->post('doSchoolDel', 'SchoolController@doSchoolDel');                         //修改学校状态 （删除) √√
        $router->post('doInsertSchool', 'SchoolController@doInsertSchool');                  //添加分校信息并创建分校管理员 √√  +1
        $router->post('getSchoolUpdate', 'SchoolController@getSchoolUpdate');                //获取分校信息（编辑）√√
        $router->post('doSchoolUpdate', 'SchoolController@doSchoolUpdate');                  //编辑分校信息  √√   +1
        $router->post('getSchoolAdminById', 'SchoolController@getSchoolAdminById');          //查看分校超级管理角色信息 √√
        $router->post('doSchoolAdminById', 'SchoolController@doSchoolAdminById');            //编辑分校超级管理角色信息（给分校超管赋权限） √√
        $router->post('getAdminById', 'SchoolController@postAdminById');                     //获取分校超级管理用户信息（编辑） √√
        $router->post('doAdminUpdate', 'SchoolController@doAdminUpdate');                    //编辑分校超级管理用户信息   √√  +1
        $router->post('getSchoolTeacherList', 'SchoolController@getSchoolTeacherList');      //获取分校讲师列表  √√√  5.11
        $router->post('getLessonList', 'SchoolController@getLessonLists');      //获取分校课程列表
        $router->post('getOpenLessonList', 'SchoolController@getOpenLessonList');      //获取分校公开课列表
        $router->post('getSubjectList', 'SchoolController@getSubjectList');      //获取课程/公开课学科大类小类
        $router->post('details','SchoolController@details'); //获取网校详情
        $router->post('getManageSchoolToken', 'SchoolController@getManageSchoolToken');                    //获取管理网校的token （用于）

        $router->post('getConfig', 'SchoolController@getConfig');                    //获取网校的设置数据
        $router->post('setConfig', 'SchoolController@setConfig');                    //设置网校数据
        $router->post('getSEOConfig', 'SchoolController@getSEOConfig');                    //获取SEO数据
        $router->post('setPageSEOConfig', 'SchoolController@setPageSEOConfig');                    //设置页面SEO数据
        $router->post('setSEOOpen', 'SchoolController@setSEOOpen');                    //获取SEO控制开关

    });

    $router->group(['prefix' => 'courschool'], function () use ($router) {
        $router->post('test', 'CourseSchoolController@test');  //测试
        $router->post('courseIdList', 'CourseSchoolController@courseIdList');  //授权分校课程ID
        $router->post('courseList', 'CourseSchoolController@courseList');  //授权课程列表
        $router->post('courseStore', 'CourseSchoolController@store');  //批量添加
        $router->post('courseCancel', 'CourseSchoolController@courseCancel');  //批量取消授权
        $router->post('authorUpdate', 'CourseSchoolController@authorUpdate');  //授权更新
        $router->post('getNatureSubjectList', 'CourseSchoolController@getNatureSubjectOneByid');  //授权课程列表大类
        $router->post('getNatureSubjectByid', 'CourseSchoolController@getNatureSubjectTwoByid');  //授权课程列表小类
    });
    $router->group(['prefix' => 'courstocks'], function () use ($router) {
        $router->post('getList', 'CourseStocksController@getList');  //库存列表
        $router->post('doInsertStocks', 'CourseStocksController@doInsertStocks');  //添加库存
    });
    $router->group(['prefix' => 'pageset'], function () use ($router) {
        $router->post('getList', 'PageSetController@getList');  //页面设置 列表
        $router->post('details', 'PageSetController@details');  //详情
        $router->post('addInfo', 'PageSetController@addInfo');  //添加
        $router->post('editInfo', 'PageSetController@editInfo');  //修改
        $router->post('delInfo', 'PageSetController@delInfo');  //删除
        $router->post('openInfo', 'PageSetController@openInfo');  //开启关闭
        $router->post('sortInfo', 'PageSetController@sortInfo');  //排序

        $router->post('doLogoUpdate', 'PageSetController@doLogoUpdate');  //修改logo
    });


    //end 网校系统     lys


    //课程模块（重构）【公开课】（lys）
    $router->group(['prefix' => 'opencourse'], function () use ($router) {
        $router->post('subject', 'OpenCourseController@subject');//公开课程学科列表(lys改)
        $router->post('getList', 'OpenCourseController@getList');//公开课列表
        $router->post('doInsertOpenCourse', 'OpenCourseController@doInsertOpenCourse');//公开课添加
        $router->post('doUpdateRecomend', 'OpenCourseController@doUpdateRecomend');//是否推荐
        $router->post('doUpdateStatus', 'OpenCourseController@doUpdateStatus');//修改状态
        $router->post('doUpdateDel', 'OpenCourseController@doUpdateDel');//是否删除
        $router->post('getOpenLessById', 'OpenCourseController@getOpenLessById');//修改(获取)
        $router->post('doOpenLessById', 'OpenCourseController@doOpenLessById');//修改
        $router->post('zhiboMethod', 'OpenCourseController@zhiboMethod');//直播类型
    });
    //教学模块
    $router->group(['prefix' => 'teach'], function () use ($router) {
        $router->post('getList', 'TeachController@getList');//教学列表
        $router->post('startLiveChild', 'TeachController@startLive');  //启动直播
        $router->post('liveInRoom', 'TeachController@liveInRoom');  //进入直播间
        $router->post('livePlayback','TeachController@livePlayback');  //课程回放
        $router->post('coursewareUpload','TeachController@courseUpload');  //课件上传
        $router->post('details','TeachController@details');  //教学详情
        $router->post('coursewareDel','TeachController@coursewareDel');  //课件删除（欢拓）
    });

    //控制台 zhaolaoxian
    $router->group(['prefix' => 'dashboard' ], function () use ($router) {
        //首页
        $router->addRoute(['GET','POST'],'index', 'SchoolDataController@index');
        //对账数据
        $router->addRoute(['GET','POST'],'orderlist', 'SchoolDataController@orderList');
        //对账数据导出
        $router->addRoute(['GET','POST'],'orderExport', 'SchoolDataController@orderExport');
        //分校信息 admin/school/getSchoolUpdate
        //修改分校 admin/school/doSchoolUpdate
        //修改状态 -> admin/school/doSchoolForbid

        //课程详情
        $router->group(['prefix' => 'course'], function () use ($router) {
            $router->addRoute(['GET','POST'],'detailStocks', 'SchoolCourseDataController@Stocks');//库存数据
            //学科 -> admin/school/getSubjectList 	   [school_id: 学校 , is_public: 级别]
            //讲师 -> admin/school/getSchoolTeacherList [school_id: 学校]
            //课程 -> admin/school/getLessonList        [subjectOne: 学科, subjectTwo: 学科, school_id: 学校, page: 页码, pagesize: 页大小, search: 关键字 ]
            //公开课 -> admin/school/getOpenLessonList   [subjectOne: 学科, subjectTwo: 学科, school_id: 学校, page: 页码, pagesize: 页大小]
            $router->addRoute(['GET','POST'],'addMultiStocks', 'SchoolCourseDataController@addMultiStocks');//批量添加库存
        });

        //购买服务
        $router->group(['prefix' => 'purservice'], function () use ($router) {
            $router->addRoute(['GET','POST'],'getPrice', 'PurServiceController@getPrice');//获取价格
            $router->addRoute(['GET','POST'],'getStorageDetail', 'PurServiceController@getStorageDetail');//获取空间详情
            $router->addRoute(['GET','POST'],'live', 'PurServiceController@purLive');//直播
            $router->addRoute(['GET','POST'],'storage', 'PurServiceController@purStorage');//空间
            $router->addRoute(['GET','POST'],'flow', 'PurServiceController@purFlow');//流量
        });

        //手动打款
        $router->group(['prefix' => 'account'], function () use ($router) {
            $router->addRoute(['GET','POST'],'getAccount', 'SchoolAccountController@getAccountList');//列表
            $router->addRoute(['GET','POST'],'recharge', 'SchoolAccountController@addAccount');//充值
            $router->addRoute(['GET','POST'],'detail', 'SchoolAccountController@detail');//单条详情
        });

        //线下订单
        $router->group(['prefix' => 'offlineOrder'], function () use ($router) {
            $router->addRoute(['GET','POST'],'index', 'SchoolOrderController@index');//列表
            $router->addRoute(['GET','POST'],'searchKey', 'SchoolOrderController@searchKey');//搜索框内容
            $router->addRoute(['GET','POST'],'detail', 'SchoolOrderController@detail');//列表
            $router->addRoute(['GET','POST'],'operate', 'SchoolOrderController@operate');//审核
        });

        //直播服务商
        $router->group(['prefix' => 'liveService'], function () use ($router) {
            $router->addRoute(['GET','POST'],'add', 'LiveServiceController@add');//增
            $router->addRoute(['GET','POST'],'index', 'LiveServiceController@index');//列表
            $router->addRoute(['GET','POST'],'detail', 'LiveServiceController@detail');//单条
            $router->addRoute(['GET','POST'],'doedit', 'LiveServiceController@edit');//改
            $router->addRoute(['GET','POST'],'delete', 'LiveServiceController@delete');//删
            $router->addRoute(['GET','POST'],'multi', 'LiveServiceController@multi');//批量更新
            $router->addRoute(['GET','POST'],'updateLivetype', 'LiveServiceController@updateLivetype');//为网校更改直播商
        });

    });

    //服务
    $router->group(['prefix' => 'service' ], function () use ($router) {
        //订单
        $router->addRoute(['GET','POST'],'orderIndex', 'ServiceController@orderIndex');
        //订单查看
        $router->addRoute(['GET','POST'],'orderDetail', 'ServiceController@orderDetail');
        //充值
        $router->addRoute(['GET','POST'],'recharge', 'ServiceController@recharge');

        //购买直播并发
        $router->addRoute(['GET','POST'],'purLive', 'ServiceController@purLive');
        //空间续费
        $router->addRoute(['GET','POST'],'purStorageDate', 'ServiceController@purStorageDate');
        //空间容量
        $router->addRoute(['GET','POST'],'purStorage', 'ServiceController@purStorage');
        //流量
        $router->addRoute(['GET','POST'],'purFlow', 'ServiceController@purFlow');

        //库存
        $router->group(['prefix' => 'stock' ], function () use ($router) {
            //点击退费时弹出框的展示信息
            $router->addRoute(['GET','POST'], 'Refund', 'ServiceController@stockRefund');
            //根据退费库存数量返回可退费金额
            $router->addRoute(['GET','POST'], 'refundMoney', 'ServiceController@stockRefundMoney');
            //执行退费
            $router->addRoute(['GET','POST'], 'doRefund', 'ServiceController@doStockRefund');
            //加入购物车
            $router->addRoute(['GET','POST'], 'addShopCart', 'ServiceController@addShopCart');
            //购物车查看
            $router->addRoute(['GET','POST'], 'shopCart', 'ServiceController@shopCart');
            //购物车数量管理
            $router->addRoute(['GET','POST'], 'shopCartManageOperate', 'ServiceController@shopCartManageOperate');
            //购物车删除
            $router->addRoute(['GET','POST'], 'shopCartManageDel', 'ServiceController@shopCartManageDel');
            //购物车结算
            $router->addRoute(['GET','POST'], 'shopCartPay', 'ServiceController@shopCartPay');
            //更换库存页面
            $router->addRoute(['GET','POST'], 'preReplace', 'ServiceController@preReplaceStock');
            //获取当前退换库存需补充或退还的金额
            $router->addRoute(['GET','POST'], 'replaceDetail', 'ServiceController@replaceStockDetail');
            //执行退还库存
            $router->addRoute(['GET','POST'], 'doReplace', 'ServiceController@doReplaceStock');
            //库存订单
            $router->addRoute(['GET','POST'], 'order', 'ServiceController@stockOrder');

        });

    });
	
	//财务模块
    $router->group(['prefix' => 'finance'], function () use ($router) {
        $router->post('details', 'OrderController@financeDetails');//财务详情
        $router->post('search_subject', 'OrderController@search_subject');//财务详情搜索-学科
        $router->post('search_course', 'OrderController@search_course');//财务详情搜索-课程
    });

});



/*****************end**********************/
