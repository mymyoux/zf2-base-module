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
    CONST MAX_RECONNECT_COUNT   = 1;
    CONST MAX_GLOBAL_RECONNECT  = 3; // max reconnect failed

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

    public function getDB()
    {
        return $this->db;
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

        try
        {
            $results = $this->db->query($strRequest, Adapter::QUERY_MODE_EXECUTE);
        }
        catch (\Exception $e)
        {
            if ($this->isExceptionNeedReconnectMySQL($e))
            {
                $this->_log('[' . date('Y-m-d H:i:s') . '] RECONNECT QUERY => ' . str_replace(PHP_EOL, ' ', $strRequest));

                if (false === $this->_reconnect())
                    throw $e;

                return $this->execute( $request );
            } else {
                throw $e;
            }
        }

        return $results;
    }

    private function isExceptionNeedReconnectMySQL( $e )
    {
        $message = $e->getMessage();

        if (mb_strpos($message, 'MySQL server has gone away') !== false)
            return true;
        if (mb_strpos($message, 'Error while sending QUERY packet') !== false)
            return true;

        return false;
    }

    private function _log($message)
    {
        $filename = ROOT_PATH . '/logs/mysql-gone-away.log';

        $data = $message . PHP_EOL;

        $cache = fopen($filename, 'a');
        fwrite($cache, $data);
        fclose($cache);
    }

    private function _reconnect()
    {
        global $retry_mysql_reconnect_yb;

        if (!isset($retry_mysql_reconnect_yb))
            $retry_mysql_reconnect_yb = 0;
        else
            $retry_mysql_reconnect_yb++;

        if ($retry_mysql_reconnect_yb > self::MAX_GLOBAL_RECONNECT)
        {
            $this->_log('retry >= 3');
            return false;
        }
        $this->db->getDriver()->getConnection()->disconnect();
        $this->_log('disconnect');

        for ($i = 1; $i <= self::MAX_RECONNECT_COUNT; $i++) {
            sleep(1);
            try {
                $this->_log('connect');
                $this->db->getDriver()->getConnection()->connect();
            } catch (\Exception $e) {
                if ($i == self::MAX_RECONNECT_COUNT) {
                    $this->_log('exception ' . $e->getMessage());
                    $retry_mysql_reconnect_yb++;
                    throw $e;
                }
            }
            if ($this->db->getDriver()->getConnection()->isConnected())
            {
                $this->_log('connect success');
                // $retry_mysql_reconnect_yb--;
                return true;
            }
        }
        $this->_log('connect failed');

        return false;
    }
    public function cut($data, $keys)
    {
        return array_map(function($item) use($keys)
            {
                foreach($item as $key=>$value)
                {
                    $subkeys = explode("_", $key);
                    if(in_array($subkeys[0], $keys))
                    {
                        if(isset($value))
                        {
                            if(!isset($item[$subkeys[0]]))
                            {
                                $item[$subkeys[0]] = [];
                            }
                            $item[$subkeys[0]][implode("_", array_splice($subkeys, 1))] = $value;
                        }
                        unset($item[$key]);
                    }
                }
                return $item;
            }, is_array($data)?$data:$data->toArray());
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
