<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:23
 */

namespace Core\Table;
use Core\Exception\ApiException;
use Core\Model\UserModel;
use Core\Annotations as ghost;

class PushTable extends CoreTable
{
    const TABLE = "push";
    const TABLE_USER = "app_user_registration";

    /**
     * @ghost\Param(name="id", required=True)
     * @ghost\Param(name="uuid", required=False)
     */
    public function pushRegistration($user, $apirequest)
    {
        $uuid = $apirequest->params->uuid->value;
        if(!isset($uuid))
        {
            $uuid="php:uuid";
        }

        $registation = $this->table(PushTable::TABLE_USER)->selectOne(["id_app_user"=>$user->id_app_user,"registration_id"=>$apirequest->params->id->value]);
        if(!isset($registation))
        {
            $this->table(PushTable::TABLE_USER)->insert(["id_app_user"=>$user->id_app_user, "registration_id"=>$apirequest->params->id->value,"uuid"=>$uuid]);
        }else
        {
            $this->table(PushTable::TABLE_USER)->update(["valid"=>1,"uuid"=>$uuid],["id_app_user"=>$user->id_app_user, "registration_id"=>$apirequest->params->id->value]);//reset to valid state
        }
    }
    /**
     * @ghost\Param(name="uuid", required=False)
     */
    public function invalidPushRegistration($user, $apirequest)
    {
         $this->table(PushTable::TABLE_USER)->update(["valid"=>0],["id_app_user"=>$user->id_app_user, "uuid"=>$apirequest->params->uuid->value]);
    }
    /**
     * @ghost\Param(name="id_app_users", array=true, required=true,requirements="\d+")
     */
    public function getPushRegistrations($user, $apirequest)
    {
        $where = $this->select()->where->in("id_app_user", $apirequest->params->id_app_users->value)
        ->and->equalTo("valid", 1);
        $request = $this->select(["push"=>PushTable::TABLE_USER])->where($where);
        $result = $this->execute($request);
        return $result->toArray();
    }
    public function savePush($push)
    {
    	if(isset($push["error"]) && isset($push["id_user_registration"]))
    	{
    		$this->table(PushTable::TABLE_USER)->update(["valid"=>0],["id_user_registration"=>$push["id_user_registration"]]);
    	}
    	$this->table(PushTable::TABLE)->insert($push);
    }
} 
