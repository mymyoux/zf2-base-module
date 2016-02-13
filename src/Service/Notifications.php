<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 23/10/14
 * Time: 10:52
 */

namespace Core\Service;


use Zend\ServiceManager\ServiceLocatorAwareInterface;


/**
 * Email Helper
 * Class Email
 * @package Core\Service
 */
class Notifications extends CoreService implements ServiceLocatorAwareInterface
{
    private $slack_url;
    protected function getSlackURL()
    {
        if(!isset($this->slack_url))
        {
            $config = $this->sm->get("AppConfig")->getConfiguration();
            if(isset($config["slack"]["token"]) && isset($config["slack"]["name"]))
            {
                $this->slack_url = "https://".$config["slack"]["name"].".slack.com/services/hooks/slackbot?token=".$config["slack"]["token"];
            }
        }
        return $this->slack_url;
    }

    public function sendError($info)
    {
        if($info["message"] == "You are not allowed to be on this page")
        {
            //ignore
            return;
        }
        return;
        $channel = "test_yb";

        $message = ":coffee:\t ".(isset($info["user"])?$info["user"]->first_name." ".$info["user"]->last_name." ":"")." ".($info["id_user"]!=0?'('.$info["id_user"].')':'(no id)');
        $message .= "\n".$info["message"];

        $message .= "\n".$info["url"];
        $file = $info["file"];

        $index = mb_strpos($file, "module/");
        if($index!==False)
        {
            $file = mb_substr($info["file"], $index+7);
        }
        $message .= "\n".$file.":".$info["line"]."\n";
        return $this->sendNotification($channel, $message);
    }

    public function sendNotification($channel, $message)
    {

            $url = $this->getSlackURL();
            if(!isset($url))
            {
                //no slack configuration
                return;
            }
            //local redirection
            $config = $this->sm->get("AppConfig")->getConfiguration(); 

            if(isset($config["local_notifications"]["slack_channel"]))
            {
                $channel = $config["local_notifications"]["slack_channel"];
            }else
            {
                if(!$this->sm->get("AppConfig")->isProduction())
                {
                    $channel = "test_yb";
                }   
            }

            $url .= "&channel=%23".$channel;
            $data  = $message;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
    }
}
