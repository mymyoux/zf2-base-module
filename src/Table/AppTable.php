<?php
namespace Core\Table;


use Core\Model\UserModel;
use Core\Table\UserTable;
use Core\Exception\FatalException;
use Core\Table\CoreTable;
use Zend\Db\Sql\Select;
use Core\Annotations as ghost;
use Zend\Db\Sql\Expression;
use Core\Exception\ApiException;


class AppTable extends CoreTable
{
	/**
	 * App
	 */
	const TABLE = "app";
	/**
	 * App user
	 */
	const TABLE_USER = "app_user";
    public function createAppUser($user, $app_name = NULL)
    {
        if(isset($app_name))
        {
            if(is_numeric($app_name))
            {
                $id_app = (int)$app_name;
            }else
            {
                 $app = $this->getAppFromName($app_name);
                 if(!isset($app))
                 {
                    throw new \Exception("app ".$app_name." doesn't exist");
                 }
                 $id_app = $app->id_app;
            }
        }else
        {
            $this->sm->get('Route')->setServiceLocator($this->sm);
            $app_name = $this->sm->get("Route")->getRouteType();
            $app = $this->getAppFromName($app_name);
            if(!isset($app))
            {
                return;
            }
            $id_app = $app->id_app;
        }
         $this->table(AppTable::TABLE_USER)->insert(array("last_connection"=>new Expression("NOW()"),'num_connection'=>0,"id_user"=>$user->id, "id_app"=> $id_app ));
         return $this->table(AppTable::TABLE_USER)->lastInsertValue;
    }
    public function getAppUser($id_app_user)
    {
        $request = $this->select(["app_user"=>AppTable::TABLE_USER])
        ->join(["u"=>UserTable::TABLE_USER],"u.id_user = app_user.id_user", ["picture","email","furniture","first_name","last_name"])
        ->where(array("app_user.id_app_user"=>$id_app_user))->limit(1);

        $result = $this->execute($request);
        $result = $result->current();
        if($result == False)
        {
            return NULL;
        }
        return $result;
    }
     public function getAppData($user, $app)
    {
        return $user;
    }
    public function getUser($id_app_user)
    {
        $app_user = $this->table(AppTable::TABLE_USER)->selectOne(["id_app_user"=>$id_app_user]);
        if(!isset($app_user))
        {
            return NULL;
        }
        $user = $this->getUserTable()->getUser($app_user["id_user"]);
        if(isset($user))
        {
            $user->id_app = $app_user["id_app"];
            $user->id_app_user = $app_user["id_app_user"];
        }
        return $user;
    }
    public function updateLoginConnection($user)
    {
        if(!isset($user))
        {
            return;
        }
         if($this->sm->get("Identity")->isLoggued() &&  $this->sm->get("Identity")->user->isAdmin())
        {
            //don't log admin
            return;
        }
        if(!isset($user->id_app))
        {
            $this->sm->get('Route')->setServiceLocator($this->sm);
            $app_name = $this->sm->get("Route")->getRouteType();
            $app = $this->getAppFromName($app_name);
            if(!isset($app))
            {
                return;
            }
            $id_app = $app->id_app;
        }else
        {
            $id_app = $user->id_app;
        }
        $request = array("id_user"=>$user->id, "id_app"=>$id_app);
        if(isset($user->id_app_user))
        {
                $id_app_user = $user->id_app_user;
        }else
        {
            $existing_user = $this->table(AppTable::TABLE_USER)->selectOne($request);
            if(isset($existing_user))
            {
                $id_app_user = $existing_user["id_app_user"];
            }
        }
        if(!isset($id_app_user))
        {
            $this->createAppUser($user, $id_app);
        }else
        {
              $this->table(AppTable::TABLE_USER)->update(array("last_connection"=>new Expression("NOW()")), array("id_app_user"=>$id_app_user));
        }
    }
    public function updateConnectionCount($user)
    {
        if(!isset($user))
        {
            return;
        }
         if($this->sm->get("Identity")->isLoggued() &&  $this->sm->get("Identity")->user->isAdmin())
        {
            //don't log admin
            return;
        }
        if(!isset($user->id_app))
        {
            $this->sm->get('Route')->setServiceLocator($this->sm);
            $app_name = $this->sm->get("Route")->getRouteType();
            $app = $this->getAppFromName($app_name);
            if(!isset($app))
            {
                return;
            }
            $id_app = $app->id_app;
        }else
        {
            $id_app = $user->id_app;
        }
        $request = array("id_user"=>$user->id, "id_app"=>$id_app);
        if(isset($user->id_app_user))
        {
                $id_app_user = $user->id_app_user;
        }else
        {
            $existing_user = $this->table(AppTable::TABLE_USER)->selectOne($request);
            if(isset($existing_user))
            {
                $id_app_user = $existing_user["id_app_user"];
            }
        }
        if(!isset($id_app_user))
        {
            $this->createAppUser($user, $id_app);
        }else
        {
              $this->table(AppTable::TABLE_USER)->update(array("num_connection"=>new Expression("num_connection + 1")), array("id_app_user"=>$id_app_user));
        }
    }
    public function getAppFromIDAppUser($id_app_user)
    {
        $request = $this->select(["app_user"=>AppTable::TABLE_USER])
        ->columns([])
        ->join(["app"=>AppTable::TABLE], "app.id_app = app_user.id_app",
            ["*"])
        ->where(["app_user.id_app_user"=>$id_app_user])
        ->limit(1);

        $result = $this->execute($request);
        $result = $result->current();
        return $this->toModel($result !== False?$result : NULL);
    }
    public function getAppFromID($id_app)
    {
        return $this->toModel($this->table(AppTable::TABLE)->selectOne(["id_app"=>$id_app]));
    }
    public function getAppFromName($name)
    {
        return $this->toModel($this->table(AppTable::TABLE)->selectOne(["name"=>$name]));
    }
    public function getUserTable()
    {
        return $this->sm->get("UserTable");
    }

}
