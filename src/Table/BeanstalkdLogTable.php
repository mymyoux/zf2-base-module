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
    const STATE_CREATED = "created";
    const STATE_EXECUTED = "executed";
    const STATE_EXECUTED_FRONT = "executed_front";
    const STATE_EXECUTED_NOW = "executed_now";
    const STATE_FAILED = "failed";
    const STATE_PENDING = "pending";
    const STATE_RETRYING = "retrying";
    const STATE_CANCELLED = "cancelled";
    const STATE_REPLAYING = "replaying";
    const STATE_REPLAYING_EXECUTED = "replayed";
    const STATE_REPLAYING_FAILED = "replay_failed";
    const STATE_EXECUTING = "executing";
    /**
     * DELETED by error
     */
    const STATE_DELETED = "deleted";

	CONST TABLE    = 'beanstalkd_log';


    public function getCountLastError()
    {
        $where  = $this->select([ 'ms' => self::TABLE ])
                        ->where
                            ->equalTo('state', BeanstalkdLogTable::STATE_EXECUTED_FRONT)
                            ->greaterThan('created_time', new Expression('NOW() - INTERVAL 1 HOUR'))
                                ;
        $request = $this->select([ 'ms' => self::TABLE ])
                        ->where( $where );

        $result = $this->execute($request);

        $data = $result->toArray();

        if (!$data) return 0;

        return count($data);
    }
    public function getPrevious($queue, $id_user, $identifier)
    {
        if(!isset($id_user) && !isset($identifier))
        {
            //ignore
            return;
        }
        $where = $this->select()->where->equalTo("queue", $queue)
        ->and
        ->in("state", [BeanstalkdLogTable::STATE_CREATED,BeanstalkdLogTable::STATE_RETRYING, BeanstalkdLogTable::STATE_PENDING ]);
        if(isset($id_user))
        {
            $where = $where->and->equalTo("id_user", $id_user);
        }
        if(isset($identifier))
        {
            $where = $where->and->equalTo("identifier", $identifier);
        }

        $request = $this->select(["bean"=>BeanstalkdLogTable::TABLE])->where($where)->order(["updated_time"=>"ASC"]);
        $result = $this->execute($request);
        return $result->toArray();
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

	public function insertLog( $json, $queue, $delay, $id_user,  $priority,  $identifier)
    {
        $data   = [
            'json'	=> (string) $json,
            'queue'=>$queue,
            'delay'=>$delay,
            'id_user'=>$id_user,
            'priority'=>$priority,
            'identifier'=>$identifier,
            'state'=>  $delay <= 0?BeanstalkdLogTable::STATE_CREATED:BeanstalkdLogTable::STATE_PENDING
        ];

        $this->table(self::TABLE)->insert($data);

        return $this->table(self::TABLE)->lastInsertValue;
    }

    public function setState( $id, $state, $tries = NULL )
    {
        $data   = [
            'state'         => $state
        ];
        if(isset($tries))
        {
            $data["tries"] = $tries;
        }

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
