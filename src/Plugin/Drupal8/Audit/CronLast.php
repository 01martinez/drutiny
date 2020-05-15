<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;

/**
 * Check a configuration is set correctly.
 */
class CronLast extends Audit {

  /**
   * @inheritDoc
   */
  public function audit(Sandbox $sandbox) {

      try {
        $last = $sandbox->drush([
          'format' => 'json'
          ])->stateGet('system.cron_last');
        $last = is_array($last) ? $last['system.cron_last'] : $last;
      }
      catch (DrushFormatException $e) {
        return FALSE;
      }

      if (empty($last)) {
        return FALSE;
      }

      $sandbox->setParameter('cron_last', date('l jS \of F Y h:i:s A', $last));

      $time_diff = time() - $last;
      // Fail if cron hasn't run in the last 24 hours.
      if ($time_diff > 86400) {
        return FALSE;
      }
      return TRUE;
  }

}
