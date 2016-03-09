<?php

namespace SiteAudit\Drush;

use SiteAudit\Base\Check;
use SiteAudit\Base\AuditResponse;

class ModulePHP extends Check {
  public function check() {
    $enabled = $this->context->drush->getModuleStatus('php');

    $response = new AuditResponse();
    $response->setDescription('The PHP filter is enabled for your website. While this does not normally represent a serious concern, it does represent a security vulnerability, in that it can allow bad PHP code to be added to your site. This bad code can cause blank pages to appear instead of your site content.');
    $response->setRemediation("Disable PHP Filter on Drupal's module administration page");
    if (!$enabled) {
      $response->setSuccess('PHP Filter module is disabled');
    }
    else {
      $response->setFailure('PHP Filter module is enabled');
    }
    return $response;
  }
}
