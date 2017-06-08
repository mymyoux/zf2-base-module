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
    /**
	 * traitment on going
	 */
	const STATE_PENDING = "pending";
	/**
	 * failed
	 */
	const STATE_FAILED = "failed";
	/**
	 * done
	 */
	const STATE_DONE = "done";
	const STATE_POSTPONED = "postpone";
	/**
	 * Have to be traited
	 */
	const STATE_CREATED = "created";
	use ServiceLocator;

	public $id_event;
	public $external_id;
   	public $external_type;
   	public $type;
   	public $state;
   	public $data;
   	public $result;
   	public $step;
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
      	return $this->id_event = $this->sm->get("EventTable")->saveEvent($data);
   	}
    
    public function answer($result, $state = NULL, $postpone_time = NULL, $id_user = NULL)
	{
		if($state === NULL)
		{
			$state =  isset($postpone_time)?static::STATE_POSTPONED:static::STATE_DONE;
		}
		if($state == static::STATE_POSTPONED || $postpone_time === True)
		{
			if(!isset($postpone_time) || $postpone_time === True)
			{
				$postpone_time = date('Y-m-d H:i:s', time());
			}
		}
		if(!isset($id_user))
		{
			$id_user = $this->sm->get("Identity")->isLoggued()? $this->sm->get("Identity")->user->id:NULL;
		}
		if($id_user === False)
		{
			$id_user = NULL;
		}
		$data = ["id_event"=>$this->id_event, "step"=>$this->step,"result" => json_encode($result), "id_user"=>$id_user,"state"=>$state,"notification_time"=>$postpone_time];
		$this->table('event_action')->insert($data);
		foreach($data as $key=>$value)
		{
			if($key == "id_user")
			{
				continue;
			}	
			$this->$key = $value;
		}
		if(isset($data["notification_time"]))
		{
			$this->state = static::STATE_POSTPONED;
		}
		unset($data["id_user"]);
		
        $this->table()->update($data, ["id_event"=>$this->id_event]);
		$this->sm->get("APIL")->path('event/handle')->param("id_event",$this->id_event)->send();
		//$handler = $this->type;
		//$handler::handle($this);
	}
	public function table($name = NULL)
	{
		return $this->sm->get("EventTable")->table($name);
	}
	public function done($result = NULL, $id_user = NULL)
	{
		return $this->answer($result, static::STATE_DONE, NULL, $id_user);
	}
	public function fail($result = NULL, $id_user = NULL)
	{
		return $this->answer($result, static::STATE_FAILED, NULL, $id_user);
	}
    public function nextStep($step, $result, $state = NULL, $postpone_time = NULL, $id_user = NULL)
	{
		$this->step = $step;
		if($state === NULL)
		{
			$state =  isset($postpone_time)?static::STATE_POSTPONED:static::STATE_PENDING;
		}
		return $this->answer($result, $state, $postpone_time, $id_user);
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
}
