<?php
/**
 * Created by PhpStorm.
 * User: AllenJiang
 * Date: 2017/9/6
 * Time: 17:21
 */

namespace YunLianHui;

class Sign
{
    //私钥值
    private $rsaPrivateKey;
    // 表单提交字符集编码
    private $postCharset = 'UTF-8';

    private $fileCharset = 'UTF-8';

    /**
     * Sign constructor.
     * @param $rsaPrivateKey
     */
    public function __construct($rsaPrivateKey)
    {
        $this->rsaPrivateKey = $rsaPrivateKey;
    }

    /**
     * 签名
     *
     * @param $params
     * @param $signType
     * @return mixed
     *
     */
    public function generateSign($params, $signType = "RSA2") {
        return $this->sign($this->getSignContent($params), $signType);
    }
    /**
     * 验证签名
     * @param $params
     * @param $rsaPublicKeyFilePath
     * @param $signType
     * @return mixed
     *
     *  在使用本方法前，必须初始化AopClient且传入公钥参数。
     *  公钥是否是读取字符串还是读取文件，是根据初始化传入的值判断的。
     **/
    public function rsaCheck($params, $rsaPublicKeyFilePath,$signType='RSA2') {
        if(isset($params['XDEBUG_SESSION_START'])){
            $params['XDEBUG_SESSION_START'] = null;
        }
        $sign = $params['sign'] ?? null;
        $params['sign_type'] = null;
        $params['sign'] = null;
        return $this->verify($this->getSignContent($params), $sign, $rsaPublicKeyFilePath,$signType);
    }

    protected function getSignContent($params) {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {

                // 转换成目标字符集
                $v = $this->characet($v, $this->postCharset);

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    protected function sign($data, $signType = "RSA2")
    {
        $priKey=$this->rsaPrivateKey;
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        ($res) or die('私钥格式错误，请检查RSA私钥配置');

        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

//        if(!$this->checkEmpty($this->rsaPrivateKeyFilePath)){
//            openssl_free_key($res);
//        }
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    protected function characet($data, $targetCharset) {

        if (!empty($data)) {
            $fileType = $this->fileCharset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }


        return $data;
    }

    /**
     * 校验$value是否非空
     *
     * @param  $value
     * @return boolean
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }

    protected function verify($data, $sign, $pubKey, $signType = 'RSA2')
    {
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($pubKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        ($res) or die('RSA公钥错误。请检查公钥文件格式是否正确');

        //调用openssl内置方法验签，返回bool值

        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }

//        if(!$this->checkEmpty($this->alipayPublicKey)) {
//            //释放资源
//            openssl_free_key($res);
//        }

        return $result;
    }

}