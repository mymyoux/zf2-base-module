<?php
namespace Core\Queue\Listener\Ask;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class No extends ListenerAbstract implements ListenerInterface
{
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
        $this->getNotifications()->noAsk($data["type"]);
    }
    protected function getNotifications()
    {
        return $this->sm->get("Notifications");
    }

}
