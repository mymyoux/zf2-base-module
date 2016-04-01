<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 06/10/2014
 * Time: 23:11
 */

namespace Core\Service\Api;


abstract class AbstractAPI implements IAPI
{
    /**
     * @var array Module's config api's part
     */
    protected $config;
    /**
     * @inheritDoc
     */
    public function canLogin()
    {
        return array_key_exists("login", $this->config) && $this->config["login"] === True;
    }
    /**
     * @inheritDoc
     */
    public function canMultiple()
    {
        return array_key_exists("multiple", $this->config) && $this->config["multiple"] === True;
    }
    /**
     * @inheritDoc
     */
    public function isSharable()
    {
        return array_key_exists("sharable", $this->config) && $this->config["sharable"] === True;
    }

    /**
     * @inheritDoc
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getAccessToken()
    {
        return NULL;
    }

    /**
     * @inheritDoc
     */
    public function logout()
    {

    }

    /**
     * @inheritDoc
     */
    public function getUserForDatabase()
    {
        $user = $this->getUser();
        $keys = $this->getDatabaseColumns();
        $sanitazed_user = array();
        foreach($keys as $key)
        {
            if(array_key_exists($key, $user))
            {
                $sanitazed_user[$key] = $user[$key];
            }
        }
        return $sanitazed_user;
    }

    /**
     * Gets database columns
     */
    protected function getDatabaseColumns()
    {
        throw new \Exception("This method need to be overridden");
    }

    public function isAts()
    {
        return false;
    }

    public function canRegister()
    {
        return true;
    }
}
