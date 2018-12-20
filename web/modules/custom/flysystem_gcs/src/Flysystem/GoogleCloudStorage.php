<?php

namespace Drupal\flysystem_gcs\Flysystem;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\flysystem\Plugin\FlysystemPluginInterface;
use Drupal\flysystem\Plugin\FlysystemUrlTrait;
use Drupal\flysystem\Plugin\ImageStyleGenerationTrait;
use Drupal\flysystem_gcs\Flysystem\Adapter\DrupalGoogleStorageAdapter;
use Google\Cloud\Storage\StorageClient;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal plugin for the "Google Cloud Storage" Flysystem adapter.
 *
 * @Adapter(id = "gcs")
 */
class GoogleCloudStorage implements FlysystemPluginInterface, ContainerFactoryPluginInterface {
  use FlysystemUrlTrait {getExternalUrl as getDownloadlUrl;
  }

  use ImageStyleGenerationTrait;

  private $bucket;

  /* @vars StorageClient*/
  private $storageClient;

  private $config;

  /* @vars GoogleStorageAdapter */
  private $adapter;

  function __construct(StorageClient $storageClient, Config $config)
  {
    $this->storageClient = $storageClient;
    $this->bucket = $storageClient->bucket($config->get("bucket"));
    $this->config = $config;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(new StorageClient($configuration['client_config']), new Config($configuration));
  }

  /**
   * Returns the Flysystem adapter.
   *
   * Plugins should not keep references to the adapter. If a plugin needs to
   * perform filesystem operations, it should either use a scheme:// or have the
   * \Drupal\flysystem\FlysystemFactory injected.
   *
   * @return \League\Flysystem\AdapterInterface
   *   The Flysytem adapter.
   */
  public function getAdapter()
  {
    if(!isset($this->adapter)) {
      $this->adapter = new DrupalGoogleStorageAdapter($this->storageClient, $this->bucket);
      $this->adapter->setPublic($this->config->get('public', FALSE));
    }
    return $this->adapter;
  }

  /**
   * Returns a web accessible URL for the resource.
   *
   * This function should return a URL that can be embedded in a web page
   * and accessed from a browser. For example, the external URL of
   * "youtube://xIpLd0WQKCY" might be
   * "http://www.youtube.com/watch?v=xIpLd0WQKCY".
   *
   * @param string $uri
   *   The URI to provide a URL for.
   *
   * @return string
   *   Returns a string containing a web accessible URL for the resource.
   */
  public function getExternalUrl($uri)
  {
    if ($this->isPublic === FALSE) {
      return $this->getDownloadlUrl($uri);
    }


    $target = $this->getTarget($uri);

    if (strpos($target, 'styles/') === 0 && !file_exists($uri)) {
      $this->generateImageStyle($target);
    }

    return $this->adapter->getUrl($target);
  }

  /**
   * Checks the sanity of the filesystem.
   *
   * If this is a local filesystem, .htaccess file should be in place.
   *
   * @return array
   *   A list of error messages.
   */
  public function ensure($force = FALSE)
  {
    $bucket = $this->storageClient->bucket($this->config->get("bucket"));

    if (!$bucket->exists()) {
      return [[
        'severity' => RfcLogLevel::ERROR,
        'message' => 'Google Cloud Storage bucket %bucket does not exist.',
        'context' => [
          '%bucket' => $this->config->get("bucket"),
        ],
      ],
      ];
    }

    return [];
  }
}
