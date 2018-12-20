<?php

namespace Drupal\flysystem_gcs\Tests;

use Drupal\flysystem\Tests\ModuleInstallUninstallWebTest as Base;

/**
 * Tests module installation and uninstallation.
 *
 * @group flysystem_s3
 */
class ModuleInstallUninstallWebTest extends Base {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['flysystem_gcs'];

}
