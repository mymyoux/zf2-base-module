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

        $provider = isset($data["channel"]) && in_array($data["channel"],["#errors","#test_yb","#marketplace_anonyme"])?'rocket':NULL;

        $used_slack = $this->sm->get('Notifications')->send( $json, $provider );
        if(isset($provider))
        {
            $cooldown = 0;
        }
        if($cooldown && $this->sm->get('AppConfig')->isCLI())
        {
            $this->getLogger()->warn("cooldown ".$cooldown."s");
            sleep($cooldown);
        }
    }

}
