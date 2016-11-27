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
    return new PushModel($this->sm);
  }
}
