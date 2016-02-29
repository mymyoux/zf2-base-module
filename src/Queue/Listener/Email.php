<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

use Jlinn\Mandrill\Mandrill;
use Core\Struct\MandrillMessage;

class Email extends ListenerAbstract implements ListenerInterface
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

    protected function checkDebug()
    {
        if(!isset($this->debug))
        {
            $this->debug  = False;
            $configuration =  $this->sm->get("AppConfig")->getConfiguration();
            //not debug - not prod - not email
            if(!$this->sm->get("AppConfig")->isProduction())
            {
                if(!isset($configuration["local_notifications"]["email"]))
                {
                    $this->debug = True;
                }
            }
        }
    }

    private function createMandrill()
    {
        $mandrill_configuration = $this->sm->get("AppConfig")->get("mandrill");
        $this->checkDebug();

        $api_key = (true === $this->debug ? $mandrill_configuration["test_api_key"] : $mandrill_configuration["api_key"]);

        return new Mandrill( $api_key );
    }

    public function checkJob( $data )
    {
        return true;
    }

    public function executeJob( $data )
    {
        $template   = $data->template;
        $message    = $data->message;
        $async      = $data->async;

        $m_message  = new MandrillMessage();
        $m_message = $m_message->fromArray( toArray($message), true );

        $result     = $this->createMandrill()->messages()->sendTemplate($template, $m_message, [], $async);

        return array("data"=>$data,"result"=>$result,"message"=>$message);
    }

}
