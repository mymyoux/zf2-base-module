<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class Slack extends ListenerAbstract implements ListenerInterface
{

    protected $queueName;
    private $tries;
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
    public function cooldown()
    {
        if ($this->sm->get('AppConfig')->isCLI())
            return 1;
        return 0;
    }
    public function executeJob( $data )
    {
        $json   = json_encode($data);
        $result = $this->sm->get('Notifications')->send( $json );
    }

}
