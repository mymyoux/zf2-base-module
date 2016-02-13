<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 11/10/2014
 * Time: 19:58
 */

namespace Core\Service;


interface IIdentity {
    public function setACL($acl);
    public function getACL();
}