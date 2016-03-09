<?php

namespace SiteAudit\Command;

use SiteAudit\Base\CoreStatus;
use SiteAudit\Base\DrushCaller;
use SiteAudit\Base\Context;
use SiteAudit\Executor\Executor;
use SiteAudit\Executor\ExecutorRemote;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

class AcsfAudit extends SiteAudit {

  /**
   * @inheritdoc
   */
  protected function configure() {
    parent::configure();

    $this
      ->setName('audit:acsf')
      ->setDescription('Audit all sites on a ACSF instance');
  }

  /**
   * @inheritdoc
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $drush_alias = $input->getArgument('drush-alias');

    // Load the Drush alias which will contain more information we'll need.
    $executor = new Executor($output);
    $drush = new DrushCaller($executor);
    $response = $drush->siteAlias('@' . $drush_alias, '--format=json')->parseJson(TRUE);
    $alias = $response[$drush_alias];
    // $drush->setAlias($drush_alias);

    // We need to obtain the sites.json file from the ACSF server.
    $docroot_parts = explode('/', $alias['root']);
    array_pop($docroot_parts);
    $docroot_name = array_pop($docroot_parts);
    $acsf_sites_json = '/mnt/files/' . $docroot_name . '/files-private/sites.json';

    // Some checks don't use drush and connect to the server
    // directly so we need a remote executor available as well.
    if (isset($alias['remote-host'], $alias['remote-user'])) {
      $executor = new ExecutorRemote($output);
      $executor->setRemoteUser($alias['remote-user'])
               ->setRemoteHost($alias['remote-host']);
      if (isset($alias['ssh-options'])) {
        $executor->setArgument($alias['ssh-options']);
      }
      if ($input->getOption('ssh_options')) {
        $executor->setArgument($input->getOption('ssh_options'));
      }
    }

    $json_data = $executor->execute('cat ' . $acsf_sites_json)->parseJson(TRUE);
    $sites = $json_data['sites'];

    $profile = $this->loadProfile($input->getOption('profile'));

    // Ensure we can bootstrap the drush alias, and get the required properties
    // from the alias. Store these in an object to use in the checks to avoid
    // duplication.
    // $core_status = new CoreStatus($drush_alias, $input, $output);

    $site_names = [];

    foreach ($sites as $domain => $site) {

      // The parsed json can contain multiple entries.
      if (in_array($site['name'], $site_names)) {
        continue;
      }
      $site_names[] = $site['name'];

      $drush = new DrushCaller($executor);
      $drush->setArgument('--uri=' . $domain)
            ->setArgument('--root=' . $alias['root']);

      $context = new Context();
      $context->set('input', $input)
              ->set('output', $output)
              ->set('executor', $executor)
              ->set('profile', $profile)
              ->set('remoteExecutor', $executor)
              ->set('drush', $drush);

      $output->writeln('<comment>Running audit over: ' . $domain . '</comment>');
      $results = $this->runChecks($context);
      $pass = 0;
      $failures = [];
      foreach ($results as $result) {
        if ($result->hasPassed()) {
          $pass++;
        }
        else {
          $failures[] = $result;
        }
      }
      $output->writeln('<info>' . $pass . '/' . count($results) . ' tests passed.</info>');
      foreach ($failures as $fail) {
        $context->output->writeln((string) $fail);
      }
    }
  }

  protected function runChecks($context) {
    $results = [];
    foreach ($context->profile['checks'] as $check => $options) {
      $test = new $check($context, $options);
      $results[] = $test->check();
      // $context->output->writeln((string) $test->check());
    }
    return $results;
  }

}
