<?php

namespace api\modules\v1\controllers;

use api\models\Needs as NeedsModel;
use api\models\User as UserModel;
use api\models\Goods as GoodsModel;
use api\models\Want as WantModel;
use Faker\Provider\File;
use Yii;
use api\modules\v1\service\UserToken as UserTokenService;


require_once Yii::getAlias("@common/lib/encrypt/wxBizDataCrypt.php");

class UserController extends BaseActiveController
{
    public $modelClass = 'api\models\User';

    /**
     * @description 破解用户信息和用户登录
     * @param  encryptedData
     * @param  iv
     * @return array
     */
    public function actionEncrypt_user_info()
    {
        $uid = UserTokenService::getCurrentTokenVar('uid');
        $userModel = UserModel::findOne($uid);
        $userModel = UserTokenService::saveUserInfo($userModel);
        return self::success($userModel);
    }

    /**
     * @description 破解用户手机号
     * @param  encryptedData
     * @param  iv
     * @return array
     */
    public function actionEncrypt()
    {
        $session_key = UserTokenService::getCurrentTokenVar('session_key');
        $wxLogin = Yii::$app->params['wxLogin'];
        $wxAppID = $wxLogin['app_id'];//从配置文件中读取
        $request = Yii::$app->request->bodyParams;//获取到参数
        $encryptedData = $request['encryptedData'];
        $iv = $request['iv'];
        $wxBiz = new \WXBizDataCrypt($wxAppID, $session_key);
        $data = '';
        $code = $wxBiz->decryptData($encryptedData, $iv, $data);
        if ($code == 0) {
            $result = json_decode($data);
            return self::success($result);
        } else {
            return self::success('', 202, '破解失败');
        }
    }

    /**
     * @description  获取用户发布的信息
     * @return array
     */
    public function actionGoods($page)
    {
        $pageSize = 10;
        $offset = $pageSize * ($page - 1);
        $uid = UserTokenService::getCurrentTokenVar('uid');
        $goods = GoodsModel::find()->where(['uid' => $uid, 'status' => 1])->
        orderBy('created DESC')->offset($offset)->limit($pageSize)->all();
        return self::success($goods);
    }

    /**
     * @description 修改用户头像和昵称
     * @param avatar  File 用户图片
     * @param   uid  int 用户ID
     * @return array
     */
    public function actionInfo_edit()
    {
        $file = $_FILES["avatar"];
        $uid = $_REQUEST['uid'];
        if (!is_dir("image/{$uid}")) {
            mkdir("image/{$uid}");//根据用户的OpenID命名文件夹，username可能文件夹命名不支持
            chmod("image/{$uid}", 0777);//Linux 系统要这样写
        }
        $userModel = UserModel::findOne($uid);
        $userModel->nickName = $_REQUEST['nickName'];
        $domain = YII::$app->params['domain'];
        $imageUrlLocal = "image/{$uid}/";
        $time = time();
        $fp = $imageUrlLocal . "avatar{$time}.jpg";
        if (move_uploaded_file($file['tmp_name'], $fp)) {//保存文件
            $imageUrlLocal = $domain . $fp;
            $userModel->avatarUrl = $imageUrlLocal;
        }
        $userModel->update();
        return  $userModel;
    }

    /**
     * @description 只修改昵称
     * @param nickName string
     * @return array
     */
    public function actionInfo_edit_n()
    {
        $request = Yii::$app->request->bodyParams;
        $uid = UserTokenService::getCurrentTokenVar('uid');
        $userModel = UserModel::findOne($uid);
        $userModel->nickName = $request['nickName'];
        $userModel->update();
        return self::success($userModel);
    }

}
