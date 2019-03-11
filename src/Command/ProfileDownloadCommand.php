<?php

namespace Drutiny\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Drutiny\Profile\ProfileSource;
use Drutiny\Profile;

/**
 *
 */
class ProfileDownloadCommand extends Command {

  /**
   * @inheritdoc
   */
  protected function configure() {
    $this
      ->setName('profile:download')
      ->setDescription('Download a remote profile locally.')
      ->addArgument(
        'profile',
        InputArgument::REQUIRED,
        'The name of the profile to download.'
      )
      ->addArgument(
        'source',
        InputArgument::OPTIONAL,
        'The source to download the profile from.'
      );
  }

  /**
   * @inheritdoc
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $render = new SymfonyStyle($input, $output);

    $profile = ProfileSource::loadProfileByName($name = $input->getArgument('profile'));

    $output = Yaml::dump($profile->dump());
    $filename = "$name.profile.yml";
    file_put_contents($filename, $output);
    $render->success("$filename written.");
  }

}
