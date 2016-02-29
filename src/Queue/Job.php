<?php

namespace Core\Queue;

use Pheanstalk\Pheanstalk;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

class Job implements ServiceLocatorAwareInterface {

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
    public function __construct($tube, array $job)
    {
        $this->job          = $job;
        $this->job_json     = json_encode($job);
        $this->tube         = $tube;
        $this->ip           = '127.0.0.1';
        $this->port         = 11300;

        $this->beanstalkd   = new Pheanstalk($this->ip, $this->port);
    }

    /**
     * Sends a job onto the specified queue.
     *
     * @return int
     */
    public function send()
    {
        $id = $this->sm->get('BeanstalkdLogTable')->insertLog( $this->job_json );

        $this->job['_id_beanstalkd'] = $id;

        if (!$this->beanstalkd->getConnection()->isServiceListening())
        {
            $object_name = '\Core\Queue\Listener\\' . ucfirst($this->tube);

            if (false === class_exists($object_name))
            {
                throw new \Exception('Class `' . $object_name . '` not exist', 1);
            }

            $listener = new $object_name;

            $listener->setServiceLocator( $this->sm );
            $listener->executeJob( json_decode($this->job) );

            $this->sm->get('BeanstalkdLogTable')->setSend($id, true);

            return true;
        }

        return $this->beanstalkd->useTube($this->getTube())->put(json_encode($this->job));
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
