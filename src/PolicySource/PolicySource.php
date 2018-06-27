<?php

namespace Drutiny\PolicySource;

use Drutiny\Config;
use Drutiny\Cache;

class PolicySource {
  public static function loadPolicyByName($name)
  {
    $list = self::getPolicyList();
    $definition = $list[$name];
    return self::getSource($definition['source'])->load($definition);
  }

  /**
   * Acquire a list of available policies.
   *
   * @return array of policy information arrays.
   */
  public static function getPolicyList()
  {
    $lists = array_map(function ($source) {
      return array_map(function ($item) use ($source) {
        $item['source'] = $source->getName();
        return $item;
      },
      $source->getList());
    },
    self::getSources());

    $list = array_filter(call_user_func_array('array_merge', $lists), function ($listedPolicy) {
      return class_exists($listedPolicy['class']);
    });
    return $list;
  }

  /**
   * Load all policies as loaded Policy objects.
   */
  public static function loadAll()
  {
    $list = [];
    foreach (self::getPolicyList() as $definition) {
      $list[$definition['name']] = self::loadPolicyByName($definition);
    }
    return $list;
  }

  /**
   * Load the sources that provide policies.
   *
   * @return array of PolicySourceInterface objects.
   */
  public static function getSources()
  {
    if ($sources = Cache::get(__CLASS__, 'sources')) {
      return $sources;
    }

    // The PolicySource config directive loads in class names that provides
    // policies for Drutiny to use. We need to validate each provided source
    // implements PolicySourceInterface.
    $sources = array_filter(array_map(function ($class) {
      $object = new $class();
      if (!($object instanceof PolicySourceInterface)) {
        return false;
      }
      return $object;
    }, Config::get('PolicySource')));

    // If multiple sources provide the same policy by name, then the policy from
    // the first source in the list will by used.
    usort($sources, function ($a, $b) {
      if ($a->getWeight() == $b->getWeight()) {
        return 0;
      }
      return $a->getWeight() > $b->getWeight() ? 1 : -1;
    });

    Cache::get(__CLASS__, 'sources', $sources);
    return $sources;
  }

  /**
   * Load a single source.
   */
  public static function getSource($name)
  {
    foreach (self::getSources() as $source) {
      if ($source->getName() == $name) {
        return $source;
      }
    }
    throw new Exception("PolicySource not found: $name.");
  }
}
?>
