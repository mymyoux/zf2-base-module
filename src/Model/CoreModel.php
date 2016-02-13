<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 15:19
 */

namespace Core\Model;


use Core\Core\CoreObject;

class CoreModel extends CoreObject
{
    private $_errors;
    private $_is_validated = False;
    private $_is_valid = True;
    private $_shortName;

    public function exchangeArray($data)
    {
        $this->invalidate();
        foreach($data as $key => $value)
        {
            if(property_exists($this, $key))
            {
                if (true === is_numeric($value) && mb_strpos($key, '_str') === false)
                {
                    if (mb_strpos($value, '.') !== false || mb_strpos($value, ',') !== false)
                        $value = floatval($value);
                    if (is_float($value))
                    {
                        $value = number_format($value, 2, ',', '');
                        if ($value == '0,00') $value = 0;
                    }
                    else
                        $value = (int) $value;
                }
                $this->$key = $value;
            }
            if($key == "id_".$this->getShortName())
            {
                if(property_exists($this, "id"))
                {
                    $this->id = $value;
                }
            }
        }
    }

    /**
     * Converts current model into array.
     * Will set all not private class vars. (private attribute or var starting with _)
     * @return array
     */
    public function toArray()
    {
        $data = to_array($this, $this);
        return $data;
        if(!isset($value))
        {
            $value = $this;
        }
        $keys = get_class_vars(get_class($value));
        $data = array();
        foreach($keys as $key => $value)
        {
            if(!starts_with($key, "_"))
            {
                $data[$key] = $this->$key;
                if(is_array($this->$key))
                {
                    foreach($this->$keys as $k=>$v)
                    {
                        if(isset($v))
                        {
                            $data[$key][$k] = $this->toArray($v);
                        }
                    }
                }else
                {
                    if(is_object($this->$key))
                    {
                        if(method_exists($this->$key,"toArray"))
                        {
                            $data[$key] = $this->$key->toArray();
                        }
                    }
                }
            }
        }
        if(property_exists($this, "id"))
        {

            $data["id_".$this->getShortName()] = $this->id;
        }
        return $data;
    }
    public function __toArray()
    {
        return $this->toArray();
    }
    /**
     * Gets class short name.
     * Examples:
     * CoreModel => core
     * ApplicationTestModel => applicationtest
     * AppTest => apptest
     * @return string
     */
    public function getShortName()
    {
        if(isset($this->_shortName))
        {
            return $this->_shortName;
        }
        $name = get_class($this);
        $name = substr(strrchr($name, "\\"),1);
        if(ends_with($name, "Model"))
        {
            $name = substr($name, 0, strlen($name)-5);
        }
        $name = mb_strtolower($name);
        $this->_shortName = $name;
        return $this->_shortName;
    }

    /**
     * @return array
     */
    public function toDatabaseArray()
    {
        $data = $this->toArray();
        if(array_key_exists("id", $data))
        {
            $name = $this->getShortName();

            $data["id_".$name] = $data["id"];
            unset($data["id"]);
        }
        return $data;
    }
    private function _validate()
    {
        $this->_errors = array();
        if($this->validate())
        {
            $this->_is_valid = True;
        }
        $this->_is_validated = True;
    }
    protected function invalidate()
    {
        $this->_is_validated = False;
    }
    protected function addError($name, $description)
    {
        $this->_errors[] = array("name"=>$name, "description"=>$description);
    }
    /**
     * Compute the validity of the model. Return true if it is valid, call addError to set errors
     * @return [type] [description]
     */
    protected function validate()
    {
        return True;
    }
    /**
     * Check if model is valid
     * @return boolean [description]
     */
    public function isValid()
    {
        if(!$this->_is_validated)
        {
            $this->_validate();
        }
        return $this->_is_valid;
    }
    public function getErrors()
    {
        return $this->_errors;
    }
}
