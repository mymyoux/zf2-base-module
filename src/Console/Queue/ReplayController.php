<?php

namespace Core\Console\Queue;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;


class ReplayController extends \Core\Console\CoreController
{
    CONST DESCRIPTION   = 'Replay an action';

    /**
     * @var \Zend\ServiceManager\ServiceManager
     */
    public $sm;

    /**
     * @return
     */
    public function startAction()
    {
        $id = $this->params()->fromRoute('id');
        if(!isset($id))
        {
            $this->getLogger()->error('No id given - you must pass it id=xxxx');
            exit();
        }
        $result = $this->getBeanstalkdLogTable()->findById($id);

         if(!isset($result))
        {
            $this->getLogger()->error('No record with id '.$id);
            exit();
        }
        if(!isset($result["queue"]))
        {
            $queue = $this->params()->fromRoute('queue');
            if(!isset($queue))
            {
                $this->getLogger()->error('This record has no queue registered you must pass it - queue=xxxx');
                exit();
            }
            $result["queue"] = $queue;
        }
        $modules = $this->sm->get("ApplicationConfig")["modules"];
        $modules = array_reverse($modules);
        foreach($modules as $module)
        {
            $object_name = '\\'.ucfirst($module).'\Queue\Listener\\' . ucfirst($result["queue"]);
            if (false === class_exists($object_name))
            {
                continue;
            }
            break;
        }
        if (false === class_exists($object_name))
        {
            $this->getLogger()->error('Should not happen: Class `' . $object_name . '` not exist');
        }



        $listener = new $object_name;

        $listener->setServiceLocator( $this->sm );
        $this->getLogger()->normal("replay job: ".$id);
        $listener->executeJob(json_decode($result["json"], True));
    }
    /**
     * @return \Core\Table\BeanstalkdLogTable
     */
    public function getBeanstalkdLogTable()
   {
        return $this->sm->get("BeanstalkdLogTable");
   } 
   

}
