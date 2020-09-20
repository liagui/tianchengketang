<?php

namespace App\Providers\Rsa;
use Illuminate\Http\Request;

class RsaFactory {
    /**
     * 加密方法，对数据进行加密，返回加密后的数据
     *
     * @param string $data 要加密的数据
     *
     * @return string
     *
     */
    public function aesencrypt($data , $key , $iv='sciCuBC7orQtDhTO') {
        return base64_encode(openssl_encrypt($data, "AES-128-CBC", $key , OPENSSL_RAW_DATA, $iv));
    }

    /**
     * 解密方法，对数据进行解密，返回解密后的数据
     *
     * @param string $data 要解密的数据
     *
     * @return string
     *
     */
    public function aesdecrypt($data , $key , $iv='sciCuBC7orQtDhTO') {
        return openssl_decrypt(base64_decode($data), "AES-128-CBC", $key , OPENSSL_RAW_DATA, $iv);
    }

    /**
     * 获取私钥
     * @return bool|resource
     */
    private static function getPrivateKey() {
        $privateKey = file_get_contents(app()->basePath().'/rsa_private_key.pem');
        return openssl_pkey_get_private($privateKey);
    }

    /**
     * 获取公钥
     * @return bool|resource
     */
    private static function getPublicKey() {
        $publicKey = file_get_contents(app()->basePath().'/rsa_public_key.pem');
        return openssl_pkey_get_public($publicKey);
    }

    /**
     * 私钥加密
     * @param string $data
     * @return null|string
     */
    public static function privateEncrypt($data = '')
    {
        if (!is_string($data)) {
            return null;
        }
        $EncryptStr = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, self::getPrivateKey());
            $EncryptStr .= $encryptData;
        }

        return base64_encode($EncryptStr);
    }

    /**
     * 公钥加密
     * @param string $data
     * @return null|string
     */
    public static function publicEncrypt($data = '')
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_public_encrypt($data,$encrypted,self::getPublicKey()) ? base64_encode($encrypted) : null;
    }

    /**
     * 私钥解密
     * @param string $encrypted
     * @return null
     */
    public static function privateDecrypt($encrypted = '')
    {
        $DecryptStr = '';

        foreach (str_split(base64_decode($encrypted), 128) as $chunk) {

            openssl_private_decrypt($chunk, $decryptData, self::getPrivateKey());

            $DecryptStr .= $decryptData;
        }

        return $DecryptStr;
    }


    /**
     * 公钥解密
     * @param string $encrypted
     * @return null
     */
    public static function publicDecrypt($encrypted = '')
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return (openssl_public_decrypt(base64_decode($encrypted), $decrypted, self::getPublicKey())) ? $decrypted : null;
    }

    /**
     * 生成数字签名
     * 使用方法示例
     * openssl_sign('您要签名的数据' , '签名后返回来的数据' , '签名的钥匙/可以是公钥签名也可以是私钥签名,一般是私钥加密,公钥解密')
     * @param  $data  待签数据
     * @return String 返回签名
     */
    public static function sign($data=''){
        //获取私钥
        $pkeyid = self::getPrivateKey();
        if (empty($pkeyid)) {
            return false;
        }

        //生成签名方法
        $verify = openssl_sign($data, $signature, $pkeyid , OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /*
     * 数字签名验证
     */
    public function verifySign($data , $sign) {
        $pub_id = openssl_get_publickey(self::getPublicKey());
        $res    = openssl_verify($data, base64_decode($sign), $pub_id , OPENSSL_ALGO_SHA256);
        return $res;
    }


    /*
     * @param description 客户端RSA加密方法示例
     * @param $key  aes随机加密的key
     *        $data aes加密后的数据
     * @param author duzhijian
     * @param ctime  2020-04-15
     * return array
     */
    public function rsaencrypt($key = '' , $data = ''){
        //判断key是否为空
        if(!$key || empty($key)){
            return response()->json(['code'=>201,'msg'=>'key不合法或为空']);
        }

        //判断data是否为空
        if(!$data || empty($data)){
            return response()->json(['code'=>201,'msg'=>'data不合法或为空']);
        }

        //判断data是否为数组格式
        if(is_array($data)){
            $result = json_encode($data);
        } else {
            $result = $data;
        }

        //将数据进行AES加密处理
        $body = $this->aesencrypt($result , $key);

        //生成签名
        $sign = self::sign($body);

        //将key进行RSA加密处理
        $token= self::publicEncrypt($key);

        //返回数据数组
        return json_encode(array('token' => $token , 'body' => $body , 'sign' => $sign));
    }

    /*
     * @param description 服务端RSA解密方法示例
     * @param $token  rsa加密的key
     *        $body   aes加密后的数据
     *        $sign   签名
     * @param author duzhijian
     * @param ctime  2020-04-15
     * return array
     */
    public function rsadecrypt($token = '' , $body = '' , $sign = ''){
        //判断key是否为空
        if(!$token || empty($token)){
            echo json_encode(['code'=>201,'msg'=>'token不合法或为空']);
            exit;
        }

        //判断data是否为空
        if(!$body || empty($body)){
            echo json_encode(['code'=>201,'msg'=>'body不合法或为空']);
            exit;
        }

        //数据验签处理
        if($sign && !empty($sign)){
            $sign_st = self::verifySign($body , $sign);
            //判断是否验签成功
            if($sign_st <= 0){
                echo json_encode(['code'=>202,'msg'=>'签名验证失败']);
                exit;
            }
        }

        //将key进行RSA解密处理(最后得到aes的明文key)
        $key = self::privateDecrypt($token);


        //再将aes进行数据解密处理
        $data= $this->aesdecrypt($body , $key);
        if(!$data || empty($data)){
            echo json_encode(['code'=>202,'msg'=>'解密失败']);
            exit;
        }

        //返回数据数组
//        echo response()->json(['code'=>$data]);
//        exit;
        return json_decode($data , true);
    }

    /*
     * @param description 服务端RSA+AES示例
     * @param $key   加密的key
     *        $arr   加密的数据
     * @param author duzhijian
     * @param ctime  2020-04-15
     * return array
     */
    public function RsaCryptDemo($key , $arr){
        //对数据进行加密处理(生成加密后的数据字符串)
        $encrypt_data =  $this->rsaencrypt($key , $arr);

        //判断加密的数据信息是否为空
        if(empty($encrypt_data)){
            return response()->json(['code'=>201,'msg'=>'加密后的数据为空']);
        }

        //进行json解码处理转化成数组
        $array_data   = json_decode($encrypt_data , true);

        //判断token是否合法或为空
        if(!isset($array_data['token']) || empty($array_data['token'])){
            return response()->json(['code'=>201,'msg'=>'token值不合法']);
        }

        //判断body是否合法或为空
        if(!isset($array_data['body']) || empty($array_data['body'])){
            return response()->json(['code'=>201,'msg'=>'body值不合法']);
        }

        //对数据进行解密处理
        $data_list = $this->rsadecrypt($array_data['token'] , $array_data['body'] , $array_data['sign']);

        echo "<pre>";
        print_r($data_list);
    }

    /*
     * @param description 服务端数据解密
     * @param $key   加密的key
     *        $arr   加密的数据
     * @param author duzhijian
     * @param ctime  2020-04-15
     * return array
     */
    public function Servicersadecrypt($data){

        //判断token是否合法或为空
        if(!isset($data['token']) || empty($data['token'])){
            echo json_encode(['code'=>201,'msg'=>'token值不存在或为空']);
            exit;
        }

        //判断body是否合法或为空
        if(!isset($data['body']) || empty($data['body'])){
            echo json_encode(['code'=>201,'msg'=>'body值不存在或为空']);
            exit;
        }

        //判断签名是否合法或为空
        /*if(!isset($data['sign']) || empty($data['sign'])){
            echo response()->json(['code'=>201,'msg'=>'sign值不存在或为空']);
            exit;
        }*/
        //对数据进行解密处理
        return $this->rsadecrypt($data['token'] , $data['body'] , '');
    }



    public function Test(){
        $key = time().rand(1,10000);
        //$arr = ['status' => '1', 'info' => 'success', 'data' => [['id' => 1, 'name' => 'big small', '2' => 'small room']]];
        //$arr = json_encode($arr);
        //$aaa = self::sign($arr);
        
        $arr = [
            //'data' => [
                /*'head_icon' => 'https://dss2.bdstatic.com/6Ot1bjeh1BF3odCf/it/u=292702532,4292822400&fm=74&app=80&f=JPEG&size=f121,90?sec=1880279984&t=d0ca9d3b11682cdb49eb0969964ac3c4',
                'phone'     => '15689213549' ,
                'real_name' => '诸葛亮' ,
                'sex'       =>  1 ,
                'qq'        => '965235825' ,
                'wechat'    => '',
                'parent_id' => 9 ,
                'child_id'  => 36 ,
                'describe'  => '王者荣耀' ,
                'content'   => '绝招很厉害',
                'type'      => 2*/
               // 'is_recommend' => 1
            //] ,
            /*'condition'    => [
                'paginate' => 15 ,
                'real_name'=> '诸',
                'type'     => 2
            ]*/
            /*'head_icon' => 'https://dss2.bdstatic.com/6Ot1bjeh1BF3odCf/it/u=292702532,4292822400&fm=74&app=80&f=JPEG&size=f121,90?sec=1880279984&t=d0ca9d3b11682cdb49eb0969964ac3c4',
            'phone'     => '18910486610' ,
            'real_name' => '马新东' ,
            'sex'       =>  1 ,
            'qq'        => '984578526' ,
            'wechat'    => 'json_13345456',
            'parent_id' => 8 ,
            'child_id'  => 32 ,
            'describe'  => '测试的内容文字' ,
            'content'   => '内容是测试内容',
            'type'      => 2,*/



            //'pagesize'=> 10,
            //'page'    => 2,
            // 'topic_name'    => '单元测试题库45555' ,
            // 'subject_id'    => '1,2,3,8' ,
            // 'parent_id'     => 6 ,
            // 'child_id'      => 7 ,
            // 'describe'      => '单元一侧',
            // 'bank_id'       => 1,
            // //'teacher_id'=> 5
            // 'search'=>'',
            // // 'search'=>'',
            // 'id' =>1,
            // 'school_id'=>1,
            // 'username'=>'kobe',
            // 'realname'=>'kobe',
            // 'mobile'=>'13520351725',
            // 'sex'=>'1',
            // 'password'=>'kobe',
            // 'pwd'=>'kobe',
            // 'role_id'=>'1',
            // 'teacher_id'=>'1,2,3,4,5,6',
               // 'is_recommend' => 1
            
            'type'          =>  1 ,
            'exam_id'       =>  36 ,
            'subject_id'    =>  16 ,
            'bank_id'       =>  0 ,
            'exam_content'  => "What might Johnny Lee Baker be?" ,
            'option_list'   => [
                [
                    'option_no'    =>  'A' ,
                    'option_name'  =>  'A fireman.' ,
                    'correct_flag' =>  0
                ],
                [
                    'option_no'    =>  'B' ,
                    'option_name'  =>  'A teacher.' ,
                    'correct_flag' =>  0
                ],
                [
                    'option_no'    =>  'C' ,
                    'option_name'  =>  'A shopkeeper.' ,
                    'correct_flag' =>  0
                ],
                [
                    'option_no'    =>  'D' ,
                    'option_name'  =>  'A policeman.' ,
                    'correct_flag' =>  1                ]
            ],
            'answer'        => 'D' ,
            'text_analysis' => '测试文字' ,
            'audio_analysis'=> '1.mv' ,
            'video_analysis'=> '2.mp4',
            'chapter_id'    => 1 ,
            'joint_id'      => 2 ,
            'point_id'      => 3 ,
            'item_diffculty'=> 1
        ];
        $ccc = $this->rsaencrypt($key , $arr);
        $ccc = json_decode($ccc , true);
        echo "<pre>";
        print_r($ccc);
        exit;
        //$bbb = "SBxzwN05LdOY0vswkWieoNj6KnQCsVbHT5Fi4TLAQe5yfZrod3UbZz90od0DinpiEi1+vGMTlZ+Ck9LcaWjGS4yBa1XYO4BI9JtTvJN+JqxKlvZIyHX/ip9WfzPqPtOwUuRt/YSU7sLslpvAbG0hvVH2jVS1OvZdnDA6nbusocs=";
        $token= "EGzWzR27RuS6bA8Haj3RZAdyEseTGgYd1pYubaMN2I2Z9vykrrohxf1Xf2A2BNQA4VsFPjyv4xnkxqKdQZ6fevgQ3pzKy2+RdsCrd8ap68RnXto5o7G8QCX8HNpTQiPmONJl1tjyWB/IVauq7MN/sLg1kViEOxMRSQuOivQAhLg=";
        $body = "wz7Jk0s++OXxJBWt2l5V6hjr9oOHA0Vf86wzvJXhi6Zs3y/nrdGONqUyAH8wG15L4FmIvs4sLUBcQDNN27Gh8Gtrp2hcqij3cbKF0t/FC8eWCJa2GATJ+w6pZbi9+D89OFUnhSCZFFNo9P8dDFjpFg==";
        $sign = "D9OmydE2zhpcS9Zd12JseO/ayiMRT4PnId4wCcRbvUaU7ehnuzK4hqno+VHVhiAzgVp2lTiRbHOWFTgQUdhCfFOSIo7op999wlT47mC8Xqjv+atKEnPZzC0MfvxZbmw62bpiRwGWmUUvMgVnQDCf9OaOEjN4ldcPW8izDsuLGrc=";
        //$bbb = self::verifySign($body , $sign);

        $dddd = $this->rsadecrypt($token , $body , $sign);

        echo "<pre>";
        print_r($dddd);
        exit;
        $this->RsaCryptDemo($key , $arr);
        exit;
    }
}
