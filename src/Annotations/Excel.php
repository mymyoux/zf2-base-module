<?php
namespace Core\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Zend\Db\Sql\AbstractSql;

class ExcelObject extends CoreObject
{
    /**
     * @var boolean
     */
    public $use = false;

 

}
/**
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class Excel extends CoreAnnotation
{
    protected $_key = "excel";
    protected $_object = "ExcelObject";
    /**
     * @var boolean
     */
    public $use = false;
    /**
     * @param $value
     * @param $request
     * @return mixed
     * @throws Exception
     */
    protected function _parse($value, $request)
    {
        $object = parent::_parse($value, $request);
//        $this->validate( $object );

        return $object;
    }

    public function validate( $object )
    {
       return $object;
    }
     

}
