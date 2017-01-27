<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 01/10/2014
 * Time: 14:00
 */

namespace Core\Table;


use Zend\Db\Sql\Select;
use Zend\ServiceManager\ServiceLocatorInterface;

class TableGateway extends \Zend\Db\TableGateway\TableGateway
{
    protected $sm_initialized;
    protected $sm;
    protected $session;
    /**
     * @return \Zend\Db\Sql\Select
     */
    public function getSelect()
    {
        return new Select($this->getTable());
    }

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        if ($this->sm_initialized || null === $serviceLocator)
        {
            return;
        }
        $this->sm = $serviceLocator;
        // $this->session = $this->sm->get("session");
        // if($this->session->getServiceLocator() === NULL)
        // {
        //     $this->session->setServiceLocator($this->sm);
        // }
        // $this->init();
        $this->sm_initialized = true;
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
            $set["created_time"] = new \Zend\Db\Sql\Expression("NOW(3)");
        }

        $start_time = microtime( true );
        $result     = parent::insert($set);

        if ($this->sm_initialized)
        {
            $this->sm->get('Log')->logMetric('insert', 1);

            if (isset($this->table) && $this->table != 'query_log')
            {
                $insert = $this->sql->insert();
                $insert->values($set);
                $this->sm->get('Log')->logSqlQuery('insert', $insert->getSqlString($this->getAdapter()->getPlatform()), $start_time);
            }
        }

        return $result;
    }

    public function update($set, $where = null)
    {
        $start_time = microtime( true );
        $result     = parent::update($set, $where);

        if ($this->sm_initialized)
        {
            $this->sm->get('Log')->logMetric('update', 1);

            if (isset($this->table) && $this->table != 'query_log')
            {
                $update = $this->sql->update();
                $update->set($set);
                if ($where !== null) {
                    $update->where($where);
                }
                $this->sm->get('Log')->logSqlQuery('update', $update->getSqlString($this->getAdapter()->getPlatform()), $start_time);
            }
        }

        return $result;
    }

    public function delete($where)
    {
        $start_time = microtime( true );
        $result     = parent::delete($where);

        if ($this->sm_initialized)
        {
            $this->sm->get('Log')->logMetric('delete', 1);

            if (isset($this->table) && $this->table != 'query_log')
            {
                $delete = $this->sql->delete();
                if ($where instanceof \Closure) {
                    $where($delete);
                } else {
                    $delete->where($where);
                }
                $this->sm->get('Log')->logSqlQuery('deleete', $delete->getSqlString($this->getAdapter()->getPlatform()), $start_time);
            }
        }

        return $result;
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
