<?php
namespace clonelin\alioss;

use OSS\Core\OssException;
use OSS\Http\RequestCore;
use OSS\Http\ResponseCore;
use OSS\OssClient;

class Base{

    protected $accessKeyId = '';
    protected $accessKeySecret = '';
    protected $endPoint = '';
    protected $usedCName = '';
    protected $securityToken = '';
    protected $bucket = '';
    protected $objAliossClient = '';

    public function __construct($aliossConfig)
    {

        $this->setConfigs($aliossConfig);
        try {
            // true为开启CNAME。CNAME是指将自定义域名绑定到存储空间上
            $this->objAliossClient = new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endPoint, $this->usedCName);
        } catch (OssException $e) {
            print $e->getMessage();
        }
    }

    /**
     * @param $config
     */
    protected function setConfigs($config)
    {

        $accessKeyId = $config['access_key_id'];
        $accessKeySecret = $config['access_key_secret'];
        $endPoint = $config['endpoint'];
        $bucket = $config['bucket'];

        if (empty($accessKeyId) || empty($accessKeySecret) || empty($endPoint) || empty($bucket)) {
            die("AliOSS参数不完整,请联系管理员");
        }

        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->endPoint = $endPoint;
        $this->usedCName = false;
        $this->bucket = $bucket;

    }

    /**
     * 上传文件
     * @param $object 文件名称
     * @param $localFileName 由本地文件路径加文件名包括后缀组成
     * @return string|void 上传文件后的可访问目录
     */
    public function upload($object, $localFileName)
    {
        try {
            $uploadResult = $this->objAliossClient->uploadFile($this->bucket, $object, $localFileName);
            $info = $uploadResult['info'];
            if ($info['http_code'] == 200) {
                $content = '';
                $saveUrl = $object;
                $authUrl = $this->getSignedUrlForGettingObject($object, $content);
                return array(
                    'status' => true,
                    'msg' => 'Success',
                    'saveUrl' => $saveUrl,
                    'authUrl' => $authUrl
                );
            }

        } catch (OssException $e) {
            return array(
                'status' => false,
                'msg' => $e->getMessage(),
                'saveUrl' => '',
                'authUrl' => '',
            );
        }
    }

    /**
     * 文件下载
     * @param $object // 文件名称
     */
    public function download($object)
    {
        try {
            $content = $this->objAliossClient->getObject($this->bucket, $object);
            print("object content: " . $content);
        } catch (OssException $e) {
            print $e->getMessage();
        }
    }

    /**
     * 列出Bucket内所有目录和文件，
     * 根据返回的nextMarker循环调用listObjects接口得到所有文件和目录
     * @param $ossClient
     * @param $bucket 存储空间名称
     */
    public function listAllObjects()
    {
        //构造dir下的文件和虚拟目录
        for ($i = 0; $i < 100; $i += 1) {
            $this->objAliossClient->putObject($this->bucket, "dir/obj" . strval($i), "hi");
            $this->objAliossClient->createObjectDir($this->bucket, "dir/obj" . strval($i));
        }
        $prefix = 'dir/';
        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 30;
        while (true) {
            $options = array(
                'delimiter' => $delimiter,
                'prefix' => $prefix,
                'max-keys' => $maxkeys,
                'marker' => $nextMarker,
            );

            try {
                $listObjectInfo = $this->objAliossClient->listObjects($this->bucket, $options);
            } catch (OssException $e) {
                return;
            }
            // 得到nextMarker，从上一次listObjects读到的最后一个文件的下一个文件开始继续获取文件列表
            $nextMarker = $listObjectInfo->getNextMarker();
            $listObject = $listObjectInfo->getObjectList();
            $listPrefix = $listObjectInfo->getPrefixList();
            if ($nextMarker === '') {
                break;
            }
        }
    }

    /**
     * 判断object是否存在
     *
     * @param $object 文件名
     * @param string $bucket bucket名字
     * @return null
     */
    public function doesObjectExist($object, $bucket)
    {

        try {
            $exist = $this->objAliossClient->doesObjectExist($bucket, $object);
        } catch (OssException $e) {
            return;
        }
    }

    /**
     * 创建虚拟目录
     *
     * @param $dirName 目录名称
     * @return null
     */
    public function createObjectDir($dirName)
    {
        try {
            $this->objAliossClient->createObjectDir($this->bucket, $dirName);
        } catch (OssException $e) {
            return;
        }
        print(__FUNCTION__ . ": OK" . "\n");
    }

    /**
     * 删除object
     *
     * @param $object 文件名称
     * @return null
     */
    public function deleteObject($object)
    {
        try {
            $this->objAliossClient->deleteObject($object);
        } catch (OssException $e) {
            return;
        }
    }

    /**
     * 批量删除object
     *
     * @param $objects array 文件名称
     * @return null
     */
    public function deleteObjects($objects)
    {
        try {
            $this->objAliossClient->deleteObjects($this->bucket, $objects);
        } catch (OssException $e) {
            return;
        }
    }

    /**
     * 拷贝object
     * @param $from_bucket
     * @param $from_object
     * @param $to_bucket
     * @param $to_object
     */
    public function copyObject($from_bucket, $from_object, $to_bucket, $to_object)
    {
        try {
            $this->objAliossClient->copyObject($from_bucket, $from_object, $to_bucket, $to_object);
        } catch (OssException $e) {
            return;
        }
    }

    /**
     * 修改文件元信息
     * 利用copyObject接口的特性：当目的object和源object完全相同时，表示修改object的文件元信息
     * @param $fromBucket
     * @param $fromObject
     * @param $toBucket
     * @param $toObject
     * https://help.aliyun.com/document_detail/32105.html?spm=a2c4g.11186623.6.818.NS4J76
     * https://help.aliyun.com/document_detail/31859.html?spm=a2c4g.11186623.2.5.3c95Dr
     */
    public function modifyMetaForObject($fromBucket, $fromObject, $toBucket, $toObject)
    {
        $copyOptions = array(
            OssClient::OSS_HEADERS => array(
                'Expires' => '2018-10-01 08:00:00',
                'Content-Disposition' => 'attachment; filename="xxxxxx"',
                'x-oss-meta-location' => 'location',
            ),
        );
        try {
            $this->objAliossClient->copyObject($fromBucket, $fromObject, $toBucket, $toObject, $copyOptions);
        } catch (OssException $e) {
            return;
        }
    }


    /**
     * 获取object meta, 也就是getObjectMeta接口
     * @param $object 存储空间名称
     */
    public function getObjectMeta($object)
    {

        try {
            $objectMeta = $this->objAliossClient->getObjectMeta($this->bucket, $object);
        } catch (OssException $e) {
            return;
        }
        if (isset($objectMeta[strtolower('Content-Disposition')]) &&
            'attachment; filename="xxxxxx"' === $objectMeta[strtolower('Content-Disposition')]
        ) {
            print(__FUNCTION__ . ": ObjectMeta checked OK" . "\n");
        } else {
            print(__FUNCTION__ . ": ObjectMeta checked FAILED" . "\n");
        }
    }


    /**
     * 获取文件范围权限
     * @param $object 文件名称
     * @param $acl 权限
     * https://help.aliyun.com/document_detail/32105.html?spm=a2c4g.11186623.6.818.NS4J76
     */
    public function putObjectAcl($object, $acl)
    { // default private public-read public-read-write

        try {
            $this->objAliossClient->putObjectAcl($this->bucket, $object, $acl);
        } catch (OssException $e) {
            return;
        }
    }

    /**
     * 获取文件访问权限
     * @param $object
     */
    public function getObjectAcl($object)
    {
        try {
            $objectAcl = $this->objAliossClient->getObjectAcl($this->bucket, $object);
        } catch (OssException $e) {

            return;
        }

    }

    /**
     * 解冻归档类型object
     * @param $object 文件名
     */
    public function restoreObject($object)
    {
        try {
            $this->objAliossClient->restoreObject($this->bucket, $object);
        } catch (OssException $e) {

            return;
        }
    }


    /**
     * 创建符号链接
     * 符号链接是一种特殊的object，它指向具体的某个object,类似于windows上使用的快捷方式。
     * @param $symlink 符号链接
     * @param $object 文件名
     */
    function putSymlink($symlink, $object)
    {

        try {
            $this->objAliossClient->putSymlink($this->bucket, $symlink, $object);
        } catch (OssException $e) {
            return;
        }
    }

    /**
     * 获取符号链接所指向的object内容
     * @param $symlink
     */
    public function getSymlink($symlink)
    {
        try {
            $Symlinks = $this->objAliossClient->getSymlink($this->bucket, $symlink);
        } catch (OssException $e) {
            return;
        }
    }


    /**
     * 生成GetObject的签名URL,主要用于私有权限下的读访问控制。
     * @param $object
     * @param $content
     */
    public function getSignedUrlForGettingObject($object, $content = '')
    {
        // 阿里oss 不以 / 传值
        $object = strpos($object, '/') == 0 ? substr($object, 1) : $object;
        if (empty($object)) {
            return false;
        }
        $timeout = 300; // URL的有效期是3600秒
        try {
            $signedUrl = $this->objAliossClient->signUrl($this->bucket, $object, $timeout);
        } catch (OssException $e) {
            return false;
        }
        return $signedUrl;
        /**
         * 可以使用代码来访问签名的URL，也可以输入到浏览器中进行访问。
         */
        //生成的URL默认以GET方式访问。
    }

    /**
     * 生成PutObject的签名URL,用于私有权限下的写访问控制。
     * @param $object
     */
    public function getSignedUrlForPuttingObject($object)
    {
        $timeout = 3600;
        $options = NULL;
        try {
            $signedUrl = $this->objAliossClient->signUrl($this->bucket, $object, $timeout, "PUT");
        } catch (OssException $e) {
            return;
        }
        print(__FUNCTION__ . ": signedUrl: " . $signedUrl . "\n");
        $content = file_get_contents(__FILE__);
        $request = new RequestCore($signedUrl);
        $request->set_method('PUT');
        $request->add_header('Content-Type', '');
        $request->add_header('Content-Length', strlen($content));
        $request->set_body($content);
        $request->send_request();

        $res = new ResponseCore($request->get_response_header(),
            $request->get_response_body(), $request->get_response_code());
        if ($res->isOK()) {
            return true;
            print(__FUNCTION__ . ": OK" . "\n");
        } else {
            return false;
            print(__FUNCTION__ . ": FAILED" . "\n");
        };
    }
}