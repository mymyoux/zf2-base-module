<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 13/11/14
 * Time: 16:18
 */

namespace Core\Model;

use Core\Traits\ServiceLocator;

class EventModel extends CoreModel 
{
	use ServiceLocator;

	public $id_event;
	public $external_id;
   	public $external_type;
   	public $type;
   	public $state;
   	public $data;
   	public $result;
   	public $owner_id;
   	public $owner_type;
   	public $notification_time;

   	public function save()
   	{
   		$keys = array("external_id", "external_type","type","state","data", "owner_id","owner_type","notification_time");
   		$data = [];
   		foreach($keys as $key)
   		{
   			if(isset($this->$key))
   			{
   				$data[$key] = $this->$key;
   			}
   		}
   		if(empty($data))
   		{
   			return;
   		}
        if(isset($data["data"]))
        {
            $data["data"] = json_encode($data["data"]);
        }
        if(isset($this->sm))
        {
   		    return $this->sm->get("EventTable")->saveEvent($data);
        }
        return null;
   	}
   	public function owner($owner)
   	{
           if(!defined(get_class($owner).'::LARAVEL'))
           {
            throw new \Exception('bad owner');
           }
            $this->owner_type = $owner::LARAVEL;
            $this->owner_id = $owner->id;
   	}
    public function external($external)
   	{
           if(!defined(get_class($external).'::LARAVEL'))
           {
            throw new \Exception('bad external');
           }
            $this->external_type = $external::LARAVEL;
            $this->external_id = $external->id;
   	}
   	public function done()
   	{
   		$this->state = "end";
   		$this->save();
   	}
}
