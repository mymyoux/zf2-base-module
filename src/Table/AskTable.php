<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:23
 */

namespace Core\Table;


use Core\Model\UserModel;
use Zend\Db\Sql\Expression;

/**
 * Class AskTable
 * @package Core\Table
 */
class AskTable extends CoreTable
{

    const TABLE = "ask";
   
   public function ask($data)
   {    
        $keys = array("type", "value","id_external_ask");
        $value = array();
        foreach($data as $key=>$v)
        {
            if(in_array($key, $keys))
            {
                $value[$key] = $v;
            }
        }
        $this->table(AskTable::TABLE)->insert($value);
   }
    public function askAPI($user, $apirequest)
   {    
        $keys = array("type", "value","id_external_ask");
        $value = array();
        foreach($apirequest->params as $key=>$v)
        {
            if(in_array($key, $keys))
            {
                $value[$key] = $v->value;
            }
        }
        $this->table(AskTable::TABLE)->insert($value);
   }
   public function answer($id_ask, $answer)
   {
        $keys = array("answer", "id_external_answer", "id_user");
        $value = array();
        foreach($answer as $key=>$v)
        {
            if(in_array($key, $keys))
            {
                $value[$key] = $v;
            }
        }
        $this->table(AskTable::TABLE)->update($answer, array("id_ask"=>$id_ask));
   }
   public function answerAPI($user, $apirequest)
   {

        $keys = array("answer", "id_external_answer", "id_user");
        $value = array();
        foreach($apirequest->params as $key=>$v)
        {
            if(in_array($key, $keys))
            {
                $value[$key] = $v->value;
            }
        }
        $value["id_user"] = $user->getRealID();
        $this->table(AskTable::TABLE)->update($value, array("id_ask"=>$apirequest->params->id_ask->value));
   }
   public function getAllTypes($user, $apirequest)
   {
        $request = $this->select(array("ask"=>AskTable::TABLE))
        ->columns(array("type"))
        ->group(array("type"));
        $result = $this->execute($request);
        return array_map(function($item)
            {
                return $item["type"];
            }, $result->toArray());
   }
   public function getAll($user, $apirequest)
   {
        $request = $this->select(array("ask"=>AskTable::TABLE));
        $where = NULL;
        if($apirequest->params->non_answered->value === True)
        {
            $where = $this->select()->where->isNull("answer")->and->isNull("id_external_answer");
        }
        if(isset($apirequest->params->type->value))
        {
            if(!isset($where))
            {
                $where = $this->select()->where;      
            }else
            {
                $where = $where->and;
            }
            $where = $where->equalTo("type", $apirequest->params->type->value);
        }
        if(isset($where))
        {
            $request = $request->where($where);
        }

        $request = $apirequest->paginate->apply($request);
        $result = $this->execute($request);
        return $result->toArray();
   }
}
