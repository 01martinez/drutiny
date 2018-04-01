<?php

namespace Drutiny;

use Symfony\Component\ClassLoader\ClassMapGenerator;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 *
 */
class Registry {

  protected static $registry;

  public static function add($namespace, $data)
  {
    self::$registry[$namespace] = $data;
  }

  public static function get($namespace)
  {
    return isset(self::$registry[$namespace]) ? self::$registry[$namespace] : [];
  }

  /**
   *
   */
  public function targets() {
    $targets = [];
    foreach ($this->config()->Target as $class) {
      $info = $this->loadClassInfo($class, '\Drutiny\Target\Target');
      $info->class = $class;
      $targets[$info->name] = $info;
    }
    return $targets;
  }

  public function getTargetClass($name) {
    $targets = $this->targets();
    if (!isset($targets[$name])) {
      throw new \InvalidArgumentException("Cannot find a registered target with the name: $name.");
    }
    return $targets[$name]->class;
  }

  public static function getConfig()
  {
    if ($config = self::get('drutiny.config')) {
      return $config;
    }
    $finder = new Finder();
    $finder->files()
      ->in('.')
      ->name('drutiny.config.yml');

    $config = [];
    foreach ($finder as $file) {
      $conf = Yaml::parseFile($file->getPathname());

      // Templates are in filepaths which need to be translated into absolute filepaths.
      if (isset($conf['Template'])) {
        foreach ($conf['Template'] as &$directory) {
          $directory = dirname($file->getRealPath()) . '/' . $directory;
        }
      }

      $config[] = $conf;
    }
    $config = call_user_func_array('array_merge_recursive', $config);
    self::add('drutiny.config', (object) $config);
    return self::get('drutiny.config');
  }

  protected function config() {
    static $config;

    if (!empty($config)) {
      return (object) $config;
    }

    $finder = new Finder();
    $finder->files()
      ->in('.')
      ->name('drutiny.config.yml');

    $config = [];
    foreach ($finder as $file) {
      $conf = Yaml::parse(file_get_contents($file->getRealPath()));

      // Templates are in filepaths which need to be translated into absolute filepaths.
      if (isset($conf['Template'])) {
        foreach ($conf['Template'] as &$directory) {
          $directory = dirname($file->getRealPath()) . '/' . $directory;
        }
      }
      $config[] = $conf;
    }
    $config = call_user_func_array('array_merge_recursive', $config);
    return (object) $config;
  }



  protected function loadClassInfo($class, $type) {
    $reflect = new \ReflectionClass($class);
    $reader = new AnnotationReader();
    if ($reflect->isAbstract()) {
      throw new \InvalidArgumentException("$class: Annotations are not supported on abstract classes.");
    }
    if (!$reflect->isSubClassOf($type)) {
      throw new \InvalidArgumentException("$class is not of type $type.");
    }
    $info = $reader->getClassAnnotations($reflect);
    $info = empty($info) ? new \stdClass : $info[0];

    $info->class = $class;
    return $info;
  }

  public function getAuditMedtadata($class) {
    $reflect = new \ReflectionClass($class);
    $reader = new AnnotationReader();
    if (!$reflect->isSubClassOf('\Drutiny\Audit')) {
      throw new \InvalidArgumentException("$class is not of type \Drutiny\Audit.");
    }
    $annotations = $reader->getClassAnnotations($reflect);
    $comment = explode(PHP_EOL, $reflect->getDocComment());

    $info = new \StdClass;
    $info->description = '';

    if (isset($comment[1])) {
      $desc = trim(substr(trim($comment[1]), 1));
      if (!empty($desc) && (strpos($desc, '@') !== 0)) {
        $info->description = $desc;
      }
    }

    $info->filename = $reflect->getFilename();
    $info->isAbstract = $reflect->isAbstract();
    $info->remediable = $reflect->implementsInterface('Drutiny\RemediableInterface');
    $info->namespace = $reflect->getNamespaceName();
    $info->extends = $reflect->getParentClass()->getName();
    $info->params = [];
    $info->tokens = [];
    $info->class = $class;
    $info->reflect = $reflect;

    foreach ($annotations as $annotation) {
      if ($annotation instanceof \Drutiny\Annotation\Token) {
        $info->tokens[$annotation->name] = $annotation;
      }
      if ($annotation instanceof \Drutiny\Annotation\Param) {
        $info->params[$annotation->name] = $annotation;
      }
    }
    return $info;
  }

  /**
   *
   */
  public function policies() {
    static $registry;

    if ($registry) {
      return $registry;
    }

    $finder = new Finder();
    $finder->files()
      ->in('.')
      ->name('*.policy.yml');

    $registry = [];
    foreach ($finder as $file) {
      $policy = Yaml::parse(file_get_contents($file->getRealPath()));
      $policy['filepath'] = $file->getRealPath();
      $registry[$policy['name']] = new Policy($policy);
    }
    return $registry;
  }

  public function getPolicy($name)
  {
    $r = $this->policies();
    if (!isset($r[$name])) {
      throw new \InvalidArgumentException("No such policy exists: $name");
    }
    return $r[$name];
  }

  /**
   *
   */
  public function commands() {
    $commands = [];
    foreach ($this->config()->Command as $class) {
      $info = $this->loadClassInfo($class, '\Symfony\Component\Console\Command\Command');
      $commands[] = $info->class;
    }
    return $commands;
  }

  public function templateDirs() {
    return array_filter($this->config()->Template, 'file_exists');
  }

  public function credentials()
  {
    $config = $this->config();
    $schema = isset($config->CredentialSchema) ? $config->CredentialSchema : [];
    return $schema;
  }

}
