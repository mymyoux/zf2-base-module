<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 23/10/14
 * Time: 10:52
 */

namespace Core\Service;

use Core\Model\PushModel;



use Zend\ServiceManager\ServiceLocatorAwareInterface;

/**
 * Push Helper
 * Class Email
 * @package Core\Service
 */
class Push extends CoreService implements ServiceLocatorAwareInterface{
  public function createPush()
  {
    $config = $this->sm->get("AppConfig");
        $gcm = $config->get("gcm");
        if(!isset($gcm))
        {
            throw new \Exception('you must have specified your gcm api key in config');
            return;
        }
    return new PushModel($gcm["api_key"], $this->sm);
  }
}
