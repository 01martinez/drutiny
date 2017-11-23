<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;

/**
 * Check a purge plugin exists.
 */
class PurgePluginExists extends Audit {

  /**
   * @inheritDoc
   */
  public function audit(Sandbox $sandbox) {
    $plugin_name = $sandbox->getParameter('plugin');

    $config = $sandbox->drush([
      'format' => 'json',
      'include-overridden' => NULL,
      ])->configGet('purge.plugins');
    $plugins = $config['purgers'];

    foreach ($plugins as $plugin) {
      if ($plugin['plugin_id'] == $plugin_name) {
        return TRUE;
      }
    }

    return FALSE;
  }


}
