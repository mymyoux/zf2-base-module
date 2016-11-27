<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 13/11/14
 * Time: 16:18
 */

namespace Core\Model;


class PushModel extends CoreModel{

    const PRIORITY_NORMAL = "normal";
    const PRIORITY_HIGH = "high";
    const MAX_TTL = 2419200; //28 days
    const DEFAULT_TTL = 2419200; //28 days
    private $data;
    private $ids;
    private $priority;
    private $background;
    private $collapseKey;
    private $ttl;
    private $title;
    private $message;
    private $_test = false;
    private $sm;
    public function __construct($sm)
    {
        $this->sm = $sm;
        $this->data = [];
        $this->ids = [];
        $this->priority = PushModel::PRIORITY_NORMAL;
        $this->ttl = PushModel::DEFAULT_TTL;
    }   
    /**
     * Add a recipient id
     */
    public function addRecipient($id)
    {
        if(is_array($id))
        {
            $id = $id["id_app_user"];
        }elseif(!is_numeric($id))
        {
            $id = $id->id_app_user;
        }
        $this->ids[] = $id;
        return $this;
    }
    /**
     * App will receive notification in realtime. can wake up phone
     */
    public function realtime($realtime = True)
    {
        $this->priority = $realtime?PushModel::PRIORITY_HIGH:PushModel::PRIORITY_NORMAL;
        return $this;
    }
    /**
     * App will receive notification even in background mode
     */
    public function receiveInBackground($background = True)
    {
        $this->background = $background;
        return $this;
    }
    /**
     * Will collapse all messages with same key
     */
    public function collapse($key)
    {
        $this->collapseKey = $key;
        return $this;
    }
    /**
     * Time to live
     */
    public function ttl($seconds)
    {
        $this->ttl = $seconds;
        if($this->ttl<0)
        {
            $this->ttl = 0;
        }
        if($this->ttl>PushModel::MAX_TTL)
        {
            $this->ttl = PushModel::MAX_TTL;
        }
        return $this;
    }
    /**
     * set title
     */
    public function title($value)
    {
        $this->title = $value;
        return $this;
    }
    /**
     * set message
     */
    public function message($value)
    {
        $this->message = $value;
        return $this;
    }
    public function setData($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }
    public function send()
    {
        $data = $this->toArray();
        $job = $this->sm->get('QueueService')->createJob("push", $data);
        $job->send();
    }
    public function test()
    {
        $this->_test = True;
        return $this;
    }
    public function toArray()
    {
        if(empty($this->ids))
        {
            throw new \Exception('you must specify at least one registration id to send a push');
        }
        $data = $this->data;
        $options = [];
        $options["priority"] = $this->priority;
        if(isset($this->background))
        {
            $options["content-available"] = 1;
        }
        if(isset($this->collapseKey) && strlen($this->collapseKey))
        {
            $options["collapse_key"] = $this->collapseKey;
        }
        if(isset($this->ttl))
        {
            $options["time_to_live"] = $this->ttl;
        }
        if(isset($this->title))
        {
            $data["title"] = $this->title;
        }
        if(isset($this->message))
        {
            $data["message"] = $this->message;
        }
        if(isset($this->_test))
        {
            $data["test_notification"] = "TEST";
        }
        return ["ids"=>$this->ids, "data"=>$data, "options"=>$options];

    }
}
