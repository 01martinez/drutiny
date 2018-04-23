<?php

namespace Drutiny\Command;

use Drutiny\Docs\PolicyDocsGenerator;
use Drutiny\Policy;
use Fiasco\SymfonyConsoleStyleMarkdown\Renderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class PolicyInfoCommand extends Command {

  /**
   * @inheritdoc
   */
  protected function configure() {
    $this
      ->setName('policy:info')
      ->setDescription('Show information about a specific policy.')
      ->addArgument(
        'policy',
        InputArgument::REQUIRED,
        'The name of the check to run.'
      );
  }

  /**
   * @inheritdoc
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $docs = new PolicyDocsGenerator();
    $policy = Policy::load($input->getArgument('policy'));
    $markdown = $docs->buildPolicyDocumentation($policy);

    $formatted_output = Renderer::createFromMarkdown($markdown);
    $output->writeln((string) $formatted_output);
  }
}
