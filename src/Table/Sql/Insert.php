<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 28/10/14
 * Time: 18:05
 */

namespace Core\Table\Sql;

/**
 * Class Insert.
 * Insert values into Mysql Table with created_time column set
 * @package Core\Table\Sql
 */
class Insert extends \Zend\Db\Sql\Insert
{

    /**
     * @inheritDoc
     */
    public function columns(array $columns)
    {
        if(!array_key_exists("created_time", $columns))
        {
            $columns[] = "created_time";
        }
        return parent::columns($columns);
    }

    /**
     * @inheritDoc
     */
    public function values($values, $flag = self::VALUES_SET)
    {
        if(is_array($values))
        {
            $values["created_time"] = new \Zend\Db\Sql\Expression("NOW()");
        }
        return parent::values($values, $flag);
    }
}