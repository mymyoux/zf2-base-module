<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:23
 */

namespace Core\Table;


use Core\Model\EventModel;
use Core\Exception\ApiException;
use Zend\Db\Sql\Expression;

/**
 * Class EventTable
 * @package Core\Table
 */
class EventTable extends CoreTable
{

    const TABLE = "event";
   
    public function create($type, $data = NULL, $owner = NULL, $external = NULL, $notification = NULL)
	{
		$event = new EventModel();
        $event->setServiceLocator($this->sm);
		$event->type = $type;
		$event->notification_time = $notification;
		if(isset($external))
			$event->external()->associate($external);
		if(isset($owner))
			$event->owner()->associate($owner);
		if(isset($data))
		{
			$event->data = json_encode($data);
		}
		if(!isset($event->notification_time))
		{
			$event->notification_time = date('Y-m-d H:i:s', time());
		}
		return $event;
	}
    public function saveEvent($event)
    {
        if(!isset($event["id_event"]))
        {
            $this->table()->insert($event);
            return $this->table()->lastInsertValue;
        }else{

            $this->table()->update($event, ["id_event"=>$event["id_event"]]);
            return $event["id_event"];
        }
    }
}
