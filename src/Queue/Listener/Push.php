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
       
        $msg = array
        (
           /* 'message'   => 'here is a message. message',
            'title'     => 'This is a title. title',*/
            'subtitle'  => 'This is a subtitle. subtitle',
            'tickerText'    => 'Ticker text here...Ticker text here...Ticker text here',
            'vibrate'   => 1,
            'foreground'=>true
        );
        $options = $data["options"];
        $options["registration_ids"] = $data["ids"];
        $options["data"] = $data["data"];
       
         
        $headers = array
        (
            'Authorization: key=' . $data["api_key"],
            'Content-Type: application/json'
        ); 
        var_dump($options);
         
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
        echo $result;
        return $result;
    }

  
    /**
     * @return \Core\Table\PushTable
     */
    protected function getPushTable()
    {
        return $this->sm->get("PushTable");
    }
}
