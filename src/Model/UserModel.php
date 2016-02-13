<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 17:45
 */

namespace Core\Model;


class UserModel extends CoreModel
{
    public $id;
    public $first_name;
    public $last_name;
    /**
     *  Login token
     * @var string
     */
    public $token;
    /**
     * @var string
     */
    public $type;
    public $last_connection;
    public $num_connection;
    public $email;
    public function toString()
    {

    }

    /**
     * Check if the user's id is a Database id
     * @return bool
     */
    public function hasID()
    {
        return isset($this->id) && $this->id!=0;
    }
    public function isAdmin()
    {
        return False;
    }
} 
