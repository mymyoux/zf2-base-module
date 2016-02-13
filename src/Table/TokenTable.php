<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 15:16
 */
namespace Core\Table;
use Zend\Db\Sql\Select;

/***
 * Login Table
 * Class TokenTable
 * @package Core\Table
 */
class TokenTable extends CoreTable
{
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