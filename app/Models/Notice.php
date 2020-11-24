<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model {
    //指定别的表名
    public $table      = 'ld_notice';
    //时间戳设置
    public $timestamps = false;


    /**
     * 当条新增 通知
     * @param $schoolId 学校id - 数字
     * @param $noticeType 通知类型 - 文字
     * @param $title 标题
     * @param $text 内容
     */
    public static function addSin($schoolId, $noticeType, $title, $text)
    {
        return self::query()
            ->insertGetId([
                'school_id' => $schoolId,
                'notice_type' => $noticeType,
                'title' => $title,
                'text' => $text
            ]);
    }

    /**
     * 多条新增
     * @param $data 二维数组
     *  [
     *      [
                'school_id' => $schoolId,
                'notice_type' => $noticeType,
                'title' => $title,
                'text' => $text
     *      ]
     * ]
     */
    public static function addMul($data)
    {
        self::query()->insert($data);
        return true;
    }
}
