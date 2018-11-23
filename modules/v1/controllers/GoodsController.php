<?php

namespace api\modules\v1\controllers;

use api\models\Goods as GoodsModel;
use api\models\Image as ImageModel;
use api\modules\CommonFunc;
use Yii;
use api\modules\v1\service\UserToken as UserTokenService;
require_once Yii::getAlias("@common/lib/AI/include.php");
class GoodsController extends BaseActiveController
{
    public $modelClass = 'api\models\Goods';

    /**
     * 创建商品信息
     * 如果类型是捡到：默认是无效的0  等传好图片之后再设置为有效1
     * 如果类型是丢失：则直接为有效的
     * @params int is_found
     * @params string $description 商品名称和信息
     * @params string $phone 用户联系方式
     * @params int $categoryID 目录ID
     * @return  int goods_id 商品id
     * @return  int uid 用户uid
     */
    public function actionNew()
    {
        $request = Yii::$app->request->bodyParams;
        $description = $request['description'];
        $phone = $request['phone'];
        $is_found = $request['is_found'];
        $way = $request['way'];
        $uid = UserTokenService::getCurrentTokenVar('uid');
        if (!is_dir("image/{$uid}")) {
            mkdir("image/{$uid}");//根据用户的uid命名文件夹，username可能文件夹命名不支持
            chmod("image/{$uid}", 0777);//Linux 系统要这样写
        }
        $GoodsModel = new GoodsModel();
        $GoodsModel->uid = $uid;
        $GoodsModel->description = $description;
        $GoodsModel->phone = $phone;
        $GoodsModel->way = $way;
        $GoodsModel->is_found = $is_found ? 1 : 0;
        $GoodsModel->status = 1;
        if (!$is_found) {
            $domain = YII::$app->params['domain'];
            $GoodsModel->head_url = $domain.'images/lost.jpg';
        }else{
            $domain = YII::$app->params['domain'];
            $GoodsModel->head_url = $domain.'images/found.jpg';
        }
        $GoodsModel->save();
        $result = [
            'goods_id' => $GoodsModel['id'],
            'uid' => $GoodsModel['uid']
        ];
        return self::success($result);
    }

    /**
     * @params int $ishead 表示是否是封面
     * @params files $image 商品图片
     * @params int $goods_id 商品ID
     * @params int $uid 用户ID
     * @return array
     * @throws \Exception
     */
    public function actionImage_upload()
    {
        $request = Yii::$app->request->bodyParams;
        $goods_id = $request['goods_id'];
        $uid = $request['uid'];
        $ishead = $request['ishead'];
        $GoodsModel = GoodsModel::findOne($goods_id);
        if (!is_dir("image/{$uid}")) {
            throw new \Exception('用户个人目录未创建成功');
        }
        $imageModel = new ImageModel();
        $imageModel->goods_id = $goods_id;
        $domain = YII::$app->params['domain'];
        $imageUrlLocal = "image/{$uid}/";
        $file = $_FILES["image"];
        if ($file) {//如果有上传的文件
            $fp = $imageUrlLocal . microtime() . '.jpg';
            if (move_uploaded_file($file['tmp_name'], $fp)) {//保存文件
                $imageUrlLocal = $domain . $fp;
                $imageModel->url = $imageUrlLocal;
                $imageModel->save();
                if ($ishead) {
                    $GoodsModel->head_url = $imageUrlLocal;
                    $GoodsModel->status = 1;
                    $GoodsModel->update();
                }
            }
        }
        return self::success('');
    }

    /**
     * @description 用户操作已经上传的信息
     * @params int $goods_id 商品id
     * @params int $handle_type 标记操作状态，2表示已对接或者已找回
     */
    public function actionHandle()
    {
        $request = Yii::$app->request->bodyParams;
        $goods_id = $request['goods_id'];
        $handle_type = $request['handle_type'];
        $GoodsModel = GoodsModel::findOne($goods_id);
//        if ($handle_type == 2) {
            $GoodsModel->status = 2;
            $GoodsModel->update();
//        }
        return self::success();
    }

    /**
     * @description 获取到商品的详情
     * @method GET
     * @param int $goods_id
     * @return array $GoodsModel 返回user表的商品模型关联
     */
    public function actionDetail($goods_id)
    {
        $GoodsModel = GoodsModel::find()->where(['id' => $goods_id])->with('user', 'images')->asArray()->one();
        return self::success($GoodsModel);
    }

    /**
     * @description search搜索
     * @param string $text
     * @return array $GoodsModel 返回user表的商品模型关联
     */
    public function actionSearch($text, $page)
    {
        $pageSize = 10;
        $offset = $pageSize * ($page - 1);
        $goods = GoodsModel::find()->where(['like', 'description', $text])->andWhere(['status'=>1])->
        orderBy('created DESC')->with('user')->offset($offset)->limit($pageSize)->asArray()->all();
        foreach ( $goods as &$item){
            $item['created']=CommonFunc::get_last_time(strtotime($item['created']));
        }
        return self::success($goods);
    }

    /**
     * @description 测试并接入腾讯AI开发平台
     *
     */
    public function actionOcr(){
       $AIconfig=Yii::$app->params['AI'];
       \Configer::setAppInfo($AIconfig['APPID'],$AIconfig['APPKEY']);
       $image_data = file_get_contents('images/card.jpg');//File_get_contents 去读取临时目录的文件而不是保存后的
        $params = array(
            'image' => base64_encode($image_data),
        );
        $response = \API::generalocr($params);
        var_dump($response);
        return $response;
    }

}
