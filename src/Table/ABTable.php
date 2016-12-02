<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:23
 */

namespace Core\Table;


use Core\Model\UserModel;
use Core\Exception\ApiException;
use Zend\Db\Sql\Expression;

/**
 * Class ABTable
 * @package Core\Table
 */
class ABTable extends CoreTable
{

    const TABLE = "abtesting";
   
   public function getTotal()
   {
        $request = $this->select(array("ab"=>ABTable::TABLE))->columns(array("count"=>new Expression("COUNT(*)")));
        $result = $this->execute($request);
        $result = $result->current();
        if($result !== NULL)
        {
            return (int)$result["count"];
        }
        return 0;
   }
    public function create($user, $apirequest)
    {
        $data = $apirequest->params->toArray();
        $id_user = isset($user)?$user->id:NULL;
        $data["id_user"] = $id_user;
        $this->table(ABTable::TABLE)->insert($data);
        return $this->table(ABTable::TABLE)->lastInsertValue;
    }
    public function get($user, $apirequest)
    {
        $where = $this->getWhere($user, $apirequest);
        $request = $this->select(array("ab"=>ABTable::TABLE))->where($where)->order(array("ab.created_time"=>"DESC"))->limit(1);
        $result = $this->execute($request);
        $result = $result->current();
        return $result;
    }
    protected function getWhere($user, $apirequest)
    {
        $id_user = isset($user)?$user->id:NULL;
        $test = $apirequest->params->test->value;
        $name = $apirequest->params->name->value;
        $version = $apirequest->params->version->value;

        if(isset($apirequest->params->id_abtesting) && isset($apirequest->params->id_abtesting->value))
        {
            $where = $this->select()->where->equalTo("id_abtesting", $apirequest->params->id_abtesting->value);   
        }else
        {
            $where = $this->select()->where
            ->equalTo("name", $name);
            if(isset($test))
            {
                $where = $where->and->equalTo("test", $test);
            }
            if(isset($version))
            {
                $where = $where->and->equalTo("version", $version);
            }
            if(isset($id_user))
            {
                $where = $where->and->equalTo("id_user", $id_user);
            }else
            {
                $where = $where->isNull("id_user");
            }
        }
        return $where;
    }
    public function updateAB($user, $apirequest)
    {
        $data = $apirequest->params->toArray();
        $id_user = isset($user)?$user->id:NULL;

        $where = $this->getWhere($user, $apirequest);

        $result = $this->get($user, $apirequest);
        if(!isset($result))
        {
            throw new ApiException("There is no abtesting for this user");
        }
        foreach($data as $key=>$value)
        {
            if($value === NULL)
            {
                unset($data[$key]);
            }
        }
        if(empty($data))
        {
            return;
        }
        $this->table(ABTable::TABLE)->update($data, array("id_abtesting"=>$result["id_abtesting"]));
    }
}
