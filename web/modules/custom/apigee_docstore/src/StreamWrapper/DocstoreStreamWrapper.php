<?php

namespace Drupal\apigee_docstore\StreamWrapper;

use Apigee\Edge\Api\Docstore\Controller\DocstoreController;
use Apigee\Edge\Api\Docstore\Controller\DocstoreControllerInterface;
use Apigee\Edge\Api\Docstore\Entity\Doc;
use Apigee\Edge\Api\Docstore\Entity\DocstoreEntityInterface;
use Apigee\Edge\Api\Docstore\Entity\Folder;
use Apigee\Edge\Client;
use Apigee\Edge\HttpClient\Plugin\Authentication\Oauth;
use Drupal\apigee_edge\OauthTokenFileStorage;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class DocstoreStreamWrapper implements StreamWrapperInterface {
  use UrlGeneratorTrait;

  private $uri = '';

  /* @var \ArrayIterator */
  private $folderContents;

  /* @var StreamInterface */
  private $stream;

  /* @var bool */
  private $writeFlag = false;

  /* @var DocstoreControllerInterface */
  private $docstoreController;

  public function __construct()
  {
    $this->docstoreController = \Drupal::service("apigee_docstore.controller.docstore_controller_factory")->getController();
  }

  public function dir_closedir()
  {
    $this->setUri('');
    $this->folderContents = new \ArrayIterator();
  }

  private static $pathsloaded = [];

  private function loadByPathFromCache($path, $reset = false){
    $cid = "docstore:" . $path;
    if($reset || !isset(static::$pathsloaded[$path])) {
      $entity = null;
      if(!$reset && $cache = \Drupal::cache()->get($cid)) {
        $entity = $cache->data;
      }
      if($entity == null) {
        $entity = $this->docstoreController->loadByPath($path);
        \Drupal::cache()->set($cid, $entity);
      }
      if($entity != null) {
        static::$pathsloaded[$path] = $entity;
      }
    }
    return static::$pathsloaded[$path]??null;
  }

  private function unsetPathCache($path){
    unset(static::$pathsloaded[$path]);
  }

  public function dir_opendir($path, $options)
  {

    $this->setUri($path);
    $this->folderContents = new \ArrayIterator();

    $targetPath = $this->getTarget($path);
    $resource = static::loadByPathFromCache($targetPath);
    if($resource instanceof Folder) {
      $contents = $this->docstoreController->getFolderContents($resource);
      $this->folderContents = new \ArrayIterator($contents);
      return true;
    } else {
      return false;
    }

  }

  public function dir_readdir()
  {
    if($this->folderContents->valid()) {
      /* @var $entity DocstoreEntityInterface */
      $entity = $this->folderContents->current();
      $this->folderContents->next();
      return $entity->getName();
    }
      return FALSE;
  }

  public function dir_rewinddir()
  {
    $this->folderContents->rewind();
    return $this->folderContents->valid();
  }

  public function mkdir($path, $mode, $options)
  {
    return $this->docstoreController->mkdir($this->getTarget($path), $options & STREAM_MKDIR_RECURSIVE);
  }

  public function rename($path_from, $path_to)
  {
    $this->unsetPathCache($this->getTarget($path_from));
    return $this->docstoreController->rename($this->getTarget($path_from), $this->getTarget($path_to));
  }

  public function rmdir($path, $options)
  {
    $target = $this->getTarget($path);
    $this->unsetPathCache($target);
    $this->docstoreController->rmdir($target, $options & STREAM_MKDIR_RECURSIVE);
  }

  public function stream_open($path, $mode, $options, &$opened_path)
  {
    $this->setUri($path);
    $targetPath = $this->getTarget();
    $entity = static::loadByPathFromCache($targetPath);
    if($entity !== null && !($entity instanceof Doc)) {
      return FALSE;
    }
    $data = "";
    if($entity !== null) {
      $data = $this->docstoreController->getSpecContentsAsJson($entity);
      $opened_path = $path;
    }

    $this->stream = $this->stream = $this->create_temporary_stream($data, $mode);
    return TRUE;
  }

  private function create_temporary_stream($data, $mode = "r+"){
    $temp_location = 'temporary://docstore_'. bin2hex(random_bytes(32));
    file_put_contents($temp_location, $data);
    return new Stream(fopen($temp_location, $mode));

  }
  public function stream_read($count)
  {
    return $this->stream->read($count);
  }

  public function stream_seek($offset, $whence = SEEK_SET)
  {
    $this->stream->seek($offset, $whence);
    // TODO: Implement stream_seek() method.
  }

  public function stream_set_option($option, $arg1, $arg2)
  {
    return FALSE;
    // TODO: Implement stream_set_option() method.
  }

  public function stream_stat()
  {
    //$this->contentStream->stat
    // TODO: Implement stream_stat() method.
  }

  public function stream_tell()
  {
    return $this->stream->tell();
  }

  public function stream_truncate($new_size)
  {
    return FALSE;
  }

  public function stream_write($data)
  {

    if(!$this->writeFlag) {
      $this->stream = $this->create_temporary_stream("", "w+");
      $this->writeFlag = true;
    }
//    var_dump("\ntrying to write \n $data");
    return $this->stream->write($data);
  }

  public function stream_cast($cast_as)
  {
    return FALSE;
  }

  public function stream_flush()
  {
    $targetPath = $this->getTarget();
    $entity = static::loadByPathFromCache($targetPath);
    if($entity != null && !($entity instanceof Doc)) {
      return FALSE;
    }
    if($entity == null) {
      $pathArr = explode(DIRECTORY_SEPARATOR, $targetPath);
      $spec_name = array_pop($pathArr);
      $entity = new Doc(['name' => $spec_name]);
      if(!empty($pathArr)) {
        $directory_path = implode(DIRECTORY_SEPARATOR, $pathArr);
        $folder = static::loadByPathFromCache($directory_path);
        if($folder !== null && !($folder instanceof Folder)) {
          return FALSE;
        }
        if($folder == null){
          $this->docstoreController->mkdir($directory_path, true);
          $folder = static::loadByPathFromCache($directory_path);
        }
        $entity->setFolder($folder->id());
      }
      $this->docstoreController->createDoc($entity);
    }
    $this->stream->seek(0);
    $this->docstoreController->uploadJsonSpec($entity, $this->stream->getContents());
    $this->unsetPathCache($targetPath);
    return TRUE;
  }

  public function stream_lock($operation)
  {
    return FALSE;
  }

  public function stream_metadata($path, $option, $value)
  {
    return FALSE;
  }
  public function stream_close()
  {
    $this->stream->close();
  }

  public function stream_eof()
  {
    return $this->stream->eof();
  }
  public function unlink($path)
  {
    return $this->docstoreController->unlink($this->getTarget($path));
  }

  public function url_stat($path, $flags)
  {
    $entity = static::loadByPathFromCache($this->getTarget($path));
    $mode =  ($entity == null ? 0 :($entity instanceof Doc ? '33279' : '16895') );
    $size = 0;
    return [
      'dev' => 0,
      'ino' => 0,
      'mode' => $mode,
      'nlink' => 1,
      'uid' => 0,
      'gid' => 0,
      'rdev' => 0,
      'size' => $size,
      'atime' => 0,
      'mtime' => $entity == null ? 0 : $entity->getModified()->getTimestamp(),
      'ctime' => $entity == null ? 0 : $entity->getCreated()->getTimestamp(),
      'blksize' => 0,
      'blocks' => 0,
      ];
  }

  public static function getType()
  {
    return static::WRITE_VISIBLE;
  }

  public function getName()
  {
    return t("Apigee SpecStore(Stream based)");
  }

  /**
   * Returns the description of the stream wrapper for use in the UI.
   *
   * @return string
   *   The stream wrapper description.
   */
  public function getDescription()
  {
    return t("StreamWrapper to read files in the Apigee SpecStore (Streaming)");
  }

  public function setUri($uri)
  {
    $this->uri = $uri;
  }

  public function getUri()
  {
    return $this->uri;
  }

  public function getExternalUrl()
  {
    $path = str_replace('\\', '/', $this->getTarget());
    return $this->url('system.private_file_download', ['filepath' => $path], ['absolute' => TRUE, 'path_processing' => FALSE]);
  }

  public function realpath()
  {
    return FALSE;
  }

  public function dirname($uri = NULL)
  {
    list($scheme, $target) = explode('://', $uri, 2);
    $structure = explode(DIRECTORY_SEPARATOR, $target);
    if(empty($structure)) {
      return FALSE;
    }
    array_pop($structure);
    return $scheme . '://' . implode(DIRECTORY_SEPARATOR, $structure);
  }

  private function getTarget($uri = null){
    if($uri == null) {
      $uri = $this->uri;
    }
    list($scheme, $target) = explode('://', $uri, 2);
    // Remove erroneous leading or trailing, forward-slashes and backslashes.
    return trim($target, '\/');
  }
}
