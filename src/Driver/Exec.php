<?php

namespace Drutiny\Driver;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter as Cache;
use Symfony\Component\Cache\CacheItem;
use Drutiny\Container;

/**
 *
 */
class Exec {

  /**
   * @inheritdoc
   */
  public function exec($command, $args = []) {
    $args['%docroot'] = '';
    $command = strtr($command, $args);
    $watchdog = Container::getLogger();

    $cache = new Cache('exec', 0, Container::CACHE_DIRECTORY);
    $cache->setLogger($watchdog);
    $cid = hash('md5', $command);
    $item = $cache->getItem($cid);

    if ($output = $item->get()) {
      $watchdog->debug("Cache hit for: $command");
      return $output;
    }

    $process = new Process($command);
    $process->setTimeout(600);

    $watchdog->info($command);
    $process->run();

    // Executes after the command finishes.
    if (!$process->isSuccessful()) {
      throw new ProcessFailedException($process);
    }

    $output = $process->getOutput();

    $watchdog->debug($output);
    $item->set($output)->expiresAt(new \DateTime('+1 hour'));
    $cache->save($item);

    return $output;
  }

}
