<?php
namespace YunLianHui;

class OAuth2
{
    /**
     * http请求类
     * @var Request
     */
    public $http;
    /**
     * 应用的唯一标识。
     * @var string
     */
    public $client_id;

    /**
     * client_id对应的密钥
     * @var string
     */
    public $client_secret;

    /**
     * client私钥
     * @var string
     */
    public $client_private_key;

    /**
     * 回调地址
     * @var string
     */
    public $redirect_uri;

    /**
     * state值
     * @var string
     */
    public $state;

    /**
     * 授权权限列表
     * @var array
     */
    public $scope;

    /**
     * 接口调用结果
     * @var array
     */
    public $result;

    /**
     * access_token，调用相应方法后可以获取到
     * @var string
     */
    public $access_token;

    /**
     * user_id，调用相应方法后可以获取到
     * @var string
     */
    public $user_id;

    /**
     * api前缀
     * 测试站： https://opentest.yunlianhui.com
     * 正式站： https://open.yunlianhui.com
     */
    private $api_prefix ;

    /**
     * 构造方法
     * @param string $api_prefix
     * @param string $client_id
     * @param string $client_secret
     * @param string $client_private_key
     * @param string $redirect_uri
     */
    public function __construct($api_prefix,$client_id, $client_secret, $client_private_key, $redirect_uri)
    {
        $this->api_prefix = $api_prefix;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->client_private_key = $client_private_key;
        $this->redirect_uri = $redirect_uri;
        $this->http = new Request();
    }


	/**
	 * 拼接url方法
	 * @param string $name 跟在域名后的文本
	 * @param array $params GET参数
	 * @return string
	 */
	public function getUrl($name, $params = array())
	{
		return $this->api_prefix . '/' . $name . (empty($params) ? '' : ('?' . \http_build_query($params)));
	}
    /**
     * 获取state值
     * @param string $state
     * @return string
     */
    protected function getState($state = null)
    {
        if(null === $state)
        {
            if(null === $this->state)
            {
                $this->state = md5(\uniqid('', true));
            }
        }
        else
        {
            $this->state = $state;
        }
        return $this->state;
    }


    /**
     * 签名
     * @param $params
     * @param $rsaPrivateKey
     * @return string
     */
    protected function generateSign($params, $rsaPrivateKey)
    {
        $sign = new Sign($rsaPrivateKey);
        return $sign->generateSign($params);
    }
	/**
	 *  1） 拼接授权url
	 * @param string $redirect_uri  指的是应用发起请求时，所传的回调地址参数
	 * @param string $scope 访问的权限，可以多个叠加。如： scope=A+B
	 * @return string
	 */
	public function getAuthorizationUrl($redirect_uri = null, $scope = 'basic_info')
	{
		return $this->getUrl('oauth/authorize', array(
			'client_id'			=>	$this->client_id,
			'redirect_uri'		=>	null === $redirect_uri ? $this->redirect_uri : $redirect_uri,
			'scope'				=>	$scope,
			'response_type' => 'code',
			'state'				=>	$this->getState(),
		));
	}

	 /**
	 *  2）引导用户登录授权
	 *  引导用户通过浏览器访问以上授权url，将弹出如下登录页面。用户输入账号、密码点“登录”按钮，即可进入授权页面。
	 *  （页面操作，无代码）
	 */

	 /**
	 *  3）获取code
	 *  若用户点“授权”按钮后，云联惠开放平台会将授权码code 返回到了回调地址上，应用可以获取并使用该code去换取access_token。
	 *  测试情况下手动将浏览器重定向后的code获取
	 *  （手动操作，无代码）
	 */


    /**
     * 4） 换取access_token
     * @param $code  $redirectUri地址中传过来的code，为null则通过get参数获取
     * @throws ApiException
     * @return mixed
     */
	public function getAccessTokenFromCode($code)
	{
        $params =
            [
                'client_id'		=>	$this->client_id,
                'client_secret'	=>	$this->client_secret,
                'grant_type'	=>	'authorization_code',
                'code'			=>	$code,
                'redirect_uri'	=>	$this->redirect_uri,
                'timestamp' => (string)time(),
            ];

        $params['sign'] = $this->generateSign($params,$this->client_private_key);

        $response = $this->http->post($this->getUrl('api/v2/oauth/token'),http_build_query($params, null, '&'));
        $this->result = json_decode($response->getBody(),true);
        if($response->getHttpResponseCode() !== 200 || (isset($this->result['error_code']) && $this->result['error_code'] !== '0'))
        {
            throw new ApiException($response->getBody(),$response->getHttpResponseCode());
        }
        else
        {
            return $this->result;
        }
	}

    /**
     * 发送一个资源请求
     * @param array $params
     * @param $url
     * @return array|mixed
     * @throws ApiException
     */
    public function sendAnResourceRequest(array $params, $url)
    {
        $params['sign'] = $this->generateSign($params,$this->client_private_key);

        $response = $this->http->post($this->getUrl($url),http_build_query($params, null, '&'));
        $this->result = json_decode($response->getBody(),true);
        if($response->getHttpResponseCode() !== 200 || (isset($this->result['error_code']) && $this->result['error_code'] !== '0'))
        {
            throw new ApiException($response->getBody(),$response->getHttpResponseCode());
        }
        else
        {
            return $this->result;
        }

    }

    /**
     * 客户端获取访问令牌
     * @param string $scope
     * @return mixed
     * @throws ApiException
     */
    public function getClientCredentials($scope='register_fast')
    {
        $this->result = json_decode($this->http->post($this->getUrl('token/client_credentials'),
            [
                'client_id'		=>	$this->client_id,
                'client_secret'	=>	$this->client_secret,
                'grant_type'	=>	'client_credentials',
                'scope'			=>	$scope
            ])->getBody(), true);

        //return $this->result;
        if(isset($this->result['error']))
        {
            throw new ApiException($this->result['error'], $this->result['error_description']);
        }
        else
        {
            return $this->result['access_token'];
        }
    }

	/**
	 * 刷新AccessToken
	 * @return bool
	 */
	public function refreshToken()
	{
		// 暂时不支持刷新
		return false;
	}


}
