<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class Ask extends ListenerAbstract implements ListenerInterface
{

    protected $queueName;
    protected $tries;
    private $queue;

    /**
     * @param int $tries
     */
    public function __construct($tries = 3)
    {
    }

    public function checkJob( $data )
    {
        return true;
    }

    public function executeJob( $data )
    {
        $ask = $this->getAskTable()->getAskByID($data["id_ask"]);
        if(!isset($ask))
        {
            return;
        }
        if($ask["answer"] === "reject")
        {
            //ignore this ask
            return;
        }
        $name = $ask["type"];
        $modules = $this->sm->get("ApplicationConfig")["modules"];
        $modules = array_reverse($modules);
        $classname = ucfirst(camel(camel($name,"_")));
        foreach($modules as $module)
        {
            $object_name = '\\'.ucfirst($module).'\Queue\Listener\Ask\\' . $classname;
            if (false === class_exists($object_name))
            {
                continue;
            }
            break;
        }
        if (false === class_exists($object_name))
        {
            $object_name = '\Core\Queue\Listener\Ask\No';
            $listener = new $object_name;

            $listener->setServiceLocator( $this->sm );
            $listener->executeJob($ask);
            return;
        }

        $listener = new $object_name;

        $listener->setServiceLocator( $this->sm );
        $listener->executeJob($ask);

    }
    protected function getAskTable()
    {
        return $this->sm->get("AskTable");
    }

}
