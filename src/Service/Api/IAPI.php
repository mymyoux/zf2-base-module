<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 03/10/2014
 * Time: 15:41
 */

namespace Core\Service\Api;


interface IAPI {
    public function typeAuthorize();
    /**
     * Specifies if the api be used to log in
     * @return bool
     */
    public function canLogin();
    /**
     * Specifies if the api can accept multiple accounts linked to a single user
     * @return bool
     */
    public function canMultiple();
    /**
     * Specifies if two users can connect the same account
     * @return bool
     */
    public function isSharable();
    /**
     * Sets the api config
     * @param $config array Module's config api's part
     */
    public function setConfig($config);
    /**
     * Gets Access token
     * @return string|null
     */
    public function getAccessToken();

    /**
     * Log out user from this api (cookies)
     */
    public function logout();

    /**
     * Gets user data for database
     * @return array
     */
    public function getUserForDatabase();

    public function isAts();

    public function canRegister();
}
