<?php

namespace app\admin\controller;

use think\Controller;
use think\File;
use think\Request;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

class Upload extends Base
{
    /**
     * 图片上传
     */
    public function upload()
    {
        $file = request()->file('file');
        $info = $file->validate(['ext' => 'jpg,png,gif'])->move(UPLOAD_PATH . DS . 'uploads/images');
        if ($info) {
            $file_path = 'uploads/images/' . $info->getSaveName();
            feifa_file($file_path);
            echo $info->getSaveName();
        } else {
            echo $file->getError();
        }
    }

    /**
     * 会员头像上传
     */
    public function uploadface()
    {
        $file = request()->file('file');
        $info = $file->validate(['ext' => 'jpg,png,gif'])->move(UPLOAD_PATH . DS . 'uploads/face');
        if ($info) {
            $file_path = 'uploads/face/' . $info->getSaveName();
            feifa_file($file_path);
            echo $info->getSaveName();
        } else {
            echo $file->getError();
        }
    }

    /**
     * 网站图标上传
     */
    public function uploadico()
    {
        $file = request()->file('file');
        $info = $file->validate(['ext' => 'jpg,png,gif,ico'])->move(UPLOAD_PATH . DS . 'uploads/images');
        if ($info) {
            $file_path = 'uploads/images/' . $info->getSaveName();
            feifa_file($file_path);
            echo $info->getSaveName();
        } else {
            echo $file->getError();
        }
    }

    /**
     * 七牛云上传
     */
    public function Qiniuupload()
    {
        $file     = request()->file('file');
        $filePath = $file->getRealPath();
        $ext      = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $key      = substr(md5($file->getRealPath()), 0, 5) . date('YmdHis') . rand(0, 9999) . '.' . $ext;
        require_once '../extend/qiniu/autoload.php';
        $accessKey = config('qiniu_ak');
        $secretKey = config('qiniu_sk');
        $auth      = new Auth($accessKey, $secretKey);
        $bucket    = config('qiniu_bucket');
        $domain    = config('qiniu_domain');
        $token     = $auth->uploadToken($bucket);
        $uploadMgr = new UploadManager();
        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        if ($err !== null) {
            echo $err;
        } else {
            echo $ret['key'];
        }
    }

    /**
     * 七牛云上传（内部调用）
     */
    public function QiniuuploadAction($filePath)
    {
        $key = substr($filePath, strripos($filePath, '\\') + 1);
        require_once '../extend/qiniu/autoload.php';
        $accessKey = config('qiniu_ak');
        $secretKey = config('qiniu_sk');
        $auth      = new Auth($accessKey, $secretKey);
        $bucket    = config('qiniu_bucket');
        $domain    = config('qiniu_domain');
        $token     = $auth->uploadToken($bucket);
        $uploadMgr = new UploadManager();
        $filePath  = str_replace('\\', '/', $filePath);
        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        if ($err !== null) {
            return ['code' => -1, 'msg' => $err];
        } else {
            return ['code' => 1, 'msg' => '', 'src' => $ret['key']];
        }
    }
}