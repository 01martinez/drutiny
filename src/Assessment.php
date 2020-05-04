<?php

namespace Drutiny;

use Drutiny\AuditResponse\AuditResponse;
use Drutiny\AuditResponse\NoAuditResponseFoundException;
use Drutiny\Target\TargetInterface;
use Drutiny\Sandbox\Sandbox;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Assessment
{

  /**
   * @var string URI
   */
    protected $uri;
    protected $results = [];
    protected $successful = true;
    protected $severityCode = 1;
    protected $logger;
    protected $container;

    public function __construct(ConsoleLogger $logger, ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->container = $container;
    }

    public function setUri($uri = 'default')
    {
        $this->uri = $uri;
        return $this;
    }

  /**
   * Assess a Target.
   *
   * @param TargetInterface $target
   * @param array $policies each item should be a Drutiny\Policy object.
   * @param DateTime $start The start date of the reporting period. Defaults to -1 day.
   * @param DateTime $end The end date of the reporting period. Defaults to now.
   * @param bool $remediate If an Drutiny\Audit supports remediation and the policy failes, remediate the policy. Defaults to FALSE.
   */
    public function assessTarget(TargetInterface $target, array $policies, \DateTime $start = null, \DateTime $end = null, $remediate = false)
    {
        $start = $start ?: new \DateTime('-1 day');
        $end   = $end ?: new \DateTime();

        $policies = array_filter($policies, function ($policy) {
            return $policy instanceof Policy;
        });

        $is_progress_bar = $this->logger instanceof ProgressBar;

        foreach ($policies as $policy) {
            if ($is_progress_bar) {
                $this->logger->setTopic($this->uri . '][' . $policy->get('title'));
            }

            $this->logger->info("Assessing policy...");

          // Setup the sandbox to run the assessment.
            $sandbox = $this->container
            ->get('sandbox')
            ->create($target, $policy)
            ->setReportingPeriod($start, $end);

            $response = $sandbox->run();

          // Omit irrelevant AuditResponses.
            if (!$response->isIrrelevant()) {
                $this->setPolicyResult($response);
            }

          // Attempt remediation.
            if ($remediate && !$response->isSuccessful()) {
                $this->logger->info("\xE2\x9A\xA0 Remediating " . $policy->get('title'));
                $this->setPolicyResult($sandbox->remediate());
            }

            if ($is_progress_bar) {
                $this->logger->advance();
            }
        }

        return $this;
    }

  /**
   * Set the result of a Policy.
   *
   * The result of a Policy is unique to an assessment result set.
   *
   * @param AuditResponse $response
   */
    public function setPolicyResult(AuditResponse $response)
    {
        $this->results[$response->getPolicy()->getProperty('name')] = $response;

      // Set the overall success state of the Assessment. Considered
      // a success if all policies pass.
        $this->successful = $this->successful && $response->isSuccessful();

      // If the policy failed its assessment and the severity of the Policy
      // is higher than the current severity of the assessment, then increase
      // the severity of the overall assessment.
        $severity = $response->getPolicy()->getSeverity();
        if (!$response->isSuccessful() && ($this->severityCode < $severity)) {
            $this->severityCode = $severity;
        }
    }

    public function getSeverityCode():int
    {
        return $this->severityCode;
    }

  /**
   * Get the overall outcome of the assessment.
   */
    public function isSuccessful()
    {
        return $this->successful;
    }

  /**
   * Get an AuditResponse object by Policy name.
   *
   * @param string $name
   * @return AuditResponse
   */
    public function getPolicyResult($name)
    {
        if (!isset($this->results[$name])) {
            throw new NoAuditResponseFoundException($name, "Policy '$name' does not have an AuditResponse.");
        }
        return $this->results[$name];
    }

  /**
   * Get the results array of AuditResponse objects.
   *
   * @return array of AuditResponse objects.
   */
    public function getResults()
    {
        return $this->results;
    }

  /**
   * Get the uri of Assessment object.
   *
   * @return string uri.
   */
    public function uri()
    {
        return $this->uri;
    }
}
