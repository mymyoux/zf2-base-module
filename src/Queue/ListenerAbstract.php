<?php
namespace Core\Queue;

use Pheanstalk\Job as PheanstalkJob;
use Pheanstalk\Pheanstalk;

abstract class ListenerAbstract
{
    protected $sm;

    public function setServiceLocator( $sm )
    {
        $this->sm = $sm;
    }

    protected function buryJob(PheanstalkJob $job, Pheanstalk $queue)
    {
        /** @var \OG_Model_Queue_FailedJobs $modelFailedJobs */
        $modelFailedJobs = \OG_Core_Base::getModel('Queue/FailedJobs');
        try {
            $modelFailedJobs->insert([
                'queue_id'   => $job->getId(),
                'queue'      => implode(', ', $queue->listTubesWatched()),
                'connection' => $queue->getConnection()->getHost() . ':' . $queue->getConnection()->getPort(),
                'payload'    => $job->getData()
            ]);
        } catch (Exception $e) {
            echo "Error: " . $e->getmessage();
        }

        $queue->bury($job);
    }
    abstract public function executeJob( $data );
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
}

