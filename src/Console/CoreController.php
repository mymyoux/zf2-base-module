<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 19:57
 */

namespace Core\Console;

class CoreController extends \Core\Controller\CoreController
{
    public function init(\Zend\Mvc\MvcEvent $event)
    {
        parent::init($event);
        $this->sm->get("Route")->setServiceLocator($this->sm);
    }
    public function getLogger()
    {
        return $this->sm->get('Log');
    }
}
