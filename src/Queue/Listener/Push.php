<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;


class Push extends ListenerAbstract implements ListenerInterface
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

 
    public function checkJob( $data )
    {
        return true;
    }

    public function executeJob( $data )
    {
        //normalement tous les ids sont de la même app
        $id_app_users = $data["ids"];
        if(empty($id_app_users))
        {
            //nothing to do
            return;
        }
        $app = $this->getAppTable()->getAppFromIDAppUser($id_app_users[0]);
        if(!isset($app))
        {
            throw new \Exception('app not found for id_app_user '.$id_app_users[0]);
        }
        //get app api_key
        $config = $this->sm->get("AppConfig");
        $gcm = $config->get("gcm");
        if(!isset($gcm) || !isset($gcm[$app->name]) || !isset($gcm[$app->name]["api_key"]))
        {
            throw new \Exception('you must have specified your gcm api key in config for app '.$app->name);
            return;
        }
        $api_key = $gcm[$app->name]["api_key"];

        $users = $this->sm->get("API")->user->method('GET')->user($this->user)->getPushRegistrations(["id_app_users"=>$data["ids"]])->value;

        if(empty($users))
        {
            $this->getLogger()->warn('no push token for '.implode(",",$id_app_users));
            return;
        }
        $headers = array
        (
            'Authorization: key=' . $api_key,
            'Content-Type: application/json'
        );

        $options = $data["options"];
        $options["data"] = $data["data"];


        foreach($users as $user)
        {
            $options["to"] = $user["registration_id"];
            $ch = curl_init();
            curl_setopt( $ch,\CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
            curl_setopt( $ch,\CURLOPT_POST, true );
            curl_setopt( $ch,\CURLOPT_HTTPHEADER, $headers );
            curl_setopt( $ch,\CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch,\CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch,\CURLOPT_POSTFIELDS, json_encode( $options ) );
            $result = curl_exec($ch );
            curl_close( $ch );
            $this->getLogger()->normal("result");
            echo $result."\n";
            $push = ["id_user_registration"=>$user["id_user_registration"],"id_app_user"=>$user["id_app_user"]];
            $push["content"] = json_encode($options);
            try
            {
                $result = json_decode($result, True);
                $push["multicast_id"] = $result["multicast_id"];
                if($result["failure"] == 1)
                {
                    //error
                    $push["error"] =  $result["results"][0]["error"];
                }else
                {
                    $push["message_id"] = $result["results"][0]["message_id"];
                }
            }catch(\Exception $e)
            {

            }
            $this->getPushTable()->savePush($push);
            
        }
        return $result;
    }

  
    /**
     * @return \Core\Table\PushTable
     */
    protected function getPushTable()
    {
        return $this->sm->get("PushTable");
    }
  
    /**
     * @return \Core\Table\AppTable
     */
    protected function getAppTable()
    {
        return $this->sm->get("AppTable");
    }
}
