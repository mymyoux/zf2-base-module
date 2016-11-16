<?php 
namespace Core\Table\Sql;

class Select extends \Zend\Db\Sql\Select
{
    /**
     * @param array $columns
     * @param bool $prefixColumnsWithTable
     * @return $this
     */
    public function addColumns(array $columns, $prefixColumnsWithTable = true)
    {
        $this->columns = array_merge($this->columns, $columns);
        $this->prefixColumnsWithTable = (bool) $prefixColumnsWithTable;
        return $this;
    }
}
