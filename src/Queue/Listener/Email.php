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
    private $debug;

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
        if (!is_object($data))
            $data = json_decode(json_encode($data));

        $template   = $data->template;
        $message    = $data->message;
        $async      = $data->async;

        $this->debug = $data->debug;

        $m_message  = new MandrillMessage();
        $m_message = $m_message->fromArray( toArray($message), true );

        $ids = $this->log($data->type, $data->user, $template, $data->original_emails, $data->sender, $data->subject);

        if (null === $template)
        {
            $result = $this->createMandrill()->messages()->send($m_message, $async);
        }
        else
        {
            if ($template === 'raw-content')
            {
                $m_message->html = $message->global_merge_vars[0]->content;
                $result          = $this->createMandrill()->messages()->send($m_message, $async);
            }
            else
            {
                $result = $this->createMandrill()->messages()->sendTemplate($template, $m_message, [], $async);
            }
        }

        foreach($result as $key=>$resultemail)
        {
            if(sizeof($ids)>$key)
            {
                $this->getMailTable()->updateMail($ids[$key], array("id_mandrill"=>$resultemail["_id"],
                    "reason"=>isset($resultemail["reject_reason"])?$resultemail["reject_reason"]:NULL,
                    "status"=>$resultemail["status"]));
            }
        }

        return array("data"=>$data,"result"=>$result,"message"=>$message);
    }

    /**
     * Logs email to the database
     * @param string $type Email's type
     * @param \Core\Model\UserModel $recipient Recipient
     * @param string $html Email's content
     * @param string $emails Email of recipient(s)
     * @param string $sender Email of sender
     * @param string $subject Email's subject
     */
    protected function log($type, $recipient, $html, array $emails, $sender, $subject = NULL)
    {
        // do not insert if it's in debug mode
        if (true === $this->debug) return false;

        if(!isset($subject))
        {
            $subject = "by_default";
        }
        if (true === is_array($type)) $type = implode('-', $type);

        $ids = [];
        foreach ($emails as $email)
            $ids[] = $this->getMailTable()->logEmail($type, $recipient, $html, $email, $sender, $subject);
        return $ids;
    }

    /**
     * @return \Core\Table\MailTable
     */
    protected function getMailTable()
    {
        return $this->sm->get("MailTable");
    }
}
