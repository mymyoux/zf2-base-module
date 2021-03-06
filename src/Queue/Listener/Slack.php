<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class Slack extends ListenerAbstract implements ListenerInterface
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
    public function cooldown()
    {
        /*if ($this->sm->get('AppConfig')->isCLI())
            return 1;*/
        return 0;
    }
    public function executeJob( $data )
    {
        $cooldown = 1;
        $json   = json_encode($data);


        $used_slack = $this->sm->get('Notifications')->send( $json, NULL, $this->sm->get('AppConfig')->isCLI() );
        
    }

}
