<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 11/10/2014
 * Time: 19:14
 */

namespace Core\Exception;


class ApiException extends \Exception{

    public $object;
    public function __construct($message = "", $code = 0, Exception $previous = null, $object = NULL) {

        $message = '[API Exception] ' . $message;

        parent::__construct($message, $code, $previous);
        $this->object = $object;
    }

}
