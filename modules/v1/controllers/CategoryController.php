<?php

namespace api\modules\v1\controllers;

use api\models\Category;
use api\models\Goods as GoodsModel;
use api\modules\CommonFunc;

class CategoryController extends BaseActiveController
{
    public $modelClass = 'api\models\Category';


    /**
     * @method GET 获取全部商品
     * @params int categoryID
     * @params int page
     * @params int page
     */
    public function actionId($categoryID, $page,$page_size)
    {
        $offset = $page_size * ($page - 1);
        if ($categoryID == 0) {
            $goods = GoodsModel::find()->where(['status' => 1])->with('user', 'images')->
            orderBy('created DESC')->asArray()->offset($offset)->limit($page_size)->all();
        } else {
            if ($categoryID == 1) { //今天
                $startTime=date('Y-m-d 00:00:00');
                $endTime = date('Y-m-d 23:59:59');
                $goods = GoodsModel::find()->where(['status'=>1])->andwhere(['between','created',$startTime,$endTime])->with('user', 'images')
                    ->orderBy('created DESC')->asArray()->offset($offset)->limit($page_size)->all();
            }
            if ($categoryID == 2) {//昨天
                $startTime=date('Y-m-d 00:00:00',strtotime("-1 day"));
                $endTime = date('Y-m-d 23:59:59',strtotime("-1 day"));
                $goods = GoodsModel::find()->where(['status'=>1])->andwhere(['between','created',$startTime,$endTime])->with('user', 'images')
                    ->orderBy('created DESC')->asArray()->offset($offset)->limit($page_size)->all();
            }
            if ($categoryID == 3) {//一周内
                $startTime=date('Y-m-d 00:00:00',strtotime("-1 week"));
                $endTime = date('Y-m-d 23:59:59',strtotime("-1 day"));
                $goods = GoodsModel::find()->where(['status'=>1])->andwhere(['between','created',$startTime,$endTime])->with('user', 'images')
                    ->orderBy('created DESC')->asArray()->offset($offset)->limit($page_size)->all();
            }
            if ($categoryID == 4) {//一周以上
                $startTime=date('Y-m-d 00:00:00',strtotime('-1 week'));
                $goods = GoodsModel::find()->where(['status'=>1])->andwhere(['<=','created',$startTime])->with('user', 'images')
                    ->orderBy('created DESC')->asArray()->offset($offset)->limit($page_size)->all();
            }
        }
        foreach ($goods as &$item) {
            $item['created'] = CommonFunc::get_last_time(strtotime($item['created']));
        }
        return self::success(['list'=>$goods]);
    }

}
