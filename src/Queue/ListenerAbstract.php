<?php
namespace Core\Queue;

use Pheanstalk\Job as PheanstalkJob;
use Pheanstalk\Pheanstalk;

abstract class ListenerAbstract
{
    protected $sm;
    protected $api;
    protected $user;
    protected $tries;

    public function getRetry()
    {
        return $this->tries;
    }

    public function cooldown()
    {
        return 0;
    }
    public function setServiceLocator( $sm )
    {
        $this->sm = $sm;
        $this->api = $sm->get("API");
    }

    public function __call($method, $params)
    {
        $plugin = $this->plugin($method);
        if (is_callable($plugin)) {
            return call_user_func_array($plugin, $params);
        }

        return $plugin;
    }
    public function plugin($name, array $options = null)
    {
        return $this->getPluginManager()->get($name, $options);
    }
    protected function getPluginManager()
    {
        return $this->sm->get("ControllerPluginManager");
    }
    abstract public function executeJob( $data );
    public function setUser($user)
    {
        $this->user = $user;
    }
    public function preexecute($data)
    {
        return $this->executeJob($data);
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param  int $memoryLimit in MegaBytes
     * @return bool
     */
    public function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage() / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Stop listening and kill the script.
     * In the best of worlds, it would be
     * picked up and restarted by Supervisord.
     *
     * @return void
     */
    public function stop()
    {
        die;
    }
    public function getLogger()
    {
        return $this->sm->get("Log");
    }
}

