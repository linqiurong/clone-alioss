<?php
namespace clonelin\alioss;

class AliOss{
    protected $aliossBase ;
    public function __construct($config){

        $this->aliossBase = new Base($config);
    }
    // 上传文件
    public function upload($saveName,$fileName){
        $result = $this->aliossBase->upload($saveName,$fileName);
        return $result;
    }
    // 生成GetObject的签名URL,主要用于私有权限下的读访问控制。
    public function getFile($fileName){
        $fileUrl = $this->aliossBase->getSignedUrlForGettingObject($fileName,'');
        return $fileUrl;
    }
    // 生成PutObject的签名URL,用于私有权限下的写访问控制。
    public function putFile($fileName){
        $result = $this->aliossBase->getSignedUrlForPuttingObject($fileName);
        return $result; // true flase
    }
    public function delFile($fileName){
        $result = $this->aliossBase->deleteObject($fileName);
        return $result;
    }
    public function delFiles($fileName = []){
        if(!empty($fileName)){
            $result = $this->aliossBase->deleteObjects($fileName);
            return $result;
        }
    }
    public function copyFile($fromBuckets,$fromName,$toBuckets,$toName){
        $this->aliossBase->copyObject($fromBuckets,$fromName,$toBuckets,$toName);
    }
}