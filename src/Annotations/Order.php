<?php
namespace Core\Annotations;
use Core\Exception\Exception;

class OrderObject extends CoreObject
{
    /**
    * @var array<string>
    */
    public $allowed;
    /**
     * @var array<string>
     */
    public $default;
    /**
     * @var string
     */
    public $key;
    /**
     * @var string
     */
    public $direction;

    public function hasData()
    {
        return isset($this->needs) || isset($this->forbidden);
    }

    public function exchangeArray($data)
    {
        if(isset($data["key"]))
        {
            $this->key = $data["key"];
        }else
        {
            $this->key = $this->default;
        }
        if(isset($data["direction"]))
        {
            if(!is_numeric($data["direction"]))
            {
                throw new \Exception("order[direction] should be numeric");
                return;
            }
            $this->direction = $data["direction"];
        }
        if(!in_array($this->key, $this->allowed))
        {
            throw new \Exception("order[key] not allowed");
            return;
        }
        if(isset($this->direction) && is_numeric($this->direction))
        {
            $this->direction = $this->direction>0?'ASC':'DESC';
        }
    }
    public function apply($request, $prefix = NULL)
    {
        if(isset($this->key) && isset($this->direction))
        {
            $key = $this->key;
            //TODO:handle array for prefix=>property attribution
            if(isset($prefix))
            {
                $key = $prefix.".".$key;
            }
            $request = $request->order(array($key=>$this->direction));


        }
        return $request;
    }
}
/**
 *
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
class Order extends CoreAnnotation
{
    protected $_key = "order";
    protected $_object = "OrderObject";
    /**
     * @var array<string>
     */
    public $allowed;
    /**
     * @var array<string>
     */
    public $default;
    /**
     * @var integer
     */
    public $direction;
    public function __construct(array $values)
    {
        if(isset($values["allowed"]))
        {
           $this->allowed = explode(",",$values["allowed"]);
        }
        if(isset($values["default"]))
        {
            $this->default = $values["default"];
        }
        if(isset($values["direction"]))
        {
            $this->direction = $values["direction"];
        }
    }


}
