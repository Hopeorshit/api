<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/29
 * Time: 0:23
 */

namespace api\modules\v1\service;


class JsonMsg
{
    public  static function success($data,$code=201,$msg="success"){
        $result=[
            'code'=>$code,
            'data'=>$data,
            'msg'=>$msg
        ];
        return $result;
    }

}