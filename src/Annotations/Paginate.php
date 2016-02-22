<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Zend\Db\Sql\AbstractSql;
use Zend\Db\Sql\Select;

class PaginateObject extends CoreObject implements \JsonSerializable
{
    /**
     * @var array<string>
     */
    public $allowed;
    /**
     * @var string
     */
    public $key;


    //paginate
    /**
     * @var int
     */
    public $limit;
    /**
     * @var mixed
     */
    public $previous;
    /**
     * @var mixed
     */
    public $next;
    //sort
    /**
     * @var integer
     */
    public $direction;

    private $_values = array();

    public function hasPagination()
    {
        if(isset($this->key) && (isset($this->limit) || isset($this->since) || isset($this->from)))
        {
            return True;
        }
    }
    public function hasOrder()
    {
        if(isset($this->key) && isset($this->direction))
        {
            return True;
        }
    }
    public function hasData()
    {
        return $this->hasPagination();
    }

    public function exchangeArray($data)
    {
        if(isset($data["key"]))
        {
            $this->key = $data["key"];
        }
        if(!in_array($this->key, $this->allowed))
        {
            throw new \Exception("paginate[key] not allowed");
            return;
        }

        //paginate
        if(isset($data["previous"]))
        {
            $this->previous = strval($data["previous"]);
        }
        if(isset($data["next"]))
        {
            $this->next = strval($data["next"]);
        }
        if(isset($this->previous) && isset($this->next))
        {
            throw new Exception("paginate->previous and paginate->next can't be used together");
            return;
        }
        if(isset($data["limit"]))
        {
            if(!is_numeric($data["limit"]))
            {
                throw new Exception("paginate->limit must be a numeric value");
            }
            $this->limit = intval($data["limit"]);
        }




        //order
        if(isset($data["direction"]))
        {
            if(!is_numeric($data["direction"]))
            {
                throw new \Exception("order[direction] should be numeric");
                return;
            }
            $this->direction = intval($data["direction"]);
        }



    }
    public function apply($request, $mapping = NULL)
    {
        $key = $this->key;
        if(isset($mapping))
        {
            if(is_array($mapping))
            {
                if(isset($mapping[$this->key]))
                {
                    $key = $mapping[$this->key];
                }
            }else
            if(is_string($mapping))
            {
                $key = $mapping.".".$this->key;
            }

        }
        /*
        if(isset($this->limit))
        {
            $request = $request->limit($this->limit);
        }
        if(isset($this->offset))
        {
            $request = $request->offset($this->offset);
        }
        if(isset($this->id_from))
        {
            $where = $request->where->greaterThan($id_name, $this->id_from);
            $request = $request->where($where);
        }
        */
        if($this->hasPagination())
        {
            if(isset($this->limit) && $this->limit > 0)
            {
                $request = $request->limit($this->limit);
            }
            if(isset($this->next))
            {
                $use_having = !empty($request->getRawState(Select::GROUP));
                if($use_having)
                {
                    if($this->direction>0)
                        $having = $request->having->greaterThan($key, $this->next);
                    else
                        $having = $request->having->lessThan($key, $this->next);
                    $request = $request->having($having);

                }else
                {
                    if($this->direction>0)
                        $where = $request->where->greaterThan($key, $this->next);
                    else
                        $where = $request->where->lessThan($key, $this->next);
                    $request = $request->where($where);
                }
            }
            if(isset($this->previous))
            {

                //$request = $request->where($where);

                $use_having = !empty($request->getRawState(Select::GROUP));
                if($use_having)
                {
                    if($this->direction<0)
                        $having = $request->having->greaterThan($key, $this->previous);
                    else
                        $having = $request->having->lessThan($key, $this->previous);
                    $request = $request->having($having);
                }else
                {
                    if($this->direction<0)
                        $where = $request->where->greaterThan($key, $this->previous);
                    else
                        $where = $request->where->lessThan($key, $this->previous);
                    $request = $request->where($where);
                }
            }

        }
        if($this->hasOrder())
        {
            if(isset($this->direction) && is_numeric($this->direction))
            {
                $direction = $this->direction>0?'ASC':'DESC';
            }
            $request = $request->order(array($key=>$direction));
        }
        return $request;
    }
    public function exchangeResult($data)
    {
        $this->next = NULL;
        $this->previous = NULL;
        if($this->hasPagination() && !empty($data))
        {
            if(isset($data[0]) && is_array($data[0]))
            {
                if(isset($data[0][$this->key]))
                {
                    $this->previous = $data[0][$this->key];
                }
            }else
            if(isset($data[0]->{$this->key}))
            {
                $this->previous = $data[0]->{$this->key};
            }
            if(isset($data[sizeof($data) - 1]) && is_array($data[sizeof($data) - 1]))
            {
                if (isset($data[sizeof($data) - 1][$this->key]))
                {
                    $this->next = $data[sizeof($data) - 1][$this->key];
                }
            }else
            if(isset($data[sizeof($data)-1]->{$this->key}))
            {
                $this->next = $data[sizeof($data)-1]->{$this->key};
            }
        }
    }
    public function setValue($name, $value)
    {
        $this->_values[$name] = $value;
    }

    public function jsonSerialize()
    {
        return $this->__toArray();
    }
    protected function __toArray()
    {
        $data =  get_object_vars($this);
        unset($data["sm"]);
        unset($data["_values"]);
        $data = array_merge($data, $this->_values);
        return $data;
    }
    public function isFirst()
    {
        return !isset($this->next) && !isset($this->previous);
    }
}
/**
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class Paginate extends CoreAnnotation
{
    protected $_key = "paginate";
    protected $_object = "PaginateObject";
    /**
     * @var array<string>
     */
    public $allowed;
    /**
     * @var string
     */
    public $key;


    //paginate
    /**
     * @var int
     */
    public $limit;
    /**
     * @var mixed
     */
    public $next;
    /**
     * @var mixed
     */
    public $previous;
    //sort
    /**
     * @var integer
     */
    public $direction;

    public function __construct(array $values)
    {
        if(isset($values["limit"]))
        {
            $this->limit = $values["limit"];
        }
        if(isset($values["key"]))
        {
            $this->key = $values["key"];
        }
        if(isset($values["allowed"]))
        {
            $this->allowed = explode(",",$values["allowed"]);
        }
        if(isset($values["direction"]))
        {
            $this->direction = $values["direction"];
        }
        //not really useful in annotations
        /*
        if(isset($values["previous"]))
        {
            $this->previous = $values["previous"];
        }
        if(isset($values["next"]))
        {
            $this->next = $values["next"];
        }*/

    }
}
