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
class QueryLogTable extends CoreTable
{
	CONST TABLE    = 'query_log';

	public function insertLog( $type, $sql, $time, $is_front, $is_cron, $is_queue, $stack)
    {
        $data   = [
            'type'      => $type,
            'query'     => $sql,
            'time'      => $time,
            'is_front'  => (int) $is_front,
            'is_cron'   => (int) $is_cron,
            'is_queue'  => (int) $is_queue,
            'stack'     => $stack,
        ];

        $this->table(self::TABLE)->insert($data);

        return $this->table(self::TABLE)->lastInsertValue;
    }
}
