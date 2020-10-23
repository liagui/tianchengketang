<?php

/**
 * ysh
 * 2020-10-23
 */
namespace App\Services\Admin\School;


use App\Tools\CurrentAdmin;

class SchoolService
{

    /**
     * 获取
     * @param $schoolId
     */
    public function getManageSchoolToken($schoolId)
    {
        $a = CurrentAdmin::user();


    }
}
