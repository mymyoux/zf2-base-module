<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 30/09/2014
 * Time: 21:12
 */

namespace Core\Core;


class CoreObject  implements \JsonSerializable
{
    public function __toString()
    {
        $data = array("__class" =>  get_called_class());
        $data = array_merge($data, $this->__toArray());
        return json_encode($data);
    }
    public function jsonSerialize() {
        return $this->__toArray();
    }
    protected function __toArray()
    {
        return get_object_vars($this);
    }
} 