<?php
/* PHP SDK
 * @version 2.0.0
 * @author connect@qq.com
 * @copyright © 2013, Tencent Corporation. All rights reserved.
 */

namespace Jonexyz\QQConnect;

class Oauth {

    const VERSION = "2.0";
    const GET_AUTH_CODE_URL = "https://graph.qq.com/oauth2.0/authorize";
    const GET_ACCESS_TOKEN_URL = "https://graph.qq.com/oauth2.0/token";
    const GET_OPENID_URL = "https://graph.qq.com/oauth2.0/me";

    public $urlUtils;
    protected $recorder;

    protected $appid;
    protected $appkey;
    protected $callback;
    protected $scope;

    /**
     * Oauth constructor.
     * @param $appid string 应用appid
     * @param $appkey string 应用appkey
     * @param $callback string 应用授权回调地址
     * @param $scope string  申请获取的应用权限，多个以英文逗号相隔
     */
    function __construct($appid,$appkey,$callback,$scope){
        $this->appid = $appid;
        $this->appkey = $appkey;
        $this->callback = $callback;
        $this->scope = $scope;

        if(empty($this->appid) || empty($this->appkey) || empty($this->callback) || empty($this->scope) ){
            throw new \Exception('配置信息填写不完整',"20001");
        }

        $this->urlUtils = new URL();
    }

    /**
     *
     * @param $appid string 应用appid
     * @param $callback string 应用回调地址
     * @param $scope string 应用申请的授权内容
     * @return string
     */
    public function qq_login(){
        //-------生成唯一随机串防CSRF攻击
        $state = md5(uniqid(rand(), TRUE));

        //-------构造请求参数列表
        $keysArr = array(
            "response_type" => "code",
            "client_id" => $this->appid,
            "redirect_uri" => $this->callback,
            "state" => $state,
            "scope" => $this->scope
        );

        $login_url =  $this->urlUtils->combineURL(self::GET_AUTH_CODE_URL, $keysArr);

        return $login_url;
    }

    public function qq_callback(){

        //--------验证state防止CSRF攻击
        /*$state = $this->recorder->read("state");
        if(!$state || $_GET['state'] != $state){
            throw new \Exception('The state does not match. You may be a victim of CSRF.',30001);
        }*/

        //-------请求参数列表
        $keysArr = array(
            "grant_type" => "authorization_code",
            "client_id" => $this->appid,
            "redirect_uri" => urlencode($this->callback),
            "client_secret" => $this->appkey,
            "code" => $_GET['code']
        );

        //------构造请求access_token的url
        $token_url = $this->urlUtils->combineURL(self::GET_ACCESS_TOKEN_URL, $keysArr);
        $response = $this->urlUtils->get_contents($token_url);

        if(strpos($response, "callback") !== false){

            $lpos = strpos($response, "(");
            $rpos = strrpos($response, ")");
            $response  = substr($response, $lpos + 1, $rpos - $lpos -1);
            $msg = json_decode($response);

            if(isset($msg->error)){
                throw new \Exception($msg->error, $msg->error_description);
            }
        }

        $params = array();
        parse_str($response, $params);

        if(empty($params["access_token"])){
            throw new \Exception('access_token 获取失败');
        }

        return $params["access_token"];
    }

    public function get_openid($access_token){
        //-------请求参数列表
        $keysArr = array(
            "access_token" => $access_token,
            "unionid"=> 1
        );

        $graph_url = $this->urlUtils->combineURL(self::GET_OPENID_URL, $keysArr);
        $response = $this->urlUtils->get_contents($graph_url);

        //--------检测错误是否发生
        if(strpos($response, "callback") !== false){

            $lpos = strpos($response, "(");
            $rpos = strrpos($response, ")");
            $response = substr($response, $lpos + 1, $rpos - $lpos -1);
        }

        $user = json_decode($response);
        if(isset($user->error)){
            throw new \Exception($user->error, $user->error_description);
        }

        //------记录openid
        if(empty($user->openid)){
            throw new \Exception('openid 获取失败');
        }
        return $user->openid;

    }
}
