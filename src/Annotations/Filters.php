<?php
namespace Core\Annotations;
use \Core\Exception\Exception;
class FiltersObject extends CoreObject/* implements ICoreObjectValidation*/
{
    public $allowed;
    public $filters;
    public function __construct()
    {
        $this->filters = array();
    }
    public function exchangeArray($data)
    {
        parent::exchangeArray($data);
       foreach($data as $key=>$value)
       {
          if(!in_array($key,$this->allowed))
          {
                throw new Exception("filter_not_allowed",0,NULL, $key);
          }
           $this->filters[$key] = $value;
       }
    }

    /**
     * @param $request Request to filter
     * @param null $mapping List of filters used and/or map from filter key to database column
     */
    public function apply($request, $mapping = NULL)
    {
        //apply filters to the request
    }
}
/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Filters extends CoreAnnotation
{
    protected $_key = "filters";
    protected $_object = "FiltersObject";
    public $allowed;
    public function __construct(array $values)
    {
        if(isset($values["value"]))
        {
            $values["allowed"] = $values["value"];
            unset($values["value"]);
        }
        if(isset($values["allowed"]))
        {
            $this->allowed = explode(",", $values["allowed"]);
        }
    }
}