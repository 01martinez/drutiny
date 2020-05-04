<?php

namespace Drutiny\Console\Command;

use Drutiny\Docs\AuditDocsGenerator;
use Drutiny\Registry;
use Fiasco\SymfonyConsoleStyleMarkdown\Renderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 *
 */
class AuditInfoCommand extends Command {

  /**
   * @inheritdoc
   */
  protected function configure() {
    $this
      ->setName('audit:info')
      ->setDescription('Show all php audit classes available.')
      ->addArgument(
        'audit',
        InputArgument::REQUIRED,
        'The name of the audit class to display info about.'
      );
  }

  /**
   * @inheritdoc
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    $docs = new AuditDocsGenerator();
    $markdown = $docs->buildAuditDocumentation($input->getArgument('audit'));

    $formatted_output = Renderer::createFromMarkdown($markdown);
    $output->writeln((string) $formatted_output);
  }

}
