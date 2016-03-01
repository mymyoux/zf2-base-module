<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 23/10/14
 * Time: 10:52
 */

namespace Core\Service;


use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Core\Model\Slack\SlackModel;

/**
 * Email Helper
 * Class Email
 * @package Core\Service
 */
class Notifications extends CoreService implements ServiceLocatorAwareInterface
{
    private $slack_url;
    private $client;
    protected $send_now = false;

    public function init()
    {
        $config = $this->getServiceLocator()->get("AppConfig")->get('slack');

        $this->slack_url = $config['url'];
    }

    public function sendNow()
    {
        $this->send_now = true;
    }

    public function sendError($info)
    {
        // if($info["message"] == "You are not allowed to be on this page")
        // {
        //     //ignore
        //     return;
        // }
        // return;
        // $channel = "test_yb";

        // $message = ":coffee:\t ".(isset($info["user"])?$info["user"]->first_name." ".$info["user"]->last_name." ":"")." ".($info["id_user"]!=0?'('.$info["id_user"].')':'(no id)');
        // $message .= "\n".$info["message"];

        // $message .= "\n".$info["url"];
        // $file = $info["file"];

        // $index = mb_strpos($file, "module/");
        // if($index!==False)
        // {
        //     $file = mb_substr($info["file"], $index+7);
        // }
        // $message .= "\n".$file.":".$info["line"]."\n";
        // return $this->sendNotification($channel, $message);
    }
    public function sendSlack( $slack )
    {
        if(!$slack->isValid())
        {
            dd("no valid");
            return;
        }
        $data = $slack->toSlackArray();

        return $this->sendToBeanstalkd($data);
    }

    public function sendNotification($channel, $message, $attachments = [], $bot_name = null, $icon = null)
    {
        $data = array(
            'channel'     => (mb_strpos($channel, '#') === false ? '#' : '') . $channel,
            'username'    => $bot_name,
            'text'        => $message,
            'icon_emoji'  => $icon,
            'attachments' => $attachments
        );

        if (true === $this->send_now)
        {
            $this->send_now = false;

            return $this->send( json_encode($data) );
        }

        return $this->sendToBeanstalkd($data);
    }

    public function send( $json )
    {
        $ch = curl_init( $this->slack_url );

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json))
        );

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function sendToBeanstalkd( $data )
    {
        $job = $this->sm->get('QueueService')->createJob('slack', $data);

        $job->send();
    }
}
