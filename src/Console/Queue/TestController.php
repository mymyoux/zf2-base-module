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
        $this->sm->get('Email')->setMergeLanguage('handlebars');
         $this->sm->get("Email")->sendEmailTemplate(['inbox', 'message', 'new'], 'new-message-on-yborder', 'benjamin.andreosso@gmail.com', 'inmail@yborder.com', null, null, [
            'from_name'         => 'test',
            'to_name'           => 'test',
            'comment'           => 'test',
            'sender_hash'       => 'test',
            'id_conversation'   => 'test',
        ]);
        $admin          = $this->sm->get('UserTable')->getConsoleUser( 'company' );
        $this->sm->get('Notifications')->alert('build_cv', 'test');
        $this->sm->get('Notifications')->leadUser( $admin );
    }
}
