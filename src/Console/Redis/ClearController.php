<?php

namespace Core\Console\Redis;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class ClearController extends \Core\Console\CoreController
{
    CONST DESCRIPTION   = '';

    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;

    /**
     * @return
     */
    public function startAction()
    {
        $this->sm->get('Redis')->flushDb();
        $this->getLogger()->info('Redis cleared');  
    }
}
