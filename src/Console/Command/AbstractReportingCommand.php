<?php

namespace Drutiny\Console\Command;

use Drutiny\Assessment;
use Drutiny\Profile\ProfileSource;
use Drutiny\Profile;
use Drutiny\Profile\PolicyDefinition;
use Drutiny\Report;
use Drutiny\Sandbox\Sandbox;
use Drutiny\DomainSource;
use Drutiny\Target\Target;
use Drutiny\ProgressBar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *
 */
abstract class AbstractReportingCommand extends Command {


  /**
   * @inheritdoc
   */
  protected function configure() {
    $this
      ->addOption(
        'format',
        'f',
        InputOption::VALUE_OPTIONAL,
        'Specify which output format to render the report (console, html, json). Defaults to console.',
        'console'
      )
      ->addOption(
        'title',
        't',
        InputOption::VALUE_OPTIONAL,
        'Override the title of the profile with the specified value.',
        false
      )
      ->addOption(
        'report-filename',
        'o',
        InputOption::VALUE_OPTIONAL,
        'For json and html formats, use this option to write report to file. Drutiny will automate a filepath if the option is omitted. Use "stdout" to print to terminal',
        false
      )
      ->addOption(
        'report-per-site',
        null,
        InputOption::VALUE_NONE,
        'Flag to additionally render a report for each site audited in multisite mode.'
      );
  }

  /**
   * Write up the report.
   */
  protected function report(
    Profile $profile,
    InputInterface $input,
    OutputInterface $output,
    Target $target,
    Array $results
  ) {

    $console = new SymfonyStyle($input, $output);
    $filepath = $input->getOption('report-filename');
    $format = $input->getOption('format');

    // If format is not out to console and the filepath isn't set, automate
    // what the filepath should be.
    if (!in_array($format, ['console', 'terminal']) && !$filepath) {
      $filepath = strtr('target-profile-date.format', [
        'target' => preg_replace('/[^a-z0-9]/', '', strtolower($input->getArgument('target'))),
        'profile' => $profile->getName(),
        'date' => date('Ymd-His'),
        'format' => $format,
      ]);
    }
    // If the filepath is not set for console formats, then force to stdout.
    elseif (in_array($format, ['console', 'terminal']) && !$filepath) {
      $filepath = 'stdout';
    }

    // Setup the reporting format.
    $format = $this->getApplication()
      ->getKernel()
      ->getContainer()
      ->get('format.factory')
      ->create($format, $profile->getFormatOptions($format));

    $report = $format->render($profile, $target, $results)
                     ->fetch();

    if ($filepath == 'stdout') {
      $output->write($report, TRUE);
    }
    else {
      file_put_contents($filepath, $report);
      $console->success('Report written to ' . $filepath);

      // Additionally write a report per site if the profile required it.
      if ($profile->reportPerSite()) {
        $info = pathinfo($filepath);
        foreach ($results as $uri => $result) {
          $info['uri'] = $uri;
          $site_report_filepath = strtr('dirname/filename/uri.extension', $info);
          if (!is_dir(dirname($site_report_filepath)) && !mkdir(dirname($site_report_filepath))) {
            continue;
          }
          $report = $format->render($profile, $target, [$result])->fetch();
          file_put_contents($site_report_filepath, $report);
          $console->success('Report written to ' . $site_report_filepath);
        }
      }
    }
  }
}
