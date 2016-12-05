<?php

namespace Core\Queue;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Core\Table\BeanstalkdLogTable;
use Pheanstalk\Job as PJob;

class Job implements ServiceLocatorAwareInterface {

    private $id_user;
    /**
     * identifier ID for deleting
     */
    private $identifier;
    private $job;
    /** @var string $tube */
    private $tube;
    /** @var Pheanstalk $beanstalkd */
    private $beanstalkd;
    /** @var string $ip */
    private $ip;
    /** @var integer $port */
    private $port;
    /** @var string $env */
    private $env;

    private $sm;

    /**
     * @param array $job
     * @param string $tube
     */
    public function __construct($tube, array $job, $id_user = NULL)
    {
        $this->job          = $job;
        $this->id_user          = $id_user;
        $this->job_json     = json_encode($job);
        $this->tube         = $tube;
    }
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }
    public function isConnected()
    {
        if (!$this->beanstalkd)
        {
            $config             = $this->sm->get('AppConfig')->get('beanstalkd');

            $this->ip           = $config['ip'];
            $this->port         = $config['port'];

            $this->beanstalkd   = new Pheanstalk($this->ip, $this->port);
        }

        return $this->beanstalkd->getConnection()->isServiceListening();
    }
    /**
     * Cancel previous jobs not executed yet
     * @return [type] [description]
     */
    public function cancelAllPrevious()
    {
        $previous = $this->sm->get('BeanstalkdLogTable')->getPrevious($this->tube, $this->id_user, $this->identifier);
        if(!empty($previous))
        {
            foreach($previous as $log)
            {
                if(isset($log["id_beanstalkd"]))
                {
                    $job = new PJob($log["id_beanstalkd"], json_decode($log["json"]));
                    try
                    {
                        $this->getBeanStalkd()->delete($job);
                    }catch(\Exception $e)
                    {
                        //beanstalkd reloaded ?
                    }
                    $this->sm->get('BeanstalkdLogTable')->setState($log["id"], BeanstalkdLogTable::STATE_CANCELLED);
                }
            }
        }

        return $this;
    }

    public function now()
    {
        return $this->send(PheanstalkInterface::DEFAULT_DELAY,  PheanstalkInterface::DEFAULT_PRIORITY, true);
    }

    protected function getBeanStalkd()
    {
        if (!$this->beanstalkd)
        {
            $config             = $this->sm->get('AppConfig')->get('beanstalkd');

            $this->ip           = $config['ip'];
            $this->port         = $config['port'];

            $this->beanstalkd   = new Pheanstalk($this->ip, $this->port);
        }
        return $this->beanstalkd;
    }

     public function throttle( $delay = PheanstalkInterface::DEFAULT_DELAY, $priority = PheanstalkInterface::DEFAULT_PRIORITY, $now = false )
     {
        $this->cancelAllPrevious();
        return $this->send($delay, $priority, $now);
     }

    /**
     * Sends a job onto the specified queue.
     *
     * @return int
     */
    public function send( $delay = PheanstalkInterface::DEFAULT_DELAY, $priority = PheanstalkInterface::DEFAULT_PRIORITY, $now = false )
    {



        $id = $this->sm->get('BeanstalkdLogTable')->insertLog( $this->job_json, $this->tube, $delay, $this->id_user, $priority, $this->identifier);

        $this->job['_id_beanstalkd'] = $id;

        if (!$this->getBeanStalkd()->getConnection()->isServiceListening() || true === $now)
        {
            if ($delay != PheanstalkInterface::DEFAULT_DELAY && php_sapi_name() === 'cli')
            {
                $this->sm->get('Log')->warn('waiting for ' . $delay . ' secs...');
                sleep( $delay );
            }
           $start_time = microtime(True);
            $classname = ucfirst(camel($this->tube, '-', '\\'));
            $this->sendAlert($now);
            $modules = $this->sm->get("ApplicationConfig")["modules"];
            $modules =  array_reverse($modules);
            foreach($modules as $module)
            {
                $object_name = '\\'.ucfirst($module).'\Queue\Listener\\' . $classname;
                if (false === class_exists($object_name))
                {
                    continue;
                }
                break;
            }
            if (false === class_exists($object_name))
            {
                $this->sm->get('Module')->lightLoad('Admin');
                $this->sm->get('Module')->fullLoad('Admin');
                $object_name = '\\Admin\Queue\Listener\\' . $classname;

                if (false === class_exists($object_name))
                    throw new \Exception('Class `' . $object_name . '` not exist', 1);
            }

            $listener = new $object_name;

            $user = isset($this->id_user)?$this->sm->get('UserTable')->getUser($this->id_user):NULL;
            $listener->setUser($user);
            $listener->setServiceLocator( $this->sm );
            $listener->preexecute( $this->job );

            $total_time = round((microtime(True) - $start_time)*1000);
            $this->sm->get('BeanstalkdLogTable')->setState($id, $now?BeanstalkdLogTable::STATE_EXECUTED_NOW:BeanstalkdLogTable::STATE_EXECUTED_FRONT, 1, $total_time);

            return true;
        }

        $id_beanstalkd = $this->beanstalkd->useTube($this->getTube())->put(json_encode($this->job), $priority, $delay);
        $this->sm->get('BeanstalkdLogTable')->setBeanstalkdID($id, $id_beanstalkd);

        return $id_beanstalkd;
    }

    private function sendAlert($now = false)
    {
        $count = $this->sm->get('BeanstalkdLogTable')->getCountLastError();

        if (0 === $count && $now === false)
        {
            $this->sm->get('Notifications')->sendNow();
            $this->sm->get('Notifications')->alert('beanstalkd');
        }
    }

    public function getTube()
    {
        return $this->getEnv() . '-' . $this->tube;
    }

    /**
     * Sets the tube name
     *
     * @param string $tube
     * @return $this
     */
    public function setTube($tube)
    {
        $this->tube = $tube;

        return $this;
    }

    /**
     * Return the number of job in each queues.
     *
     * @return array
     */
    public function getQueuesLoad()
    {
        $tubes = $this->beanstalkd->listTubes();

        $load = [];

        foreach ($tubes as $tube) {
            $stats = $this->beanstalkd->statsTube($tube);
            $load[] = [
                'name' => $tube,
                'load' => $stats['current-jobs-ready']
            ];
        }

        return $load;
    }

    /**
     * @return string
     */
    public function getEnv()
    {
        return $this->sm->get('AppConfig')->getEnv();
    }

    public function setServiceLocator(\Zend\ServiceManager\ServiceLocatorInterface $serviceLocator)
    {
        $this->sm = $serviceLocator;

        // set other with the app configuration
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->sm;
    }
}
