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

        $ids = explode(",", $id);
        $original_id = $id;

        $rebuild_ids = array();
        foreach($ids as $id)
        {
            if(($index = strpos($id, "-")) !== False)
            {
                $subids = explode("-", $id);
                if(count($subids) != 2)
                {
                    throw new \Exception("Bad format:".$original_id);
                }
                if(!is_numeric($subids[0]) || !is_numeric($subids[1]))
                {
                    throw new \Exception("Bad format:".$original_id);   
                }
                $min = intval(trim($subids[0]));
                $max = intval(trim($subids[1]));
                if($min>$max)
                {
                    $tmp = $min;
                    $min = $max;
                    $max = $tmp;
                }
                for($i=$min; $i<=$max; $i++)
                {
                    $rebuild_ids[] = $i;
                }
            }
            elseif(($index = strpos($id, "+")) !== False)
            {
                $id = str_replace("+", "", $id);
                if(!is_numeric($id))
                {
                    throw new \Exception("Bad format:".$original_id);   
                }
                $moreids = $this->getBeanstalkdLogTable()->getIdsGreaterThanOrEqual(intval(trim($id)));
                $rebuild_ids = array_merge($rebuild_ids, $moreids);
            }
            else
            {
                if(is_numeric($id))
                {
                    $rebuild_ids[] = intval(trim($id));
                }else
                {
                   throw new \Exception("Bad format:".$original_id);   
                }
            }
        }
        $rebuild_ids = array_unique($rebuild_ids);
        $failed = [];
        foreach($rebuild_ids as $id)
        {
            try
            {
                $result = $this->getBeanstalkdLogTable()->findById($id);

                 if(!isset($result))
                {
                    $failed[] = $id;
                    $this->getLogger()->error('No record with id '.$id);
                    continue;
                }
                if(!isset($result["queue"]))
                {
                    $queue = $this->params()->fromRoute('queue');
                    if(!isset($queue))
                    {
                        $this->getLogger()->error('This record['.$id.'] has no queue registered you must pass it - queue=xxxx');
                        $failed[] = $id;
                        continue;
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
                $listener->preexecute(json_decode($result["json"], True));
            }catch(\Exception $e)
            {
                $failed[] = $id;
                $this->getLogger()->error($e->getMessage());
            }
        }
        foreach($rebuild_ids as $id)
        {
            if(in_array($id, $failed))
            {
                $this->getLogger()->error($id." failed");
            }else
            {
                $this->getLogger()->info($id." success");
            }
        }
    }
    /**
     * @return \Core\Table\BeanstalkdLogTable
     */
    public function getBeanstalkdLogTable()
   {
        return $this->sm->get("BeanstalkdLogTable");
   } 
   

}
