<?php

namespace Drutiny\Sandbox;

use Drutiny\Target\Target;
use Drutiny\AuditInterface;
use Drutiny\Audit;
use Drutiny\AuditValidationException;
use Drutiny\RemediableInterface;
use Drutiny\AuditResponse\AuditResponse;
use Drutiny\Policy;
use Drutiny\Cache;
use Symfony\Component\Yaml\Yaml;

/**
 * Run check in an isolated environment.
 */
class Sandbox {

  use DrushDriverTrait;
  use ExecTrait;
  use ParameterTrait;
  use LoggerTrait;
  use RegistryTrait;
  use ReportingPeriodTrait;

  /**
   * @var \Drutiny\Target\Target
   */
  protected $target;

  /**
   * @var \Drutiny\Audit
   */
  protected $audit;

  /**
   * @var \Drutiny\Policy
   */
  protected $policy;

  /**
   * Create a new Sandbox.
   *
   * @param string $target
   *   The class name of the target to create.
   * @param Policy $policy
   *
   * @throws \Exception
   */
  public function __construct(Target $target, Policy $policy) {
    $this->target = $target->setSandbox($this);

    $class = $policy->get('class');
    $audit = new $class($this);
    if (!$audit instanceof AuditInterface) {
      throw new \InvalidArgumentException("$class is not a valid Audit class.");
    }
    $this->audit = $audit;
    $this->policy = $policy;

    // Default reporting period is last 24 hours to the nearest hour.
    $start = new \DateTime(date('ha'));
    $end   = clone $start;
    $end->add(new \DateInterval('PT24H'));
    $this->setReportingPeriod($start, $end);
  }

  /**
   * Run the check and capture the outcomes.
   */
  public function run() {
    $response = new AuditResponse($this->getPolicy());

    $this->logger()->info('Auditing ' . $this->getPolicy()->get('name'));
    try {
      // Run the audit over the policy.
      $outcome = $this->getAuditor()->execute($this);

      // Log the parameters output.
      $this->logger()->info("Tokens:\n" . Yaml::dump($this->getParameterTokens(), 4));

      // Set the response.
      $response->set($outcome, $this->getParameterTokens());
    }
    catch (AuditValidationException $e) {
      $this->setParameter('exception', $e->getMessage());
      $this->logger()->warning($e->getMessage());
      $response->set(Audit::NOT_APPLICABLE, $this->getParameterTokens());
    }
    catch (\Exception $e) {
      $this->setParameter('exception', $e->getMessage() . PHP_EOL . $e->getTraceAsString());
      $response->set(Audit::ERROR, $this->getParameterTokens());
    }

    return $response;
  }

  /**
   * Remediate the check if available.
   */
  public function remediate() {
    $response = new AuditResponse($this->getPolicy());
    try {

      // Do not attempt remediation on checks that don't support it.
      if (!($this->getAuditor() instanceof RemediableInterface)) {
        throw new \Exception(get_class($this->getAuditor()) . ' is not remediable.');
      }

      // Make sure remediation does report false positives due to caching.
      Cache::purge();
      $outcome = $this->getAuditor()->remediate($this);
      $response->set($outcome, $this->getParameterTokens());
    }
    catch (\Exception $e) {
      $this->setParameter('exception', $e->getMessage());
      $response->set(Audit::ERROR, $this->getParameterTokens());
    }

    return $response;
  }

  /**
   *
   */
  public function getAuditor() {
    return $this->audit;
  }

  /**
   *
   */
  public function getPolicy() {
    return $this->policy;
  }

  /**
   *
   */
  public function getTarget() {
    return $this->target;
  }

  /**
   * A wrapper function for traits to use.
   */
  public function sandbox() {
    return $this;
  }

}
