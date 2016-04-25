<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 15:16
 */
namespace Core\Table;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Core\Exception\ApiException;
/***
 * Login Table
 * Class TokenTable
 * @package Core\Table
 */
class TokenTable extends CoreTable
{
    const TABLE_ONE_SHOT = "user_one_token";
    const TABLE_ONE_SHOT_HISTORY = "user_one_token_history";
    const TOKEN_LIFETIME = 8467200000;//14*7*24*3600*1000; //2 weeks
    /**
     * Tests if a token given by a user is still valid
     * @param $id_user
     * @param $token
     * @param $device_token
     * @return bool
     */
    public function isTokenValid($id_user, $token, $device_token)
    {
        $rows = $this->table()->select(
            array(
                "id_user"=>$id_user,
                "token" => $token,
                "device_token" => $device_token
            )
        );
        $row = $rows->current();
        if(!$row)
        {
            return False;
        }
        return True;
    }
    public function isOneTokenValid($token)
    {
        $rows = $this->table(TokenTable::TABLE_ONE_SHOT)->select(
            array(
                "token" => $token
            )
        );
        $row = $rows->current();
        if(!$row)
        {
            return False;
        }
        return True;
    }
    public function useOnetoken($user, $apirequest)
    {

        $where = $this->delete()
        ->where
         ->nest
            ->lessThan("expired_time", new Expression('NOW()'))
            ->and
            ->isNotNull("expired_time")
        ->unnest
        ->or
        ->nest
            ->lessThanOrEqualTo("count", 0)
            ->and
            ->isNotNull("count")
        ->unnest;
        
        $request = $this->delete(TokenTable::TABLE_ONE_SHOT)->where($where);
        $this->execute($request);

        try
        {
            $this->startTransaction();
            $token = $apirequest->params->token->value;
            
            $result = $this->table(TokenTable::TABLE_ONE_SHOT)->select(array("token"=>$token));
            $result = $result->current();
            //no token
            if($result === False)
            {
                return NULL;
            }

            if(isset($result["count"]) && is_numeric($result["count"]))
            {
                $result["count"]--;
                $this->table(TokenTable::TABLE_ONE_SHOT)->update(array("count"=>$result["count"]), array("token"=>$token));
            }
            $this->table(TokenTable::TABLE_ONE_SHOT_HISTORY)->insert(array("id_user"=>$result["id_user"],"token"=>$result["token"],"source"=>$result["source"]));
             $this->endTransaction();
             $this->getNotifications()->oneToken($result);
            return (int)$result["id_user"];
        }catch(\Exception $e)
        {
            $this->rollbackTransaction( true );
        }
        return NULL;


    }
    public function createOneShot($user, $apirequest)
    {
        $token = generate_token(100);
        $count = $apirequest->params->count->value;
        $id_user = (int)$apirequest->params->id_user->value;
        $source = mb_substr($apirequest->params->source->value,0,255);


        $tokenuser = $this->getUserTable()->getUser($id_user);
        if(!isset($tokenuser))
        {
            throw new ApiException("No user ".$id_user);
        }
        if($tokenuser->type == "admin")
        {
            throw new ApiException("Can't create token for admin user");
        }
        $expires_in = $apirequest->params->expires_in->value;
        if(!isset($count) && !isset($expires_in))
        {
            throw new ApiException('count or expires_in are required');
        }   
       
       $request = $this->insert(TokenTable::TABLE_ONE_SHOT)
        ->values(array(
            "id_user"=>$id_user,
            "source"=>$source,
            "token"=>$token,"count"=>$count, 
            "expired_time"=>(isset($expires_in)?new Expression("NOW() + INTERVAL ? SECOND", $expires_in):NULL)
            ));


        $this->execute($request);

        return $token;
    } 
    public function removeToken($device_token, $token = NULL)
    {
        //TODO: remove token
        $where = array();
        if(isset($device_token))
        {
            $where["device_token"] = $device_token;
        }
        if(isset($token))
        {
            $where["token"] = $token;
        }
        if(!empty($where))
            $this->table()->delete($where);
    }
    /**
     * Generate a token for the user
     * @return string|Null Token generated or NULL if there is no id_user
     */
    public function generateUserToken()
    {
        if(!isset($this->session->id_user))
        {
            return FALSE;
        }
        $this->session->token = generate_token();
        $this->table()->delete(
            array(
                "id_user" => $this->session->id_user,
                "device_token" => $this->session->device_token
            )
        );
        $this->table()->insert(
            array(
                "id_user"=>$this->session->id_user,
                "token" => $this->session->token,
                "device_token" => $this->session->device_token,
                "expire" => timestamp() + TokenTable::TOKEN_LIFETIME
             )
        );

        return $this->session->token;
    }
    public function getUserTable()
    {
        return $this->sm->get("UserTable");
    }
    public function getNotifications()
    {
        return $this->sm->get("Notifications");
    }
    /**
     * Clean all expired tokens
     */
    public function cleanExpiredToken()
    {
        $count = $this->table()->count();

        $this->table()->delete(
            array(
                "expire <= ? " => timestamp()
            )
        );

        $count2 = $this->table()->count();

        return $count - $count2;
    }
} 
