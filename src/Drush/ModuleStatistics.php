<?php

namespace SiteAudit\Drush;

use SiteAudit\Base\Check;
use SiteAudit\Base\AuditResponse;

class ModuleStatistics extends Check {
  public function check() {
    $enabled = $this->context->drush->getModuleStatus('statistics');

    $response = new AuditResponse();
    $response->setDescription('The statistics module tracks page views and logs access statistics for your site. Because it is triggered on every page load it can slow sites down. Consider using a client-side analytics solution (such as Google Analytics) instead.');
    $response->setRemediation("Disable Statistics on Drupal's module administration page");
    if (!$enabled) {
      $response->setSuccess('Statistics module is disabled');
    }
    else {
      $response->setFailure('Statistics module is enabled');
    }
    return $response;
  }
}
