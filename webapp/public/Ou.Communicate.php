<?php
/**
 * 通讯相关公共功能
 * Created by PhpStorm.
 * User: outao
 * Date: 2022/8/22
 * Time: 16:23
 */

class Ou_communicate{
    /**
     * 发送post请求
     * @param  string $url  请求地址
     * @param array $post_data  健值对数据
     * @return string
     *
     * //使用方法
        $post_data = array(
            'username' => 'stclair2201',
            'password' => 'handan'
         );
        send_post('http://www.jb51.net', $post_data);
     */
    public static function sendPost($url,$post_data){
        $postData = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postData,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return $result;
    }

}