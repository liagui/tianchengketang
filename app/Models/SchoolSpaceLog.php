<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

// use App\Models\Teacher;
// use App\Models\Admin;
// use App\Models\CouresSubject;
// use App\Models\Coures;
// use App\Models\Couresmethod;

use App\Tools\CurrentAdmin;

class SchoolSpaceLog extends Model
{
    //指定别的表名
    public $table = 'ld_school_space_log';

    // region schoolspace表中的常量

    // 'video','doc','audio','picture' 空间使用类型
    const USE_TYPE_VIDEO = "video";
    const USE_TYPE_DOC = "doc";
    const USE_TYPE_AUDIO = "audio";
    const USE_TYPE_PICTURE = "picture";

    // 'add','use' 空间变化情况
    const SPACE_USE = "use";
    const SPACE_ADD = "add";

    // endregion

    // region 空间使用情况的相关函数


    public function addLog(string $school_id,int $space_used, string $used_type, string $type, int $before_space,string $log_date){

        $data = array(
            "school_id" => $school_id,
            "space_used" => $space_used,
            "type" => $type,
            "before_space" => $before_space,
            "log_date" => $log_date
        );
        // 如果空间使用类型空 那么 有可能是 空间扩容
        !empty($used_type)?$data["used_type"] = $used_type:"";

        return $this->newModelQuery()->insert($data);


    }

    // endregion


}


