<?php
/*
 * File: LoginController.php
 * File Created: Tuesday, 15th January 2019 10:33:24 am
 * Author: 窦雪峰 (douxuefeng@ihuanyan.cn)
 * -----
 * Last Modified: Thursday, 17th January 2019 6:02:20 pm
 * Modified By: 窦雪峰 (douxuefeng@ihuanyan.cn>)
 * -----
 * Copyright 2019 山西欢颜网络科技有限公司
 */

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OauthAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    protected function guard(){
        return Auth::guard('api');
    }
    protected function mini(){
        $config = [
            'app_id' => config('comm.mini_app_id'),
            'secret' => config('comm.mini_secret'),
            'response_type' => 'array',
            'log' => [
                'level' => 'debug',
                'file' => storage_path().'/logs/wechatmini.log',
            ],
        ];
        return $config;
    }



    public function login(Request $request)
    {
//        $data = jiemi($request->ihy);
        $data = $request->all();
        // 会员登陆 根据登陆终端分流；
        switch ($data['type']) {
            case 'mini': //小程序登陆
                $user = User::where('openid',$data['openid'])->orderByDesc('id')->first(['id','nickname','avatar','phone','is_vip']);
                if (!empty($user) ) {
                    $user->update(['mini_formid'=>$data['mini_formid']]);
                    // 排他性登陆
                    OauthAccessToken::where(['user_id'=>$user['id'],'name'=>'mini'])->delete();
                    $token = $user->createToken('mini')->accessToken;
                    $user = User::where('openid',$data['openid'])->first(['id','nickname','avatar','phone','is_vip']);
                    return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                } else {
                    $i['nickname'] = $data['nickName'];
                    $i['openid'] = $data['openid'];
                    $i['avatar'] = $data['avatar'];
                   // $i['gender'] = $data['gender'];
                    $i['mini_formid'] = $data['mini_formid'];
                    $user = User::create($i);
                    $user = User::find($user->id,['id','nickname','avatar','phone','is_vip']);
                    $token = $user->createToken('mini')->accessToken;
                    return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                }
                break;
            case 'password':
                # 手机密码登陆
                $user = User::where('phone',$data['phone'])->first(['id','nickname','avatar','password','is_vip','phone']);
                if (empty($user)) {
                    return json(201,'','该手机号未注册，请先注册');
                }else{
                    if (Hash::check($data['password'], $user['password'])) {
                        
                        OauthAccessToken::where(['user_id'=>$user['id'],'name'=>'password'])->delete();
                        $token = $user->createToken('password')->accessToken;
                        $user = array_except($user, ['password']);
                        return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                    } else {
                        return json(201,'','账号或密码错误');
                    }
                }
                break;
            case 'weixin':
                $user = User::where('wxopenid',$data['wxopenid'])->first(['id','nickname','avatar','phone','is_vip']);
                if (empty($user)) {
                    //创建 用户
                    $i['nickname'] = $data['nickName'];
                    $i['wxopenid'] = $data['wxopenid'];
                    $i['avatar'] = $data['avatar'];
                    $i['gender'] = $data['gender'];
                    $user = User::create($i);
                    $user = User::find($user->id,['id','nickname','avatar','phone','is_vip']);
                    $token = $user->createToken('weixin')->accessToken;
                    return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                } else {
                    OauthAccessToken::where(['user_id'=>$user['id'],'name'=>'weixin'])->delete();
                    $token = $user->createToken('weixin')->accessToken;
                    return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                }

                break;
            case 'qq':
                $user = User::where('qqopenid',$data['qqopenid'])->first(['id','nickname','avatar','phone','is_vip']);
                if (empty($user)) {
                    //创建 用户
                    $i['nickname'] = $data['nickName'];
                    $i['qqopenid'] = $data['qqopenid'];
                    $i['avatar'] = $data['avatar'];
                    $i['gender'] = $data['gender'];
                    $user = User::create($i);
                    $user = User::find($user->id,['id','nickname','avatar','phone','is_vip']);
                    $token = $user->createToken('qq')->accessToken;
                    return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                } else {
                    OauthAccessToken::where(['user_id'=>$user['id'],'name'=>'qq'])->delete();
                    $token = $user->createToken('qq')->accessToken;
                    return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                }
                break;
            case 'code':
                // 手机验证码
                $user = User::where('phone',$data['phone'])->first(['id','nickname','avatar','is_vip','phone']);
                if (empty($user)) {
                    if (Cache::store('redis')->has('sms_code_'.$data['phone'])) {
                        $code = Cache::store('redis')->get('sms_code_'.$data['phone']);
                    } else {
                        return json(201,'','验证码错误');
                    }
                    if ($code == $data['code']) {
                        $i['phone'] = $data['phone'];
                        $i['password'] = bcrypt($data['password']);
                        $i['nickname'] = '龙承书院会员';
                        $user = User::create($i);
                        $user = User::find($user->id,['id','nickname','avatar','is_vip','phone']);
                        $token = $user->createToken('phone')->accessToken;
                        return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'注册成功');
                    }else {
                        return json(201,'','验证码错误');
                    }
                }else{
                    if (Cache::store('redis')->has('sms_code_'.$data['phone'])) {
                        $code = Cache::store('redis')->get('sms_code_'.$data['phone']);
                    } else {
                        return json(201,'','验证码错误');
                    }
                    if ($code == $data['code']) {
                        OauthAccessToken::where(['user_id'=>$user['id'],'name'=>'phone'])->delete();
                        $token = $user->createToken('phone')->accessToken;
                        return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                    }else {
                        return json(201,'','验证码错误');
                    }
                }
                break;
            case 'reg':
                // 注册
                $user = User::where('phone',$data['phone'])->first();
                if (!empty($user)) {
                    return json(201,'','改手机号已注册，请直接登陆');
                } else {
                    if (Cache::store('redis')->has('sms_code_'.$data['phone'])) {
                        $code = Cache::store('redis')->get('sms_code_'.$data['phone']);
                    } else {
                        return json(201,'','验证码错误');
                    }
                    if ($code == $data['code']) {
                        $i['phone'] = $data['phone'];
                        $i['password'] = bcrypt($data['password']);
                        $i['nickname'] = '龙承书院会员';
                        $user = User::create($i);
                        $user = User::find($user->id,['id','nickname','avatar','is_vip','phone']);
                        $token = $user->createToken('phone')->accessToken;
                        return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'注册成功');
                    }else {
                        return json(201,'','验证码错误');
                    }
                }

                break;

            case 'share':
                // 小程序分享邀请注册
                $user = User::where('openid',$data['openid'])->orderByDesc('id')->first(['id','nickname','avatar','phone','is_vip']);
                if (!empty($user) ) {
                    $user->update(['mini_formid'=>$data['mini_formid'],'nickname'=>$data['nickName'],'avatar'=>$data['avatar']]);
                    // 排他性登陆
                    OauthAccessToken::where(['user_id'=>$user['id'],'name'=>'mini'])->delete();
                    $token = $user->createToken('mini')->accessToken;
                    $user = User::where('openid',$data['openid'])->first(['id','nickname','avatar','phone','is_vip']);
                    return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                } else {
                    $i['nickname'] = $data['nickName'];
                    $i['openid'] = $data['openid'];
                    $i['avatar'] = $data['avatar'];
                    $i['gender'] = $data['gender'];
                    $i['mini_formid'] = $data['mini_formid'];
                    $i['rec_id'] = $data['share_user'];
                    $user = User::create($i);
                    $user = User::find($user->id,['id','nickname','avatar','phone','is_vip']);
                    $token = $user->createToken('mini')->accessToken;
                    return json(200,array('token'=>'Bearer '.$token,'userInfo'=>$user),'登陆成功');
                }
                break;
        }
    }

    public function logout(Request $request)
    {
        if ($this->guard()->check()) {
        	$this->guard()->user()->token()->delete();
        }
        return json(200,'','退出成功');
    }
}
