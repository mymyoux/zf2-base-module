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
    private $slack;
    private $rocket;
    private $client;
    protected $send_now = false;

    public function init()
    {
        $config = $this->getServiceLocator()->get("AppConfig")->get('slack');
        if(isset($config))
            $this->slack = $config;

      $this->rocket = $this->getServiceLocator()->get("AppConfig")->get('rocket');
    }

    public function sendNow()
    {
        $this->send_now = true;
    }
    public function sendErrorSlack($info)
    {

    }
    public function ask($type, $value, $id_external)
    {
        $icon = ':question:';
        $text = "$icon\t ASK *$type* " . (isset($id_external) ? "($id_external)" : '') . ": \n$value";

        return $this->sendNotification("ask", $text."\n");
    }
    public function noAsk($type)
    {
        $channel = "alert";
        $message = ":cold_sweat: Ask type:".$type." has no class handler\n";
        return $this->sendNotification($channel, $message);
    }
    public function oneToken($token)
    {
        $channel = "alert";
        $message = ":dark_sunglasses: Connexion by token - source: ".$token["source"]."\n";
        $user = $this->sm->get("UserTable")->getUser($token["id_user"]);
        if(isset($user))
        {
            $message.= "*".$user->first_name." ".$user->last_name."*";
            if($user->isCompanyEmployee())
            {
                $message.= " of ".$user->getCompany()->name."\n";
            }
        }else
        {
            $message.= "id_user: ".$token["id_user"];
        }
        return $this->sendNotification($channel, $message);
    }
    public function sendError($info)
    {
         if(in_array($info["message"], ["You are not allowed to be on this page", "[API Exception] not_allowed"]))
        {
            //ignore
            return;
        }
        return null;
        $user = NULL;
        if($this->sm->get("Identity")->isLoggued())
        {
            $user = $this->sm->get("Identity")->user;
        }

        $channel = "error";

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

    public function send( $json, $provider = NULL, $handletimeout = False )
    {
        if(isset($this->slack) && (!isset($provider) || $provider == "slack"))
        {
            $slacks = $this->slack["accounts"];
            $rawjson = json_decode($json);
            $channel = $rawjson->channel;
            if(starts_with($channel, "#"))
            {
                $channel = substr($channel, 1);
            }
            
            $mapping = $this->slack["channels"];
            $filter = NULL;
            if(isset($mapping) && isset($mapping[$channel]))
            {
                $filter = $mapping[$channel];
                if(is_string($filter))
                {
                    $filter = [$filter];
                }
            }   
            foreach($slacks as $name=>$slack)
            {

                if(isset($filter) && !in_array($name, $filter))
                {
                    continue;
                }
                if($handletimeout)
                {
                    $time = $this->sm->get("Redis")->get('slack_'.$name);
                    if(!isset($time))
                    {
                        $this->getLogger()->warn("no time");
                        $time = round(microtime(True)*1000)-1001;
                    }
                    $now = round(microtime(True)*1000);
                    $this->getLogger()->warn("since last [".$name."]:".($now-$time));
                    if($now-$time<1000)
                    {
                        $this->getLogger()->warn("cooldown [".$name."]".(1000-($now-$time))."ms");
                        usleep(((1000-($now-$time))*1000));
                    }
                    $this->sm->get("Redis")->set('slack_'.$name, round(microtime(True)*1000));
                }
                $time = round(microtime(True)*1000);
                $url    = $slack['url'];
                $ch = curl_init( $url );
              //  $json = json_encode($json);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json))
                );

                $result = curl_exec($ch);
                curl_close($ch);
                $now = round(microtime(True)*1000);
                $this->getLogger()->info('time:'.($now-$time));
            }


            // $ch = curl_init( $this->slack_url );

            // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            // curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            //     'Content-Type: application/json',
            //     'Content-Length: ' . strlen($json)
            //     )
            // );

            // $result = curl_exec($ch);
            // curl_close($ch);
        }

        // if(isset($this->rocket) && (!isset($provider) || $provider == "rocket"))
        // {
        //     try
        //     {
        //         $headers = array(
        //             'Content-Type: application/json',
        //             'Content-Length: ' . strlen($json),
        //         );
        //         foreach($this->rocket['headers'] as $key=>$header)
        //         {
        //             $headers[] = $key.': '.$header;
        //         }
        //         $ch = curl_init( $this->rocket["url"] );

        //         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        //         curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //         curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        //         $result = curl_exec($ch);
        //         curl_close($ch);
                    
        //     }catch(\Exception $e)
        //     {

        //     }
        // }

        return $result;
    }

    public function sendToBeanstalkd( $data )
    {
        $job = $this->sm->get('QueueService')->createJob('slack', $data);

        $job->send();
    }
    public function getLogger()
    {
        return $this->sm->get('Log');
    }
}
