<?php

namespace Core\Console\Queue;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class TestController extends \Core\Console\CoreController
{
    CONST DESCRIPTION   = 'Queue system management';

    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;

    /**
     * @return
     */
    public function startAction()
    {

    }
}
