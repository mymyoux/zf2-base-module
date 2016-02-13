<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 14/10/2014
 * Time: 13:48
 */

namespace Core\Service;


interface IACL {
    /**
     * @return array list of roles
     */
    public function getRoles();
}