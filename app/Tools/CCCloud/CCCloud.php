<?php
// 测试代码

namespace App\Tools\CCCloud;

//require_once(__DIR__."CCCloudLiveRoomInfo.php");
/*=============================================================================
#     FileName: CCCloud.php
#         Desc: CC 直播 API PHP SDK
#   LastChange: 2020年10月19日19:46:20
#      History:
=============================================================================*/

//use Illuminate\Support\Facades\Config;
//use App\Tools\CCCloud\CCCloudLiveRoomInfo;
//use CCCloudLiveRoomInfo;


/**
 *  CC语音视频服务开放接口SDK
 *  CC sdk分成为 直报和 点播等业务
 *  本sdk 只封装 直播和点播的部分功能
 *
 *  点播和直播的业务接口
 * Class CCCloud
 */
class CCCloud
{
    const RET_IS_OK = "0";
    const RET_IS_ERR = "201";
    //region CC 直播的 配置设定默认写在 代码里
    /** @var string cc直播使用的 userid */
    private $_USER_ID = "788A85F7657343C2";

    /** @var string cc直播使用的 api key */
    private $_api_key_for_live = "TUUddgHOPhX2n1xGVldnLfbkmFrUc4Sa";

    /** @var string cc点播使用的 秘钥 */
    private $_api_key_for_demand = "LCP6xcucI61lSawLqLzli8QbCc8DDtFX";

    // 点播 api 的地址
    private $_url_spark = "https://spark.bokecc.com";

    // 直播 api 的地址
    private $_url_csslcloud = "http://api.csslcloud.net";

    // 点播业务的错误代码
    private $_format_error_fro_demand = array(
        "INVALID_REQUEST"  => "用户输入参数错误",
        "SPACE_NOT_ENOUGH" => "用户剩余空间不足",
        "SERVICE_EXPIRED"  => "用户服务已经过期",
        "PROCESS_FAIL"     => "服务器处理失败",
        "TOO_MANY_REQUEST" => "访问过于频繁",
        "PERMISSION_DENY"  => "用户服务无权限"
    );
    // 直报业务的错误代码
    private $_format_error_for_live = array(
        "invalid param"     => "请求参数无效，请检查参数名称以及参数值是否正确",
        "invalid encrypt"   => "接口THQS加密请求无效",
        "database error"    => "系统内部错误，请稍后重试",
        "system error"      => "系统内部错误，请稍后重试",
        "invalid operation" => "操作无效，例如：直播间有直播正在进行时，关闭直播间则报此类错误",
    );


    /**
     *  从配置文件 加载配置
     */
    private function loadFromEnv()
    {
        //从环境配置文件中加载 CC 的配置信息
        // 加载 USER_ID
        !empty(env("CC_USER_ID")) ? $this->_USER_ID = env("CC_USER_ID") : "";
        // 加载 USER_ID
        !empty(env("CC_API_KEY_FOR_LIVE")) ? $this->_api_key_for_live = env("CC_API_KEY_FOR_LIVE") : "";
        // 加载 TOKEN_PUBLIC_KEY
        !empty(env("CC_API_KEY_FOR_DEMAND")) ? $this->_api_key_for_demand = env("CC_API_KEY_FOR_DEMAND") : "";

    }

    /**
     *  CC 直播的封装后的标准返回结果
     *  会返回一个 code 无论是发生错误还是 正确的结果
     *  这里 结合了前端使用 欢托的api 返回结果 格式 如果错误那么返回的数据中
     *  code > 0 并且 msg 中有中文的相关说明
     *
     * @param int $ret_is_ok
     * @param array $ret_vars
     * @return array
     */
    private function format_api_return(int $ret_is_ok, array $ret_vars)
    {
        if ($ret_is_ok == self::RET_IS_OK) {
            return (array( "code" => self::RET_IS_OK, "data" => $ret_vars ));
        } else {
            return array( "code" => self::RET_IS_ERR, "msg" => $ret_vars[ 'reason' ] );
        }
    }

    // region 中间层函数

    /**
     *  创建直报房间
     *   返回结果中含有 room_id
     * @param string $name 直播房间名称
     * @param string $desc 直播房间的描述
     * @param string $publisherpass 主持人段密码
     * @param string $assistantpass 助教端密码
     * @param string $playpass
     * @param string $templatetype 房间界面模板
     * @param int $authtype 验证方式 默认即可
     * @return array|false|mixed
     */
    public function create_room(string $name, string $desc, string $publisherpass, string $assistantpass,
                                string $playpass, string $templatetype = "5", $authtype = 2,array $ext_attr)
    {
        return $this->cc_room_create($name, $desc, $templatetype, $authtype, $publisherpass,
            $assistantpass, $playpass,$ext_attr);
    }

    /**
     *  更新直播间信息
     * @param string $room_id
     * @param string $name
     * @param string $desc
     * @param string $barrage
     * @param array $ext_attr
     * @return array
     */
    public function update_room_info( string $room_id, string $name, string $desc, string $barrage,array $ext_attr)
    {
        $data[ 'name' ] = $name;
        $data[ 'desc' ] = $desc;
        $data[ 'barrage' ] = $barrage;
        $data = array_merge($data, $ext_attr);
        return $this->cc_room_update($room_id,$data);
    }

    /**
     *  打开直播间  默认直播间需要使用这个api 使直播间处于可用阶段
     * @param string $room_id
     * @return array
     */
    public function open_room(string $room_id)
    {
        return $this->cc_room_open($room_id);
    }

    /**
     *  关闭一个直播间 使直播间处于不可播放状态
     * @param $room_id
     */
    public function close_room($room_id)
    {
        return $this->cc_room_close($room_id);
    }


    /**
     * courseLaunch 和
     * @param $room_id
     * @param string $publisherpass
     * @param string $assistantpass
     * @param string $playpass
     * @return array
     */
    public function start_live($room_id, string $publisherpass, string $assistantpass, string $playpass)
    {
        /**
         *  这里api 模拟和欢托sdk一样的返回结果 目前需要处理一下的返回值
         *
         * url    string    登录页面地址、  cc 直播 返回web观看地址
         * spUrl    string    管理员登录页面地址 CC 直播不返回
         * protocol    string    Windows启动协议，链接地址为协议内容，放在网页链接里面，点击可以启动直播器
         * protocolMac    string    Mac启动协议，链接地址为协议内容，放在网页链接里面，点击可以启动直播器、
         * **protocol 和 protocolMac 这里只返回 web观看启动地址**
         * download    string    Win直播器下载地址 CC 直播无返回
         * downloadMac    string    Mac直播器下载地址 CC 直播无返回
         * token    string    登录验证token，主播直播器终端（Windows,Mac,App,网页）验证自动登录用
         * **token 这里等同CC 的直播密码 默认空**
         * spToken    string    管理员登录验证token，主播直播器终端（Windows,Mac,App,网页）验证自动登录用
         * **spToken 这里等同 管理员密码**
         *
         */
        // 第一步 打开直播房间是直播房间处于可以直播状态
        $ret = $this->cc_room_open($room_id);
        if ($ret[ 'code' ] != self::RET_IS_OK) {
            return $ret;
        }
        // 第二部 获取CC 直播房间地code
        $ret = $this->cc_rooms_code($room_id);
        if ($ret[ 'code' ] != self::RET_IS_OK) {
            return $ret;
        }

        $ret_data = array(
            "url"           => $ret[ "data" ][ 'viewUrl' ],
            "spUrl"         => $ret[ "data" ][ 'viewUrl' ],
            "protocol"      => $ret[ "data" ][ 'clientLoginUrl' ],
            "protocolMac"   => $ret[ "data" ][ 'clientLoginUrl' ],
            "download"      => $ret[ "data" ][ 'viewUrl' ],
            "downloadMac"   => $ret[ "data" ][ 'viewUrl' ],
            "token"         => "",
            "spToken"       => $publisherpass,
            "room_id"       => $room_id,
            "publisherpass" => $publisherpass,
            "assistantpass" => $assistantpass

        );

        return $this->format_api_return(self::RET_IS_OK, $ret_data);

    }


    /**
     *  这个api  模拟 欢托的 courseAccess 这个api
     *  返回直播的 客户端的播放id
     * @param $room_id
     * @return array
     */
    public function get_room_live_code($room_id){
        /**
         * playbackUrl	string	回放地址
         * liveUrl	string	直播地址
         * liveVideoUrl	string	直播视频外链地址
         * access_token	string	用户的access_token
         * playbackOutUrl	string	回放纯视频播放地址
         * miniprogramUrl	string	小程序web-view的直播或回放地址（未传miniprogramAppid参数时返回默认域名的直播或回放地址）
         *
         */
        // 获取CC 直播房间地code
        $ret = $this->cc_rooms_code($room_id);
        if ($ret[ 'code' ] != self::RET_IS_OK) {
            return $ret;
        }

        // 返回和 欢托sdk 一致的数据
        return $this->format_api_return(self::RET_IS_OK, array(
            "playbackUrl" => "",         // 回放地址
            "liveUrl" => $ret['data']['viewUrl'],             // 直播地址
            "liveVideoUrl" => "",        // 直播视频外链地址
            "access_token" => "",        // 用户的access_token
            "playbackOutUrl" => "",      // 回放纯视频播放地址
            "miniprogramUrl" => "",      // 小程序web-view的直播或回放地址

        ));
    }


    /**
     *  查看直播房间的回放记录 默认CC 直播只会有一个回放记录
     *  模拟  courseAccessPlayback 的返回结果
     * @param $room_id
     */
    public  function  get_room_live_recode_code($room_id){
        // 获取该直播间下的所有 直播回放
        $live_recode_data = $this->cc_live_info($room_id);
        if ($live_recode_data[ 'code' ] != self::RET_IS_OK) {
            // 发生任何错误 返回
            return $live_recode_data;
        }
        $recode_list = $live_recode_data["data"]["record"];
        // 这里 只处理第一个回放的记录
        $first_recode = $recode_list[0];
        if(!empty($first_recode)){
            return $this->format_api_return(self::RET_IS_ERR,"没有回放记录！");
        }

        // 如果默认的直播回放还在生成中
        if(!empty($first_recode['recordStatus']) and $first_recode['recordStatus'] != "1" ){
            return $this->format_api_return(self::RET_IS_ERR,"回放录制中");
        }

        // 返回和 欢托sdk 一致的数据
        return $this->format_api_return(self::RET_IS_OK, array(
            "playbackUrl" => $first_recode['downloadUrl'],             // 回放地址
            "liveUrl" => $first_recode['replayUrl'],             // 直播地址
            "liveVideoUrl" => "",        // 直播视频外链地址
            "access_token" => "",        // 用户的access_token
            "playbackOutUrl" => "",      // 回放纯视频播放地址
            "miniprogramUrl" => "",      // 小程序web-view的直播或回放地址

        ));

    }


    // endregion


    // region 封装的CC直播的api

    /**
     *  检查api调用的结果 是否成果
     *  如果调用失败 那么返回 false 同时格式化返回结果
     * @param $ret_vars
     * @return bool
     */
    private function format_api_error_for_live(&$ret_vars)
    {
        if (!empty($ret_vars[ 'result' ] and $ret_vars[ 'result' ] == "FAIL")) {
            $ret_vars[ 'reason' ] = $this->_format_error_for_live[ $ret_vars[ 'reason' ] ];
            return false;
        } else {
            return true;
        }
    }

    /**
     *  创建直播间
     *  传入 info 创建直播间的参数
     * @param string $name 直播间的名字
     * @param string $desc 直播间的描述
     * @param string $templatetype 直播间的模板
     * @param int $authtype 验证方式
     * @param string $publisherpass 推流端密码，即讲师密码
     * @param string $assistantpass 助教端密码
     * @param $playpass
     * @return array|false|mixed 返回结果 false 调用失败 array ( room_id,publishUrls )
     */
    private function cc_room_create(string $name, string $desc, string $templatetype, $authtype = 5,
                                    string $publisherpass, string $assistantpass, string $playpass,array $ext_attr)
    {
        $data[ 'name' ] = $name;
        $data[ 'desc' ] = $desc;
        $data[ 'templatetype' ] = $templatetype;
        $data[ 'authtype' ] = $authtype;
        $data[ 'publisherpass' ] = $publisherpass;
        $data[ 'assistantpass' ] = $assistantpass;
        $data[ 'playpass' ] = $playpass;

        // 这里 拼接 附加 属性 到房间信息中
        if(!empty($ext_attr)){
            $data = array_merge($data,$ext_attr);
        }

        // 调用 api /api/room/create 创建 直播间
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/room/create", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            // 调用成功  那么返回room_id 和publishUrls
            return $this->format_api_return(self::RET_IS_OK, array(
                "room_id"     => $ret[ "room" ][ 'id' ],
                "publishUrls" => $ret[ "room" ][ "publishUrls" ]
            ));
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }

    /**
     *  更新 已经一个直播间的 信息
     * @param string $room_id
     * @param array $info
     * @return array
     */
    private function cc_room_update(string $room_id,array $info)
    {
        // 创建直播房间
        // $room_info = $this->object_to_array($info);

        $room_info = $info;
        $room_info["roomid"] = $room_id;
        // 调用 api /api/room/update 创建 直播间
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/room/update", $this->_api_key_for_live, $room_info);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }

    }


    /**
     *  关闭一个直播房间
     * @param string $room_id
     * @return array
     */
    private function cc_room_close(string $room_id)
    {
        $data[ 'roomid' ] = $room_id;
        // 调用 api /api/room/close 创建 直播间
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/room/close", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }

    /**
     *  关闭一个直播房间
     * @param string $room_id
     * @return array
     */
    private
    function cc_room_open(string $room_id)
    {
        $data[ 'roomid' ] = $room_id;
        // 调用 api /api/room/open 开启 直播间
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/room/open", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }


    /**
     *  删除 一个直播间 注意 删除直播间相应的回放信息也会删除
     * @param string $room_id
     * @return array
     */
    private
    function cc_room_delete(string $room_id)
    {
        $data[ 'roomid' ] = $room_id;
        // 调用 api /api/room/delete 创建 直播间
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/room/delete", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }

    /**
     *  获取在线的直播列表  这个接口地命名有问题
     *  info 其实是list
     *  接口返回
     * pageindex    页码
     * count    直播间总数
     * rooms    直播间列表信息 该信息类似于 CCCloudLiveRoomInfo
     *
     * @param int $pagenum 每页多少条 系统默认值为50，最大值为100
     * @param int $pageindex 可选，系统默认值为1
     * @return array
     */
    private function cc_room_info($pagenum = 100, $pageindex = 1)
    {
        // 组合 参数
        $data[ 'pagenum' ] = $pagenum;
        $data[ 'pageindex' ] = $pageindex;

        // 调用 api /api/room/info 创建 直播间
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/room/info", $this->_api_key_for_live, $data);

        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }

    }

    /**
     *   获取一个直播间的信息
     *    名字有点奇怪 search 其实是 info
     * @param string $room_id
     * @return array
     */
    private function cc_room_search(string $room_id)
    {
        $data[ 'roomid' ] = $room_id;
        // 调用 api /api/room/search 开启 直播间
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/room/search", $this->_api_key_for_live, $data);
        print_r($ret);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        print_r($check_ret); print_r($ret);

        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
        exit(0);
    }


    /**
     *  通过该接口获取指定直播间下历史直播信息，
     *  正确后返回一下的信息
     *   count    直播总数
     *   pageIndex    页码
     *   lives    直播列表信息 包含一下的信息
     *         id    直播id、
     *         startTime    开始直播时间
     *         endTime    结束直播时间
     *         templateType    模板类型
     *         sourceType    直播来源类型，0：正常直播；1：合并回放生成； 2：迁移回放生成； 3：上传回放生成
     *
     * @param string $room_id 房间id多少
     * @param int $pagenum 每页多少条
     * @param int $pageindex 页数
     * @param string $starttime 开始时间
     * @param string $endtime 结束时间
     * @return array
     */
    private
    function cc_live_info(string $room_id, $pagenum = 100, $pageindex = 1, $starttime = "", $endtime = "")
    {
        $data[ 'roomid' ] = $room_id;
        $data[ 'pagenum' ] = $pagenum;
        $data[ 'pageindex' ] = $pageindex;

        // 添加时间段的查询
        !empty($starttime) ? $data[ 'starttime' ] = $starttime : "";
        !empty($endtime) ? $data[ 'endtime' ] = $endtime : "";

        // 调用 api /api/v2/live/info 获取该直播间下所有的直播详情
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/v2/live/info", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }


    /**
     *  通过该接口可以删除直播，同时会删除该直播下的全部回放，删除后不可恢复，接口
     * @param string $liveids
     * @return array
     */
    private
    function cc_live_delete(string $liveids)
    {
        $data[ 'liveids' ] = $liveids;

        // 调用 api /api/live/delete 获取该直播间下所有的直播详情
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/live/delete", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }

    /**
     *  查询回放列表
     *      通过该接口可以分页获取回放列表的信息
     *  返回字段名    说明
     *      result    请求是否成功。OK：成功；FAIL：失败
     *      count    回放总数
     *      pageIndex    页码
     *      records    回放列表信息
     *              id    回放id
     *              liveId    直播id
     *              startTime    开始录制时间
     *              stopTime    结束录制时间
     *              recordStatus    录制状态，0表示录制未结束，1表示录制完成(回放生成,不包括离线CCR)
     *              recordVideoId    录制视频id，如果recordStatus为0则返回-1
     *              replayUrl    回放地址，当recordStatus为0时返回""
     *              offlinePackageUrl    离线包下载地址，注：只有开通离线播放权限才会返回该参数
     *              offlinePackageMd5    离线包md5，注：只有开通离线播放权限才会返回该参数
     *              offlinePackageSize    离线包文件大小，单位Byte，注：只有开通离线播放权限才会返回该参数
     *              templateType    模板类型
     *              sourceType    回放来源，0：录制； 1：合并； 2：迁移； 3：上传
     *              title    回放标题
     *              desc    回放描述
     * @param $room_id
     * @param int $pagenum
     * @param int $pageindex
     * @param string $starttime
     * @param string $endtime
     * @param string $liveid
     * @return array
     */
    private
    function cc_live_recode($room_id, $pagenum = 100, $pageindex = 1, $starttime = "", $endtime = "", $liveid = "")
    {
        $data[ 'roomid' ] = $room_id;
        $data[ 'pagenum' ] = $pagenum;
        $data[ 'pageindex' ] = $pageindex;

        // 添加时间段和直播间下某一个直播的查询
        !empty($starttime) ? $data[ 'starttime' ] = $starttime : "";
        !empty($endtime) ? $data[ 'endtime' ] = $endtime : "";
        !empty($liveid) ? $data[ 'liveid' ] = $liveid : "";


        // 调用 api /api/v2/record/info 获取该直播间下所有的直播详情
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/v2/record/info", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }


    /**
     * 查询回放信息
     *     通过该接口获取单个回放信息
     * 返回结果
     *    record    回放信息
     *      id   回放Id
     *      liveId    直播Id
     *      startTime    开始录制时间, 格式为"yyyy-MM-dd HH:mm:ss"
     *      stopTime    结束录制时间, 格式为"yyyy-MM-dd HH:mm:ss", 如果录制未结束，该值则为""
     *      recordStatus    录制状态，0表示录制未结束，1表示录制完成
     *      replayUrl    回放地址，当recordStatus为0时返回""
     *      recordVideoId    录制视频id，如果recordStatus为0则返回-1
     *      offlinePackageUrl    离线包下载地址，注：只有开通离线播放权限才会返回该参数
     *      offlinePackageMd5    离线包md5，注：只有开通离线播放权限才会返回该参数
     *      offlinePackageSize    离线包文件大小，单位Byte，注：只有开通离线播放权限才会返回该参数
     *      downloadUrl    回放视频下载地址,该下载地址具有时效性，有效时间为2小时
     *      templateType    模板类型
     *      sourceType    回放来源，0：录制； 1：合并； 2：迁移； 3：上传
     *      title    回放标题
     *      desc    回放描述
     * @param string $recordid 某一个直播间下面的某一个直播
     * @return array
     */
    private
    function cc_live_record_search(string $recordid)
    {

        $data[ 'recordid' ] = $recordid;

        // 调用 api /api/v2/record/search 获取该直播间下所有的直播详情
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/v2/record/search", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }

    }

    /**
     *  合并回放
     *      通过该接口可以对同一直播间下相同模板类型的回放进行合并，接口
     *  返回结果
     *     "recordId": "1898E3CD7F97BEED" // 合并生成新的回放ID
     * @param string $room_id
     * @param string $recordids
     * @return array
     */
    private
    function cc_live_merge(string $room_id, string $recordids)
    {
        // 添加直播间 和 想要的回放信息
        $data[ 'roomids' ] = $room_id;
        $data[ 'recordids' ] = $recordids;

        // 调用 api /api/live/merge
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/live/merge", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }

    /**
     *  编辑回放
     *      通过该接口编辑回放信息
     * @param string $recordids
     * @param string $title
     * @param string $desc
     * @return array
     */
    private
    function cc_record_edit(string $recordids, string $title, string $desc)
    {
        // 添加直播间 和 只播放对应的回放
        $data[ 'recordid' ] = $recordids;
        $data[ 'title' ] = $title;
        $data[ 'desc' ] = $desc;

        // 调用 api /api/record/edit 编辑一个直播的回放信息
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/record/edit", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }

    /**
     *  删除一个 直播的回放
     * @param string $recordids
     * @return array
     */
    private
    function cc_record_delete(string $recordids)
    {
        // 添加直播间 和 只播放对应的回放
        $data[ 'recordid' ] = $recordids;

        // 调用 api /api/record/delete 编辑一个直播的回放信息
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/record/delete", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }

    /**
     *   获取当前正在举行的直播
     *   返回结果
     * 返回数据包含以下字段：
     *
     * 字段名    说明
     * result    请求是否成功。OK：成功；FAIL：失败
     * rooms    房间列表 array
     *     房间列表含有以下字段：
     *    字段名    说明
     *    roomId    房间ID
     *    liveId    正在直播的直播ID
     *    startTime     直播开始时间，格式为"yyyy-MM-dd HH:mm:ss"
     * @return array
     */
    private
    function cc_rooms_broadcasting()
    {

        // 这里不需要传递任何参数
        // 唯一需要的参数数 userid
        $data = array();

        // 调用 api /api/rooms/broadcasting 编辑一个直播的回放信息
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/rooms/broadcasting", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }

    }


    /**
     * 获取直播间直播状态
     *     通过该接口获取直播间的直播状态，接口请求地址为:
     *
     * 参数    说明
     * result    "OK"：请求成功，否则请求失败
     * rooms    返回查询直播间信息
     * liveStatus    0：直播未开始，1：正在直播
     * startTime    直播开始时间，若直播未开始，不返回该参数
     * liveId    直播ID，若直播未开始，不返回该参数
     * roomId    直播间ID
     *
     * @param string $roomids
     * @return array
     */
    private
    function cc_rooms_publishing(string $roomids)
    {

        // 传递参数
        $data[ 'roomids' ] = $roomids;

        // 调用 api /api/rooms/publishing 查询一个直播间中正在执行的直播
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/rooms/publishing", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }

    /**
     * 获取直播间连接数
     *     通过该接口可以获取直播间的连接数统计信息，请求次数上限为2次/分钟，
     * 返回结果
     * 字段名    说明
     * result    请求是否成功。OK：成功；FAIL：失败
     * roomId    直播间id
     * connections    连接统计信息 array
     *      统计信息字段
     *         字段名    说明
     *         time    统计时间点
     *         count    连接总数
     *         replayCount    回放连接总数
     * @param string $roomids 房间ide
     * @param $starttime
     * @param $endtime
     * @return array
     */
    private
    function cc_statis_connections(string $roomids, string $starttime, string $endtime)
    {

        // 传递参数
        $data[ 'roomids' ] = $roomids;
        // 添加时间段
        !empty($starttime) ? $data[ 'starttime' ] = $starttime : "";
        !empty($endtime) ? $data[ 'endtime' ] = $endtime : "";

        // 调用 api /api/statis/connections 查询一个直播间中正在执行的直播
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/statis/connections", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }

    }


    /**
     * 获取直播间代码
     *       通过该接口可以获取直播间的代码信息，包括观看地址信息、客户端登陆地址、助教端登录地址、
     * 推流地址(只有第三方推流直播间才可以获取到)、图文直播发布页地址（只有开启图文直播权限才可以获取到）
     * 返回信息
     *  roomId    直播间id
     * clientLoginUrl    客户端登录地址
     * assistantLoginUrl    助教端登录地址
     * viewUrl    观看地址
     * publishUrls    推流地址，第三方推流直播间可以获取到此参数\
     * hostLoginUrl    如果直播间为主持人模式，则返回主持人登录地址
     * promulgatorUrl    如果账号开通了图文直播权限，则返回图文直播发布地址
     *
     * @param string $roomid
     * @return array
     */
    private
    function cc_rooms_code(string $roomid)
    {
        // 传递参数
        $data[ 'roomid' ] = $roomid;

        // 调用 api /api/rooms/code 获取一个直播间的 code
        $ret = $this->CallApiForUrl($this->_url_csslcloud, "/api/room/code", $this->_api_key_for_live, $data);
        // 格式化接口的错误的情况 并将结果返回
        $check_ret = $this->format_api_error_for_live($ret);
        if ($check_ret) {
            return $this->format_api_return(self::RET_IS_OK, $ret);
        } else {
            return $this->format_api_return(self::RET_IS_ERR, $ret);
        }
    }


    /**
     *  用来验证 cc直播的用户接口
     *  如果用户接口成功那么返回下面的值
     * {
     *  "result": "ok",
     *  "message": "登录成功",
     * "user":{
     *      "id": "E6A232B2DEDF69469C33DC5901307461",
     *      "name": "学员A",
     *      "groupid": "a1",
     *      "avatar": "http://domain.com/icon.png",
     *      "customua": "customua1",
     *      "viewercustommark": "mark1",
     *      "viewercustominfo": "{\"exportInfos\": [{\"key\": \"区域\", \"value\": \"北京\"}, {\"key\": \"城市\", \"value\": \"北京\"}, {\"key\": \"姓名\", \"value\": \"哈哈\"}, {\"key\": \"邮箱\", \"value\": \"someone@bokecc.com\"}]}",
     *      "marquee": "{\"loop\":-1,\"type\":\"text\",\"text\":{\"content\":\"跑马灯内容\",\"font_size\":20,\"color\":\"0xf0f00f\"},\"action\":[{\"duration\":4000,\"start\":{\"xpos\":0,\"ypos\":0,\"alpha\":0.5},\"end\":{\"xpos\":0.6,\"ypos\":0,\"alpha\":1}},{\"duration\":4000,\"start\":{\"xpos\":0,\"ypos\":0.7,\"alpha\":0.3},\"end\":{\"xpos\":0.7,\"ypos\":0.7,\"alpha\":0.9}}]}"
     *      }
     * }
     *  注意如果result 返回的不是ok 接口直接认为验证错误
     *
     */
    public  function  cc_user_login_function(bool $login_is_ok, array $user_info){
          if($login_is_ok){
              $nick_name = empty($user_info['nickname'])?substr_replace($user_info['phone'],'****',3,4):$user_info['nickname'];
              // 组合 验证接口中的用户数据
              $user_info = array(
                  "id"               => $user_info['id'],
                  "name"             =>$nick_name,
                  "groupid"          => "",
                  "avatar"           => "",
                  "customua"         => "",
                  "viewercustommark" => "mark1",
                  "viewercustominfo" => "",
                  "marquee"          => "",

              );
              // 如果登录成功
              return array(
                  "result" => "ok",
                  "message" => "登录成功",
                  "user" => $user_info
              );
          }else{
              return array(
                  "result" => "FAil",
                  "message" => "登录失败"
              );
          }
    }


// endregion

// region 一些工具函数
    /**
     *  计算加密后的字符串
     *  详细流程参见 CC 直播sdk
     * @param array $Vars
     * @param string $apikey
     * @return string
     */
    private function THQS(array $Vars, string $apikey)
    {
        print_r("开始加密数据" . PHP_EOL);
        print_r($Vars);

        // 第⼀步,将上述QueryString 按照字⺟顺序进行升序排序,对value进行URLencode转义处理
        foreach ($Vars as $k => $v) {
            //$Parameters[ strtolower($k) ] = $v;
            $Parameters[ $k ] = $v;
        }


        //按字典序排序参数
        ksort($Parameters);

        // 得到 得到散列前的 qf 拼接上 时间戳 和 salt 也就是appkey
        $_time_span = time();
        $_qs = $this->arrayToString($Parameters, true);
        //print_r("parametersStr:" . $_qs . PHP_EOL);
        $_qf = $_qs . "&time=" . ($_time_span) . "&salt=" . ($apikey);
        //print_r("qf:" . $_qf . PHP_EOL);
        //计算hash
        $_hash = strtoupper(md5($_qf));

        // 最终的得到 加密后的字符串
        $_hqs = $_qs . "&time=" . ($_time_span) . "&hash=" . $_hash;
        //print_r("hqs:" . $_hqs . PHP_EOL);
        return $_hqs;
    }


    /**
     *  call api
     * @param $_url
     * @param $interface_url
     * @param $apikey
     * @param $data
     * @param int $second
     * @return false|mixed
     */
    private function CallApiForUrl($_url, $interface_url, $apikey, $data, $second = 30)
    {
        // 注意这里 手动添加一个参数 userid 这个参数是无论live api 还是点播业务 都是必须的
        $data[ 'userid' ] = $this->_USER_ID;

        // 由于CC 直播要求使用 get 方式提交属 并且对参数实行 THOQ 方式的加密
        // 那么这里直接把传递的 $data 直接加密后拼接到
        $url = $_url . $interface_url . "?" . ($this->THQS($data, $apikey));

        //初始化curl
        $ch_ins = curl_init();
        //超时时间
        curl_setopt($ch_ins, CURLOPT_TIMEOUT, $second);
        //这里设置代理，如果有的话
        curl_setopt($ch_ins, CURLOPT_URL, $url);
        //设置header
        curl_setopt($ch_ins, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch_ins, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        // curl_setopt($ch_ins, CURLOPT_POST, TRUE);
        // curl_setopt($ch_ins, CURLOPT_POSTFIELDS, $data);

        curl_setopt($ch_ins, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch_ins, CURLOPT_SSL_VERIFYHOST, FALSE);
        //运行curl
        $ret_data = curl_exec($ch_ins);
        //返回结果
        if ($data) {
            curl_close($ch_ins);
            //print_r($ret_data);
            return (json_decode($ret_data, true));
        } else {
            $error = curl_errno($ch_ins);
            //print_r($error);
            curl_close($ch_ins);
            return false;
        }
    }

    /**
     *  将一个数组转换成 字符串的形式
     *  可以使用impolor
     * @param $array 参数列表、
     * @param $urlencode bool 是否启用 urlencode
     * @return false|string
     */
    private
    function arrayToString($array, $urlencode)
    {
        $buff_str = "";
        ksort($array);
        foreach ($array as $k => $v) {
            if ($urlencode) {
                $v = rawurlencode($v);
            }
            $buff_str .= ($k) . "=" . $v . "&";
        }
        $reqPar = "";
        if (strlen($buff_str) > 0) {
            $reqPar = substr($buff_str, 0, strlen($buff_str) - 1);
        }
        return $reqPar;
    }

    /**
     * 数组 转 对象
     *
     * @param array $arr 数组
     * @return object
     */
    private function array_to_object(array $arr)
    {
        if (gettype($arr) != 'array') {
            return null;
        }
        foreach ($arr as $k => $v) {
            if (gettype($v) == 'array' || getType($v) == 'object') {
                $arr[ $k ] = (object)array_to_object($v);
            }
        }

        return (object)$arr;
    }

    /**
     * 对象 转 数组
     *
     * @param object $obj 对象
     * @return array
     */
    private function object_to_array(object $obj)
    {
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'resource') {
                return array();
            }
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                if (!empty($v))
                    $obj[ $k ] = (array)object_to_array($v);
            }
        }

        return $obj;
    }

    /**
     *  产生随机的密码 默认 8位
     * @param int $length
     * @param string $type
     * @return false|string
     */
    function random_password($length = 8, $type = 'alpha-number')
    {
        $code_arr = array(
            'alpha'  => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'number' => '0123456789',
            'sign'   => '#$%@*-_',
        );

        $type_arr = explode('-', $type);

        foreach ($type_arr as $t) {
            if (!array_key_exists($t, $code_arr)) {
                trigger_error("Can not generate type ($t) code");
            }
        }

        $chars = '';

        foreach ($type_arr as $t) {
            $chars .= $code_arr[ $t ];
        }
        $chars = str_shuffle($chars);
        $number = $length > strlen($chars) - 1 ? strlen($chars) - 1 : $length;
        return substr($chars, 0, $number);
    }

    // endregion

    public function test()
    {

        // 1 创建直播间
        echo "开始 CC 直播\r\n";
//$room_info = new CCCloudLiveRoomInfo();

        $room_id = "70C947ADA24DE3499C33DC5901307461";
        $cc_api = new CCCloud();
        if (empty($room_id)) {
            echo "创建直播间";
            $cc_api_ret = $cc_api->cc_room_create("xiaomage", "test", 5,
                "2", "123456", "123456");

            print_r($cc_api_ret);
        }
        echo "获取直播间信息";
        $cc_api_ret = $cc_api->cc_room_search($room_id);

        print_r($cc_api_ret);


        echo "开启直播间";

        $cc_api_ret = $cc_api->cc_room_open($room_id);
        print_r($cc_api_ret);


        echo "获取该账户下所有的直播间";

        $cc_api_ret = $cc_api->cc_room_info();
        print_r($cc_api_ret);

        echo "获取直播间的code";

        echo "获取直播间的code";
        $cc_api_ret = $cc_api->cc_rooms_code($room_id);
        print_r($cc_api_ret);

        echo "获取直播的回放信息";
        $cc_api_ret = $cc_api->cc_live_info($room_id);
        print_r($cc_api_ret);

        $live_id = "18E04C85B7590020";
        $cc_api_ret = $cc_api->cc_live_recode($room_id);
        print_r($cc_api_ret);

        $records_id = "87B267A049669BFC";
        $cc_api_ret = $cc_api->cc_record_edit($records_id, "test", "test llive");
        print_r($cc_api_ret);

        $cc_api_ret = $cc_api->cc_live_record_search($records_id);
        print_r($cc_api_ret);

    }

}

//
//
//$ret_str="?assistantpass=123456&authtype=2&desc=test&name=xiaomage&publisherpass=123456&templatetype=5&userid=788A85F7657343C2&time=1603159002&hash=06C94BC27ABF51624AEE9B214D3D1BEE";
//$str="";
//
//// 得到 得到散列前的 qf 拼接上 时间戳 和 salt 也就是appkey
//$_time_span ="1603159002";
//$_qs = "assistantpass=123456&authtype=2&desc=test&name=xiaomage&publisherpass=123456&templatetype=5&userid=788A85F7657343C2";
//
//print_r("parametersStr:".$_qs.PHP_EOL);
//$_qf = $_qs . "&time=" . ($_time_span) . "&salt=" . ("TUUddgHOPhX2n1xGVldnLfbkmFrUc4Sa");
//print_r("qf:".$_qf.PHP_EOL);
////计算hash
//$_hash =  strtoupper(md5($_qf));
//
//// 最终的得到 加密后的字符串
//$_hqs = $_qs . "&time=" . ($_time_span) . "hash=" . $_hash;
//print_r("hqs:".$_hqs.PHP_EOL);
//print_r($ret_str.PHP_EOL);
//
//
//
//exit(0);

//$cccloud = new CCCloud();
//$cccloud->test();




