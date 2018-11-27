<?php

namespace api\modules\v1\controllers;

use api\models\Goods as GoodsModel;
use api\models\Image as ImageModel;
use api\modules\CommonFunc;
use Yii;
use api\modules\v1\service\UserToken as UserTokenService;
use api\modules\v1\service\AipOcr;
use api\modules\v1\service\FoundMsg;
use api\models\User as UserModel;

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
        $title = $request['title'];
        $phone = $request['phone'];
        $is_found = $request['is_found'];
        $is_card = $request['is_card'];
        $way = $request['way'];

        $uid = UserTokenService::getCurrentTokenVar('uid');
        if (!is_dir("image/{$uid}")) {
            mkdir("image/{$uid}");//根据用户的uid命名文件夹，username可能文件夹命名不支持
            chmod("image/{$uid}", 0777);//Linux 系统要这样写
        }
        $GoodsModel = new GoodsModel();
        $GoodsModel->uid = $uid;
        $GoodsModel->title=$title;
        $GoodsModel->description = $description;
        $GoodsModel->phone = $phone;
        $GoodsModel->way = $way;
        $GoodsModel->is_found = $is_found ? 1 : 0;
        $GoodsModel->is_card = $is_card;
        $GoodsModel->status = 1;
        if (!$is_found) {
            $domain = YII::$app->params['domain'];
            $GoodsModel->head_url = $domain.'images/lost.jpg';
        }else{
            $domain = YII::$app->params['domain'];
            $GoodsModel->head_url = $domain.'images/found.jpg';
        }
        $GoodsModel->save();

        $msgRe='没有发送';
        if($is_card){
            if(isset($request['student_id'])){
                $userModel=UserModel::find()->where(['student_id' =>$request['student_id']])->one();
                $foundMsg=new FoundMsg();
                if($userModel) {
                    //TODO 一种是发布信息的时候触发，另一种是绑定的时候触发
                    $msgRe=$foundMsg->send($userModel['form_id'], $userModel['openid'],$GoodsModel['id'],$way,$phone);
                }
            }
        }
        $result = [
            'goods_id' => $GoodsModel['id'],
            'uid' => $GoodsModel['uid'],
            'msgRe'=>$msgRe
        ];
        return self::success($result);
    }

//    //内部API 通过crl_post 来触发
//    public function actionFound_msg(){
//        $request = Yii::$app->request->bodyParams;
//        $foundMsg=new FoundMsg();
//        $foundMsg->send($request['form_id'], $request['openid'],$request['goods_id'],$request['way'],$request['phone']);
//        return self::success();
//    }


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
    public function actionSearch($text, $page,$page_size)
    {;
        $offset = $page_size * ($page - 1);
        $goods = GoodsModel::find()->where(['like', 'description', $text])->andWhere(['status'=>1])->
        orderBy('created DESC')->with('user')->offset($offset)->limit($page_size)->asArray()->all();
        foreach ( $goods as &$item){
            $item['created']=CommonFunc::get_last_time(strtotime($item['created']));
        }
        return self::success(['list'=>$goods]);
    }

    /**
     * @description 测试腾讯AI开发平台
     *
     */
    public function actionOcr_test(){
       $AIconfig=Yii::$app->params['AI'];
       \Configer::setAppInfo($AIconfig['APPID'],$AIconfig['APPKEY']);
       $image_data = file_get_contents('images/card.jpg');//File_get_contents 去读取临时目录的文件而不是保存后的
        $params = array(
            'image' => base64_encode($image_data),
        );
        $response = \API::generalocr($params);
        return  json_decode($response);
    }
    /**
     * @description 接入腾讯AI开发平台
     *
     */
    public function actionOcr(){
        $imageUrlLocal = "image/";
        $file = $_FILES["image"];
        $fp = $imageUrlLocal .time() . '.jpg';
        if (move_uploaded_file($file['tmp_name'], $fp)) {//保存文件
            $image_data = file_get_contents($fp);//File_get_contents 去读取临时目录的文件而不是保存后的
            $AIconfig = Yii::$app->params['AI'];
            \Configer::setAppInfo($AIconfig['APPID'], $AIconfig['APPKEY']);
            $params = array(
                'image' => base64_encode($image_data),
            );
            $response = \API::generalocr($params);
            return  json_decode($response);
        }else{
            return self::success('','40001','识别失败');
        }
    }

    /**
     * @description 接入百度AI开发平台
     * 百度AI 存在方向检测不出的问题
     */
    public function actionBaidu_ocr_test(){
        $baiDuAI=Yii::$app->params['BaiDuAI'];
        $aipOcr=new AipOcr($baiDuAI['appId'],$baiDuAI['apikey'],$baiDuAI['secretKey']);
        $domain = YII::$app->params['domain'];
        $image_data = file_get_contents('images/card.jpg');
        $res=$aipOcr->basicAccurate($image_data);
        return $res;
    }

    /**
     * @description 接入百度AI开发平台
     * 百度AI 存在方向检测不出的问题
     */
    public function actionBaidu_ocr(){
        $imageUrlLocal = "image/";
        $file = $_FILES["image"];
        $fp = $imageUrlLocal .time() . '.jpg';
        if (move_uploaded_file($file['tmp_name'], $fp)) {//保存文件
            $image_data = file_get_contents($fp);
            $baiDuAI=Yii::$app->params['BaiDuAI'];
            $aipOcr=new AipOcr($baiDuAI['appId'],$baiDuAI['apikey'],$baiDuAI['secretKey']);
            $res=$aipOcr->basicAccurate($image_data);
            return $res;
        }else{
            return self::success('','40001','识别失败');
        }
    }

}
