<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 01/10/2014
 * Time: 14:00
 */

namespace Core\Table;


use Zend\Db\Sql\Select;

class TableGateway extends \Zend\Db\TableGateway\TableGateway
{
    /**
     * @return \Zend\Db\Sql\Select
     */
    public function getSelect()
    {
        return new Select($this->getTable());
    }

    /**
     * @param $params
     */
    public function count($params = NULL)
    {
        $select = $this->getSelect();
        $select = $select->columns(
            array("count" => new \Zend\Db\Sql\Expression('COUNT(*)'))
        );
        if(!empty($params))
        {
            $select->where($params);
        }
        $row = $this->execute($select);
        $row = $row->current();
        if(!$row)
        {
            return 0;
        }
        return intval($row["count"]);
    }

    /**
     * Executes given query
     * @param \Zend\Db\Sql\PreparableSqlInterface $query
     * @param array $parameters
     * @return mixed
     */
    public function execute(\Zend\Db\Sql\PreparableSqlInterface $query, $parameters = NULL)
    {


        $statement = $this->getSql()->prepareStatementForSqlObject($query);
        return $statement->execute($parameters);
    }

    public function insert($set)
    {
        if(!array_key_exists("created_time", $set))
        {
            $set["created_time"] = new \Zend\Db\Sql\Expression("NOW()");
        }
        return parent::insert($set);
    }

    /**
     * Select one item
     * @param $where
     * @return array|\ArrayObject|null
     */
    public function selectOne($where = NULL)
    {
        $result = $this->select($where);
        if($result->current() !== False)
        {
            return $result->current();
        }
        return NULL;
    }
} 