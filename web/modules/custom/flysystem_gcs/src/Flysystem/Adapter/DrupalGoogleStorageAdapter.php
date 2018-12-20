<?php

namespace Drupal\flysystem_gcs\Flysystem\Adapter;

use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Storage\Acl;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Superbalist\Flysystem\GoogleStorage\GoogleStorageAdapter;

class DrupalGoogleStorageAdapter extends GoogleStorageAdapter
{

  private $public = false;

  public function setPublic($public) {
    $this->public = $public;
  }
  public function isPublic(){
    return $this->public;
  }
  public function upload($path, $contents, Config $config)
  {
    $config->set("visibility", $this->isPublic() ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE);
    return parent::upload($path, $contents, $config);
  }
}
