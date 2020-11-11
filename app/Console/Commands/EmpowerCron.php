<?php


namespace App\Console\Commands;


use App\Services\Admin\Course\CourseService;
use App\Services\Admin\Course\OpenCourseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EmpowerCron extends Command
{
    /**
     * 命令行执行命令
     * @var string
     */
    protected $signature = 'EmpowerCron {func}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '课程授权';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $func = $this->argument('func');
        $this->$func();
//        Log::info('任务调度', ['time' => date('Y-m-d H:i:s')]);
//        //这里编写需要执行的动作
    }

    /**
     * 课程
     */
    public function course()
    {

        $courseService = new CourseService();
        $courseService->courseEmpowerCron();

        Log::info('课程授权', ['time' => date('Y-m-d H:i:s')]);
    }

    /**
     * 公开课
     */
    public function open()
    {
        $openCourseService = new OpenCourseService();
        $openCourseService->openCourseEmpowerCron();
        Log::info('公开课授权', ['time' => date('Y-m-d H:i:s')]);
    }

}
