<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 28/10/14
 * Time: 18:11
 */

namespace Core\Table\Sql;


class Sql extends \Zend\Db\Sql\Sql{

    public function insert($table = null)
    {
        if ($this->table !== null && $table !== null) {
            throw new Exception\InvalidArgumentException(sprintf(
                'This Sql object is intended to work with only the table "%s" provided at construction time.',
                $this->table
            ));
        }
        return new Insert(($table) ?: $this->table);
    }
}