<?php

namespace Core\Table;

use Core\Exception\FatalException;
use Core\Table\CoreTable;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;

/**
 * Class ShortlistTable - not used anymore
 * @package Application\Table
 * @deprecated
 */
class BeanstalkdLogTable extends CoreTable
{
	CONST TABLE    = 'beanstalkd_log';


    public function getCountLastError()
    {
        $where  = $this->select([ 'ms' => self::TABLE ])
                        ->where
                            ->equalTo('id_send', 2)
                            ->greaterThan('created_time', new Expression('NOW() - INTERVAL 1 HOUR'))
                                ;
        $request = $this->select([ 'ms' => self::TABLE ])
                        ->where( $where );

        $result = $this->execute($request);

        $data = $result->toArray();

        if (!$data) return 0;

        return count($data);
    }
    public function getIdsGreaterThanOrEqual( $id, $queue = null )
    {
        $where = $this->select()->where->greaterThanOrEqualTo("id", $id);

        if (null !== $queue)
            $where->equalTo('queue', (string) $queue);

        $request = $this->select(BeanstalkdLogTable::TABLE)->where($where);
        $result = $this->execute($request);

        return array_map(function($item)
        {
            return intval($item["id"]);
        }, $result->toArray());

    }
    public function findById( $id )
    {
        $where  = $this->select([ 'ms' => self::TABLE ])
                        ->where
                            ->equalTo('id', (int) $id)
                                ;
        $request = $this->select([ 'ms' => self::TABLE ])
                        ->where( $where );

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;

        return $data;
    }

    public function findByIdAndQueue( $id, $queue )
    {
        $where  = $this->select([ 'ms' => self::TABLE ])
                        ->where
                            ->equalTo('id', (int) $id)
                            ->equalTo('queue', (string) $queue)
                                ;
        $request = $this->select([ 'ms' => self::TABLE ])
                        ->where( $where );

        $result = $this->execute($request);

        $data = $result->current();

        if (!$data) return null;

        return $data;
    }

	public function insertLog( $json, $queue, $delay, $id_user )
    {
        $data   = [
            'json'	=> (string) $json,
            'queue'=>$queue,
            'delay'=>$delay,
            'id_user'=>$id_user
        ];

        $this->table(self::TABLE)->insert($data);

        return $this->table(self::TABLE)->lastInsertValue;
    }

    public function setSend( $id, $id_send )
    {
        $data   = [
            'id_send'         => (int) $id_send
        ];

        $this->table(self::TABLE)->update($data, ['id' => (int) $id]);
    }
    public function setBeanstalkdID( $id, $id_beanstalkd )
    {
        $data   = [
            'id_beanstalkd'         => (int) $id_beanstalkd
        ];

        $this->table(self::TABLE)->update($data, ['id' => (int) $id]);
    }
}
