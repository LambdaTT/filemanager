<?php

namespace Filemanager\Services;

use SplitPHP\Service;

class S3 extends Service
{
  private $awsAccessKey;
  private $awsSecretKey;
  private $awsS3Region;
  private $s3BucketName;
  private $s3Class;

  public function init()
  {
    $this->awsAccessKey = getenv('AWS_KEY');
    $this->awsSecretKey = getenv('AWS_SECRET');
    $this->awsS3Region = getenv('AWS_REGION');
    $this->s3BucketName = getenv('AWS_S3_BUCKETNAME');

    require_once dirname(__DIR__) . '/vendor/amazon-s3-php-class/S3.php';
    $this->s3Class = new \S3($this->awsAccessKey, $this->awsSecretKey);
    $this->s3Class->setExceptions(true);
    $this->s3Class->setRegion($this->awsS3Region);
  }

  public function listBuckets()
  {
    return $this->s3Class->listBuckets();
  }

  public function listObjects()
  {
    return $this->s3Class->getBucket($this->s3BucketName);
  }

  public function deleteObject($objName)
  {
    return $this->s3Class->deleteObject($this->s3BucketName, $objName);
  }

  public function putObject($file, $objName, $mimeType = null)
  {
    if ($this->s3Class->putObjectFile($file, $this->s3BucketName, $objName, \S3::ACL_PUBLIC_READ, [], $mimeType))
      return "https://{$this->s3BucketName}.s3.{$this->awsS3Region}.amazonaws.com/{$objName}";

    return null;
  }
}
