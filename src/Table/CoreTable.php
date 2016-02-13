<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 15:09
 */
namespace Core\Table;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;
use Core\Table\Sql\Sql;
use Zend\Db\Sql\Update;
use Zend\Db\TableGateway\TableGateway as ZTableGateway;

class CoreTable extends \Core\Service\CoreService
{
    /**
     * @var \Core\Table\TableGateway
     */
    private $_table;
    private $_tables;
    /**
     * @var \Zend\Db\Adapter\Adapter
     */
    protected $db;
    /**
     * @var \Core\Table\Sql\Sql;
     */
    protected $sql;

    /**
     * @param $tableGateway
     */
    public function __construct($tableGateway)
    {
        $this->_tables = array();
        if($tableGateway instanceof ZTableGateway)
        {
            $this->_table = $tableGateway;
            $this->db = $tableGateway->getAdapter();
            $this->sql = new Sql($this->db);
        }
        else
        {

            foreach($tableGateway as $key => $value)
            {
                $this->_tables[$key] = $value;
                $this->db = $this->_tables[$key]->getAdapter();
                if(!isset($this->sql))
                {
                    $this->sql = new Sql($this->db);
                }
            }
        }

    }

    protected function startTransaction()
    {
        $this->db->getDriver()->getConnection()->beginTransaction();
    }

    protected function rollbackTransaction( $throw = false )
    {
        $this->db->getDriver()->getConnection()->rollback();

        if (true === $throw)
        {
            throw new \Core\Exception\ApiException('Fatal error : transaction failed', 5);
        }

    }

    protected function endTransaction()
    {
        $this->db->getDriver()->getConnection()->commit();
    }

    protected function toString($request)
    {
        return $this->sql->getSqlstringForSqlObject($request);
    }
    /**
     * @param string $name
     * @return \Core\Table\TableGateway
     */
    public function table($name = NULL)
    {
        if(!isset($name))
        {
            if(isset($this->_table))
                return $this->_table;
            return $this->_tables["_default"];
        }
        if(!array_key_exists($name, $this->_tables))
        {
            $this->_tables[mb_strtolower($name)] = new TableGateway($name, $this->db, NULL, NULL);
        }
        return $this->_tables[mb_strtolower($name)];
    }
    public function fetchAll()
    {
        $resultSet = $this->table->select();
        return $resultSet;
    }

    /**
     * @param string|\Zend\Db\TableGateway\TableGateway $name Name of table or TableGateway instance. If not specified default table is used ($this->table())
     * @return \Zend\Db\Sql\Select
     */
    public function select($name = NULL)
    {
        if(!isset($name))
        {
            $name = $this->table()->getTable();
        }
        if($name instanceof ZTableGateway)
        {
            $name = $name->getTable();
        }
        return $this->sql->select($name);
    }
    /**
     * @param string|\Zend\Db\TableGateway\TableGateway $name Name of table or TableGateway instance. If not specified default table is used ($this->table())
     * @return \Zend\Db\Sql\Update
     */
    public function update($name = NULL)
    {
        if(!isset($name))
        {
            $name = $this->table()->getTable();
        }
        if($name instanceof ZTableGateway)
        {
            $name = $name->getTable();
        }
        return $this->sql->update($name);
    }
    /**
     * @param string|\Zend\Db\TableGateway\TableGateway $name Name of table or TableGateway instance. If not specified default table is used ($this->table())
     * @return \Core\Table\Sql\Insert
     */
    public function insert($name = NULL)
    {
        if(!isset($name))
        {
            $name = $this->table()->getTable();
        }
        if($name instanceof ZTableGateway)
        {
            $name = $name->getTable();
        }
        return $this->sql->insert($name);
    }
    /**
     * @param string|\Zend\Db\TableGateway\TableGateway $name Name of table or TableGateway instance. If not specified default table is used ($this->table())
     * @return \Zend\Db\Sql\Delete
     */
    public function delete($name = NULL)
    {
        if(!isset($name))
        {
            $name = $this->table()->getTable();
        }
        if($name instanceof ZTableGateway)
        {
            $name = $name->getTable();
        }
        return $this->sql->delete($name);
    }
    public function execute($request)
    {
        if(is_string($request))
        {
            $strRequest = $request;
        }else
        {
            $strRequest = $this->sql->getSqlStringForSqlObject($request);
        }
        $results = $this->db->query($strRequest, Adapter::QUERY_MODE_EXECUTE);
        return $results;
    }

    public function getList($where = NULL, $page=0, $count=10)
    {

        $name = $this->table()->getTable();
        $select = $this->select($name);
        if(isset($where))
        {
            $select = $select->where($where);
        }
        $select = $select->limit($count)->offset($page*$count);
        $result = $this->execute($select);
        return $result;
    }



}
