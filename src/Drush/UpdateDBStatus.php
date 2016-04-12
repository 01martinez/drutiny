<?php

namespace SiteAudit\Drush;

use SiteAudit\Base\Check;
use SiteAudit\AuditResponse\AuditResponse;

class UpdateDBStatus extends Check {
  public function check() {
    $response = new AuditResponse('system/updatedb', $this);

    $response->test(function ($check) {
      $context = $check->context;
      $output = $context->drush->updatedbStatus()->getOutput();
      if (count($output) === 1) {
        $output = reset($output);
        if (strpos($output, 'No database updates required') === 0) {
          return TRUE;
        }
      }

      return FALSE;
    });

    return $response;
  }
}
