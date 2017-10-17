<?php

namespace Drutiny\Plugin\Drupal8\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;

/**
 *  Cron last run.
 */
class CronHasRun extends Audit {

  /**
   *
   */
  public function audit(Sandbox $sandbox) {

    try {
      $timestamp = $sandbox->drush(['format' => 'json'])->stateGet('system.cron_last');
    }
    catch (DrushFormatException $e) {
      return FALSE;
    }

    // Check that cron was run in the last day.
    $since = time() - $timestamp;
    $sandbox->setParameter('cron_last', date('Y-m-d H:i:s', $timestamp));

    if ($since > $sandbox->getParameter('cron_max_interval')) {
      return FALSE;
    }

    return TRUE;
  }

}
