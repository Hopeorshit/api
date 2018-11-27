<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/27
 * Time: 14:27
 */

namespace api\modules\v1\service;

use api\models\User as UserModel;
use yii\base\Exception;

class FoundMsg extends WxMessage
{
    const templateID='o3NVGcDXj00oSlL8UbSXsHBnKzZ9jl8o2boxrTHAMtI';

    public function send($formID,$openid,$goods_id,$way,$phone){
        $this->tplID=self::templateID;
        $this->formID=$formID;
        $this->page='/pages/goodsdetail/goodsdetail?goods_id='.$goods_id.'&if_found=1';
        $this->prepareMessageData($way,$phone);
        return parent::sendMessage($openid);
    }

    private function prepareMessageData($way,$phone){
        $text='';
        if($way==1){
           $text='指定地点';
        }
        if($way==2){
            $text='qq';
        }
        if($way==3){
            $text='微信';
        }
        if($way==4){
            $text='手机';
        }
        $data=[
            'keyword1'=>[//物品名称
                'value'=>'学生卡'
            ],
            'keyword2'=>[//领取方式
                'value'=>$text
            ],
            'keyword3'=>[//联系方式
                'value'=>$phone
            ]

        ];
        $this->data=$data;
    }

}