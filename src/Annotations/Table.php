<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class TableObject extends CoreObject
{
    /**
     * @var string
     */
    public $name;
     /**
     * @var string
     */
    public $method;
      /**
     * @var string
     */
    public $useDoc = True;

    public function hasData()
    {
        return isset($this->name);
    }

    public function getTable()
    {
        if($this->sm->has($this->name . 'Table'))
        {
            return $this->sm->get($this->name . 'Table');
        }else
        return $this->sm->get($this->name);
    }
}
/**
 *
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 */
class Table extends CoreAnnotation
{
    protected $_key = "table";
    protected $_object = "TableObject";
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $method;

    /**
     * @var string
     */
    public $useDoc = True;

    /**
     * @param $value
     * @param $request
     * @return mixed
     * @throws Exception
     */
    protected function _parse($value, $request)
    {
        $object = parent::_parse($value, $request);

        $this->validate( $object );

        return $object;
    }

    protected function validate( $object )
    {
        try
        {
            $object->getTable();
        }
        catch (\Exception $e)
        {
            throw new ApiException($object->name . " doesn't exist in the ServiceLocator", 10);
        }
    }
     public function getTable()
    {
        if($this->sm->has($this->name . 'Table'))
        {
            return $this->sm->get($this->name . 'Table');
        }else
        return $this->sm->get($this->name);
    }

}
