<?php

namespace App\Tools;

class ProfitSharingSign
{

    /**
     * 根据Url传递的参数，生成签名字符串
     * @param array $param
     * @param string $signType
     * @param $md5Key
     * @return string
     * @throws \Exception
     */
    public function getSign(array $param, $signType = 'MD5', $md5Key)
    {
        $values = $this->paraFilter($param);
        $values = $this->arraySort($values);
        $signStr = $this->createLinkstring($values);

        $signStr .= '&key=' . $md5Key;
        switch ($signType)
        {
            case 'MD5':
                $sign = md5($signStr);
                break;
            case 'HMAC-SHA256':
                $sign = hash_hmac('sha256', $signStr, $md5Key);
                break;
            default:
                $sign = '';
        }
        return strtoupper($sign);
    }


    /**
     * 移除空值的key
     * @param $para
     * @return array
     */
    public function paraFilter($para)
    {
        $paraFilter = array();
        while (list($key, $val) = each($para))
        {
            if ($val == "") {
                continue;

            } else {
                if (! is_array($para[$key])) {
                    $para[$key] = is_bool($para[$key]) ? $para[$key] : trim($para[$key]);
                }

                $paraFilter[$key] = $para[$key];
            }
        }
        return $paraFilter;
    }


    /**
     * @function 对输入的数组进行字典排序
     * @param array $param 需要排序的数组
     * @return array
     * @author helei
     */
    public function arraySort(array $param)
    {
        ksort($param);
        reset($param);
        return $param;
    }


    /**
     * @function 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param array $para 需要拼接的数组
     * @return string
     * @throws \Exception
     */
    public function createLinkString($para)
    {
        if (! is_array($para)) {
            throw new \Exception('必须传入数组参数');
        }

        reset($para);
        $arg  = "";
        while (list($key, $val) = each($para)) {
            if (is_array($val)) {
                continue;
            }

            $arg.=$key."=".urldecode($val)."&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }

        return $arg;
    }
}
