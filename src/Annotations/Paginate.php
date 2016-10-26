<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Zend\Db\Sql\AbstractSql;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;

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
    public $exchangedResult;
    protected $has_been_partially_filtered;

    private $_values = array();

    public function hasPagination()
    {
        if(isset($this->key) && (isset($this->limit) || isset($this->since) || isset($this->from)))
        {
            return True;
        }
        return False;
    }
    public function hasOrder()
    {
        if(isset($this->key) && isset($this->direction))
        {
            return True;
        }
        return False;
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
        if(!is_array($this->key))
        {
            $this->key = [$this->key];
        }
        foreach($this->key as $key)
        {
            if(!in_array($key, $this->allowed))
            {
                throw new \Exception("paginate[key] not allowed => ".$key);
                return;
            }
        }

        //paginate
        if(isset($data["previous"]))
        {
            $this->previous = is_array($data["previous"])?array_map(function($item){ return  strval($item);}, $data["previous"]):[strval($data["previous"])];
        }
        if(isset($data["next"]))
        {
             $this->next = is_array($data["next"])?array_map(function($item){
            $item = strval($item);
          
            return  strval($item);}, $data["next"]):[strval($data["next"])];
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


        if(!isset($data["direction"]))
            $data['direction'] = $this->direction;

        //order
        if(isset($data["direction"]))
        {
            if(!is_array($data["direction"]))
            {
                $data["direction"] = [$data["direction"]];
            }
            if(!is_numeric_array($data["direction"]))
            {
                throw new \Exception("order[direction] should be a numeric array");
                return;
            }
            $this->direction = array_map(function($item){return intval($item);},$data["direction"]);
        }



    }
    public function apply($request, $mapping = NULL, $only = NULL, $having = NULL)
    {
        $keys = $this->key;
        $orderCustom = [];
        $used = [];
        $force_having = False;
        foreach($keys as $index=>$k)
        {
            $used[$index] = !isset($only) || in_array($k, $only);
            if($used[$index] && isset($having))
            {
                if(in_array($k, $having))
                {
                    $force_having = True;
                }
            }
            if(isset($mapping) && $used[$index])
            {
                if(is_array($mapping))
                {
                    if(isset($mapping[$k]))
                    {
                        $key = $mapping[$k];
                        if($key instanceof PaginateExpression)
                        {
                            $orderCustom[$index] = $key->getExpression();
                            $key = $key->getColumn();
                        }
                        if(strpos($key, ".") === False)
                        {
                            $force_having = True;
                        }
                    }else
                    {
                        $key = $mapping[0].".".$k;
                    }
                }else
                if(is_string($mapping))
                {
                    $key = $mapping.".".$k;
                }
                $keys[$index] = $key;
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
            $ = $request->where->greaterThan($id_name, $this->id_from);
            $request = $request->where($where);
        }
        */
        if($this->hasPagination())
        {
            if(isset($this->limit) && $this->limit > 0)
            {
                $request = $request->limit($this->limit);
            }
            $use_having = $force_having || !empty($request->getRawState(Select::GROUP));
            if(isset($this->next))
            {
                $first = True;
                $where = NULL;
                foreach($keys as $index=>$key)
                {
                    if(!$used[$index])
                    {
                        $this->has_been_partially_filtered = True;
                        break;
                    }

                    if($first)
                    {
                        if($use_having)
                        {
                            $where = $request->having;
                        }else
                        {
                            $where = $request->where;
                        }
                     }else
                     {
                        $where = $where->or;
                        $where = $where->nest;
                     }
                     $direction = $this->direction[$index];
                    if($direction>0)
                    {
                        if(mb_strlen($this->next[$index]))
                        {
                            $where = $where->greaterThan($key, $this->next[$index]);
                        }else
                        {
                            $where = $where->nest
                            ->isNotNull($key)
                            ->or
                            ->greaterThan($key, $this->next[$index])
                            ->unnest;
                        }
                    }else
                    {
                         if(mb_strlen($this->next[$index]))
                        {
                            $where = $where->lessThan($key, $this->next[$index]);
                        }else
                        {
                             $where = $where->nest
                            ->isNotNull($key)
                            ->or
                            ->lessThan($key, $this->next[$index])
                            ->unnest;
                        }
                    }

                    for($i=0;$i<$index; $i++)
                    {
                        $where = $where->and;
                        if(mb_strlen($this->next[$i]))
                        {
                            $where = $where->equalTo($keys[$i], $this->next[$i]);
                        }else
                        {
                            $where = $where->nest
                                ->isNull($keys[$i], $this->next[$i])
                                ->or
                                ->equalTo($keys[$i], $this->next[$i])
                                ->unnest;
                        }
                    }
                    if(!$first)
                    {
                        $where = $where->unnest;
                    }
                    $first = False;
                }
                if(isset($where))
                {
                    if($use_having)
                    {
                        $request = $request->having($where);
                    }else
                    {
                        $request = $request->where($where);
                    }
                }
            }
            if(isset($this->previous))
            {
                $having = NULL;
                $first = True;
                foreach($keys as $index=>$key)
                {
                    if(!$used[$index])
                    {
                        $this->has_been_partially_filtered = True;
                        continue;
                    }
                    if($first)
                    {
                        if($use_having)
                        {
                            $where = $request->having;
                        }else
                        {
                            $where = $request->where;
                        }
                     }else
                     {
                        $where = $where->or;
                        $where = $where->nest;
                     }
                     $direction = $this->direction[$index];
                    if($direction<0)
                    {
                        if(mb_strlen($this->previous[$index]))
                        {
                            $where = $where->greaterThan($key, $this->previous[$index]);
                        }else
                        {
                             $where = $where->nest
                            ->isNotNull($key)
                            ->or
                            ->greaterThan($key, $this->previous[$index])
                            ->unnest;
                        }
                    }else
                    {
                        if(mb_strlen($this->previous[$index]))
                        {
                            $where = $where->lessThan($key, $this->previous[$index]);
                        }else
                        {
                               $where = $where->nest
                            ->isNotNull($key)
                            ->or
                            ->lessThan($key, $this->previous[$index])
                            ->unnest;
                        }
                    }

                    for($i=0;$i<$index; $i++)
                    {
                        $where = $where->and;
                        if(mb_strlen($this->previous[$i]))
                        {
                            $where = $where->equalTo($keys[$i], $this->previous[$i]);
                        }else
                        {
                            $where = $where->nest
                                ->isNull($keys[$i], $this->previous[$i])
                                ->or
                                ->equalTo($keys[$i], $this->previous[$i])
                                ->unnest;
                        }
                    }
                    if(!$first)
                    {
                        $where = $where->unnest;
                    }
                    $first = False;
                }
                if(isset($having))
                {
                    if($use_having)
                    {
                        $request = $request->having($where);
                    }else
                    {
                        $request = $request->where($where);
                    }
                }
            }

        }
        if($this->hasOrder())
        {

            if(isset($this->direction))
            {
                if(!is_array($this->direction))
                {
                    $temp = $this->direction;
                    $this->direction = array_map(function($item) use($temp)
                    {
                        return $temp;
                    }, $this->key);
                }
                $direction = array_map(function($item)
                {
                    return $item>0?'ASC':'DESC';
                }, $this->direction);
            }
            $orderRequest = [];
            foreach($keys as $index=>$key)
            {
                if(!$used[$index])
                {
                    continue;
                }

                if(isset($orderCustom[$index]))
                {
                    $orderRequest[] = new expression($orderCustom[$index]." ".$direction[$index]);
                }else
                {
                    $orderRequest[$key] = $direction[$index];
                }
            }
            /*
            $new_order = NULL;
            if(isset($orderMapping) && !empty($orderMapping))
            {
                foreach($orderMapping as $orderM)
                {
                    if($orderM->match($this->key))
                    {
                        $new_order = $orderM;
                        break;
                    }
                }
                if(isset($new_order))
                {
                    $orderRequest[$new_order->getColumn()]=$new_order->getOrder();
                    $request->addColumns([$this->key=>new Expression("")]);
                }
            }*/
            /*
            if(isset($orderMapping))
            {
                if(is_array($orderMapping))
                {
                    if(isset($mapping[$this->key]))
                    {
                        $key = $mapping[$this->key];
                    }else
                    {
                        $key = $mapping[0].".".$this->key;
                    }
                }else
                if(is_string($mapping))
                {
                    $key = $mapping.".".$this->key;
                }

            }*/
            if(!empty($orderRequest))
                $request = $request->order($orderRequest);
        }
        return $request;
    }
    public function exchangeResult(&$data, $mapping = NULL)
    {
        if($this->exchangedResult === True)
        {
            return;
        }
        $this->exchangedResult = True;
        if($this->has_been_partially_filtered === True)
        {
            $keys = $this->key;
            foreach($keys as $index=>$k)
            {
                if(isset($mapping))
                {
                    if(is_array($mapping))
                    {
                        if(isset($mapping[$k]))
                        {
                            $key = $mapping[$k];
                            if($key instanceof PaginateExpression)
                            {
                                $orderCustom[$index] = $key->getExpression();
                                $key = $key->getColumn();
                            }
                        }else
                        {
                            $key = $mapping[0].".".$k;
                        }
                    }else
                    if(is_string($mapping))
                    {
                        $key = $mapping.".".$k;
                    }
                    $keys[$index] = $key;
                }
            }
            if(isset($this->next))
            {
                $data = array_values(array_filter($data, function($item) use($keys)
                {
                    foreach($keys as $index=>$key)
                    {

                        $direction = $this->direction[$index];
                        if($direction>0)
                        {
                            if($item[$key]<=$this->next[$index])
                            {
                                continue;
                            }
                           // $where = $where->greaterThan($key, $this->next[$index]);
                        }else
                        {
                            if($item[$key]>=$this->next[$index])
                            {
                                continue;
                            }
                            //$where = $where->lessThan($key, $this->next[$index]);
                        }

                        for($i=0;$i<$index; $i++)
                        {
                            if($item[$keys[$i]]!=$this->next[$i])
                            {
                                continue 2;
                            }
                            //$where = $where->and;
                            //$where = $where->equalTo($keys[$i], $this->next[$i]);
                        }
                       return true;
                    }
                    return false;
                }));
            }

            if(isset($this->previous))
            {
                $data = array_values(array_filter($data, function($item) use($keys)
                {
                    foreach($keys as $index=>$key)
                    {
                         $direction = $this->direction[$index];
                        if($direction<0)
                        {
                            if($item[$key]<=$this->previous[$index])
                            {
                                continue;
                            }
                        }else
                        {
                            if($item[$key]>=$this->previous[$index])
                            {
                                continue;
                            }
                        }

                        for($i=0;$i<$index; $i++)
                        {
                            if($item[$keys[$i]]!=$this->previous[$i])
                            {
                                continue 2;
                            }
                        }
                        return true;
                    }
                    return false;
                }));
            }
        }

        $this->next = NULL;
        $this->previous = NULL;
        if($this->hasPagination() && !empty($data))
        {
            $this->previous = [];
            if(isset($data[0]) && is_array($data[0]))
            {
                $keys = array_keys($data[0]);
                foreach($this->key as $key)
                {
                    if(in_array($key, $keys))
                    {
                        $this->previous[] = $data[0][$key];
                    }
                }
                /*
                if(isset($data[0][$this->key]))
                {
                    $this->previous = $data[0][$this->key];
                }*/
            }else
            {
                foreach($this->key as $key)
                {
                    if(isset($data[0]->{$key}))
                    {
                        $this->previous[] = $data[0]->{$key};
                    }
                }
                //$this->previous = $data[0]->{$this->key};
            }
            if(isset($data[sizeof($data) - 1]))
            {
                $this->next = [];
                $len = sizeof($data)-1;
                if(is_array($data[$len]))
                {
                    $keys = array_keys($data[$len]);
                    foreach($this->key as $key)
                    {
                        if(in_array($key, $keys))
                        {
                            $this->next[] = $data[$len][$key];
                        }
                    }
                }else
                {
                    foreach($this->key as $key)
                    {
                        if(isset($data[$len]->{$key}))
                        {
                            $this->next[] = $data[$len]->{$key};
                        }
                    }
                    //$this->next = $data[$len]->{$this->key};
                }
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
/*
class PaginateOrderConfig
{
    protected $names;
    protected $column;
    protected $order;
    public function __construct($names, $column, $order)
    {
        $this->names = is_array($names)?$names:[$names];
        $this->column = $column;
        $this->order = $order == -1?"DESC":($order == 1?'ASC':$order);
    }
    public function match($column)
    {
        return in_array($column, $this->names);
    }
    public function getColumn()
    {
        return $this->column;
    }
    public function getOrder()
    {
        return $this->order;
    }
}*/
class PaginateExpression
{
    protected $expression;
    protected $column;
    public function __construct($column, $expression)
    {
        $this->expression = $expression;
        $this->column = $column;
    }
    public function getExpression()
    {
        return $this->expression;
    }
    public function getColumn()
    {
        return $this->column;
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

