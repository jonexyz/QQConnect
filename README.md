# QQConnect
只是为了方便自己写网站使用，官方的sdk包用起来很是不方便，每次使用都要翻看文档和示例。

也看过别人封装的代码，要么就只适用于TP5 要么就是不做修改的直接搬运官方sdk，使用方法Demo 也是没有，让我感觉用起来太麻烦了。

QQ互联，基于官方sdk包修改与封装，另许多框架都对session操作进行了重新封装，避免在框架中使用时无法读取$_SESSION数据，这里将不做任何的数据存储，access_token 的有效期维护，需要自行处理。

### 说明
官方文档 https://wiki.connect.qq.com/
1. access_token 过期时间为30天。如果存储的access token过期，请重新走登录流程，access token 的数据缓存需自行维护。
2. 代码中使用了 $_GET 全局变量获取数据。
3. 代码不适用于 swoole 类型的常驻内存框架使用。
4. 如需避免抛出的异常信息暴露给用户，建议使用 try{ }catch(){} 捕获。


### 业务流程
1. 实例化 QC类  执行 qq_login() 方法， 会生成一个QQ的登陆URL，访问该URL，进行授权登陆。
2. 授权登陆成功，会携带 code 跳转至设置的回调地址， 使用 qq_callback() 接收 code值，（code值是获取access_token的凭证），从而获取 access_token
3. 凭借 access_token 获取 用户的 open_id
4. 有了 access_token 与 open_id 执行 setKeysArr() 方法，设置接口的公共请求参数。
5. 执行 get_user_info() 方法获取用户信息，写入数据表 

### 食用说明, Laravel 代码示例
```$xslt
    $appid = "102***947"  //应用appid
    $appkey = "FuMaaMwJT***ew0pP" //应用appkey
    $callback  = "https://www.***.cn/login/qcallback" //应用授权回调地址
    $scope = "get_user_info,add_share"  //申请获取的应用权限，多个以英文逗号相隔
```

```$xslt
use jonexyz\QQConnect\QC;

    // qq登录
    public function qq()
    {
        $qc = new QC($appid,$appkey,$callback,$scope);
       return redirect($qc->qq_login());
    }
```

```$xslt
use jonexyz\QQConnect\QC;

// 回调操作
    public function qqCallback(Request $request)
    {
        $qc = new QC($appid,$appkey,$callback,$scope);

        if(!$request->get('code')){
            if(cache('QC_userData')['openid']){
                $qc->setKeysArr($access_token, $openid);
                $info = $qc->get_user_info();
                $data = [
                    'name'=>$info['nickname'], //昵称
//                    ''=>$info['gender_type'], // 性别 1男
//                    ''=>$info['province'], //省份
//                    ''=>$info['city'], //城市
//                    ''=>$info['figureurl_2'], //头像
//                    ''=>$info['year'], // 出生年分
                ];
                
                // TODO 信息获取成功，保存用户数据，实现用户登录

                // 进入登陆成功页
                return redirect(url('user'));
            }else{
                // 授权回调失败，显示404
                abort(404);
            }
        }else{
            $access_token = $qc->qq_callback();
        
            if($access_token){
                $openid = $qc->get_openid();

                $user = User::where('qq_openid',$openid)->first();

                if($user){
                    // TODO 信息获取成功，保存用户数据，实现用户登录
                    
                    // 缓存数据，access_token有效期30天
                    cache('QC_userData',['access_token'=>$access_token,'openid'=>$openid], 29*24*3600);
                    // 进入登陆成功页
                    return redirect(url('user'));
                }else{
                    // 重新访问回调地址
                    return redirect($callback);
                }

            }else{
                return abort(404);
            }
        }
    }
```
