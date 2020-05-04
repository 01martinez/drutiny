<?php

namespace Drutiny\Http\Middleware;

use Drutiny\Http\MiddlewareInterface;
use Drutiny\ImmuntableConfig;
use Psr\Http\Message\RequestInterface;

class UserAgent implements MiddlewareInterface {

  protected $config;

  /**
   * @param $config @config service.
   */
  public function __construct(ImmuntableConfig $config) {
    $this->config = $config->getConfig('http');
  }

  /**
   * {@inheritdoc}
   */
  public function handle(RequestInterface $request) {
    return isset($this->config->user_agent) ? $request->withHeader('User-Agent', $this->config->user_agent) : $request;
  }
}
