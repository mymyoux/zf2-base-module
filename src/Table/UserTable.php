<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 15:16
 */
namespace Core\Table;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Core\Model\UserModel;

class UserTable extends CoreTable{

    const TABLE = "user";
    const TABLE_API = "user_api";
    const TABLE_MANUAL = "user_network_manual";
    const TABLE_TOKEN = "user_login_token";

    public function createAPI($user, $apirequest)
    { //test

        $user = $this->getUsersFromEmail($apirequest->params->email->value);
        if(!empty($user))
        {
            $user = $user[0];
            throw new ApiException("Email already registered for user ".$user["id_user"]);
        }
        $keys = ["first_name", "last_name", "email", "type"];
        $data = [];
        foreach($keys as $key)
        {
            if(isset($apirequest->params->$key->value))
            {
                $data[$key] = $apirequest->params->$key->value;
            }
        }
        $id_user = $this->createUser($data, $keys);
        $user = $this->getUser($id_user);
        return ["id_user"=>$id_user,"token"=>$user->token];
    }
    public function getUserFromLoginToken($token)
    {
        $result = $this->table(UserTable::TABLE_TOKEN)->select(array("token"=>$token));
        $result = $result->current();
        if($result === False)
        {
            return NULL;
        }
        return $this->getUser($result["id_user"]);
    }
    public function getIDUserByEmail($email)
    {
        if(!isset($email))
        {
            return NULL;
        }
        $email = trim($email);
        if(mb_strlen($email) == 0)
        {
            return NULL;
        }
        $result = $this->table(UserTable::TABLE)->select(array("email"=>$email));
        $result = $result->current();
        if($result !== False)
        {
            return $result;
        }
        $result = $this->table(UserTable::TABLE_MANUAL)->select(array("email"=>$email));
        $result = $result->current();
        if($result !== False)
        {
            return $result;
        }
        $apiManager = $this->sm->get("APIManager");
        $apis = $apiManager->getAll();
        foreach($apis as $api)
        {
            if($apiManager->canLogin($api))
            {
                 $result = $this->table(UserTable::TABLE."_network_".$api)->select(array("email"=>$email));
                 $result = $result->current();
                 if($result !== False)
                 {
                    return $result;
                 }
            }
        }
        return NULL;
    }
    public function authenticate($email, $password)
    {
        $user = $this->getIDUserByEmail($email);
        if(!isset($user))
        {
            throw new \Exception("no_email");
            return NULL;
        }
        $result = $this->table(UserTable::TABLE_MANUAL)->select(array("id_user"=>$user["id_user"]));
        $result = $result->current();
        if($result === False)
        {
             $apis = $this->getApis($user["id_user"]);
            if(!empty($apis))
            {
                throw new \Exception("bad_network.use_".$apis[0]);
                return NULL;
            }else
            {
                //can't happen
                throw new \Exception("unknown");
                return NULL;
            }
        }
        $password_good = password_verify(trim($password), $result["password"]);
        if(!$password_good)
        {
            throw new \Exception("bad_password");
            return NULL;
        }

        return $this->getUser($result["id_user"]);

    }

    /**
     * Gets user from its id_user
     * @param $id_user ID User
     * @return \Core\Model\UserModel
     */
    public function getUser($id_user)
    {
        $request = $this->select(array("u"=>UserTable::TABLE))
        ->join(array("t"=>UserTable::TABLE_TOKEN), "t.id_user = u.id_user",array("token"))
        ->where(array("u.id_user"=>$id_user,"deleted"=>0))->limit(1);
        $result = $this->execute($request);
        $result = $result->current();
        if($result === False)
        {
            return NULL;
        }
        $user = new UserModel();
        $user->setIsIdentity(true);
        $user->exchangeArray($result);
        return $user;
    }

    /**
     * Gets user from its api id or
     * @param $api
     * @param $id_api
     * @return array|\ArrayObject|null
     * @throws Exception
     */
    public function getUserFromAPIID($api, $id_api)
    {
        if(!$this->sm->get("APIManager")->isSharable($api))
        {
            $api = mb_strtolower($api);
            $user_api = $this->table($api)->select(array("id_".$api=>$id_api))->current();
            if($user_api == False)
            {
                return NULL;
            }
            $user = $this->getUser($user_api->id_user);//$this->table()->select(array("id_user"=>$user_api->id_user))->current();
            if($user == False)
            {
                throw new Exception("User API found but not app user : impossible - ".$api."_id=".$id_api);
                return NULL;
            }
            $user->addAPI($api, $user_api);
            return $user;

        }else
        {

        }
    }
    public function getAPIURL(UserModel $user, $network)
    {
        $result = $this->table($network)->select(array("id_user"=>$user->id));
        $result = $result->current();
        if($result === False)
        {
            return NULL;
        }
        if(array_key_exists("link", $result))
        {
            return $result["link"];
        }
        return NULL;
    }
    public function getUserFromEmail($email, $api = NULL)
    {
        if(!isset($email))
        {
            return NULL;
        }
        $suffix = NULL;
        if(isset($api))
        {
            $suffix = "_network_".$api;
        }
        $user = $this->table()->select(array("email"=>$email))->current();
        if($user === False)
        {
            $user = $this->table(isset($suffix)?UserTable::TABLE.$suffix:UserTable::TABLE_MANUAL)->select(array("email"=>$email))->current();
            if($user !== False)
            {
                $user = $this->getUser($user["id_user"]);
                if($user !== False)
                {
                    return $user;
                }
            }
            return NULL;
        }
        return $user;
    }

    public function getUserByEmail($email, $model = True)
    {
        $users = $this->getUsersFromEmail($email);
        if(empty($users))
        {
            return NULL;
        }   
        $user = $users[0];
        if(!$model)
        {
            return $user;
        }
        $user = $this->getUser($user);
        return $user;
    }
    /**
     * Gets User from All sources (object)
     * @param  [type] $email [description]
     * @param  [type] $apis  [description]
     * @return [type]        [description]
     */
    public function getUsersFromEmail($email, $apis = NULL)
    {

        if(!isset($email))
        {
            return array();
        }
        if(!isset($apis))
        {
            $api_manager = $this->sm->get("APIManager");
            $apis = $api_manager->getAllLoggable();
            $apis[] = "manual";
        }

        $users = $this->table()->select(array("email"=>$email))->toArray();
        $ids = array_map(function($item)
        {
            return $item["id_user"];
        }, $users);

        if(!empty($ids))
        {
            $where = $this->select()->where->notIn("id_user", $ids);
        }
        foreach($apis as $api)
        {

            $request = $this->select(UserTable::TABLE."_network_".$api);
            if(!empty($where))
            {
                $request = $request->where($where);
            }
            $request = $request->where(array("email"=>$email));
            $result = $this->execute($request);
            $result = $result->toArray();

            $ids = array_merge($ids, array_map(function($item)
            {
                return $item["id_user"];
            }, $result));
            if(!empty($where))
            {
                $where = $this->select()->where->notIn("id_user", $ids);
            }
            $users = array_merge($users, $result);
        }
        return $users;
    }
     public function getAPIUserFromUserID($api, $id_user)
     {
         if(!isset($id_user))
         {
             return NULL;
         }
         $row = $this->table($api)->select(array("id_user"=>$id_user));
         $user = $row->current();
         if($user === False)
         {
             return NULL;
         }
         return $user;
     }
     public function updateAccessToken($access_token, $api)
     {
        return $this->updateUser(array("access_token"=>$access_token), $api);
     }
    public function updateUser($data, $api = NULL, $user = NULL)
    {
        if(!isset($data) || (!$this->sm->get("Identity")->isLoggued() && !isset($user)))
        {
            return False;
        }
        if(isset($api))
            $data = $this->cleanUser($data, $api);
        if(!isset($user))
        {
            $id_user = $this->sm->get("Identity")->user->id;
        }else{
            if($user instanceof UserModel)
            {
                $id_user = $user->id;
            }else
            {
                //id_user
                $id_user = $user;
            }
        }
        $key = $api;
        if(!isset($key))
        {
            $key = "user";
        }
        $meta_keys = array("twitter"=>array("id_twitter","name","access_token","access_token_secret","followers_count","friends_count","screen_name","link"),
            "linkedin"=>array("id_linkedin","headline","first_name","last_name","access_token","email","link"),
            "manual"=>array("email","password"),
            "facebook"=>array("id_facebook","last_name","first_name","link","locale","name","timezone","verified","gender","access_token","email"),
            "user"=>array("first_name","last_name","type","email","picture"),
            "smartrecruiters"=>array("first_name","last_name","role","email","active", 'id_smartrecruiters', 'access_token', 'refresh_token'),
            "greenhouse"=>array("first_name","last_name","email", 'id_greenhouse', 'access_token', 'harvest_key'),
        );
        //$keys = array("first_name","last_name","email","picture", "access_token");
        $keys = $meta_keys[$key];
        $user_data = array();
        foreach($keys as $key)
        {
            if(isset($data[$key]))
            {
                $user_data[$key] = $data[$key];
            }
        }

        if(sizeof($user_data)==0)
        {
            return;
        }

        $this->table($api)->update($user_data, array("id_user"=>$id_user));
    }
    public function createUserFromManual($data)
    {
        if(!isset($data) || !array_key_exists("email",$data) || !array_key_exists("password",$data) )
        {
            throw new \Exception("email_password_required");
            return;
        }

        $result =$this->table()->select(array("email"=>trim($data["email"])));
        $result = $result->current();
        if($result !== False)
        {
            throw new \Exception("email.already_exists");
        }



        $data["password"] = $this->getHashedPassword(trim($data["password"]));



        $id_user = $this->createUser($data);
        $data = array("email"=>$data["email"], "password"=>$data["password"], "id_user"=>$id_user);
        //add api manual pour permettre une récupération correcte des data après
        //
        $this->table(UserTable::TABLE_MANUAL)->insert($data);
        $this->_addAPIToUserAPIList("manual", $id_user);
        return $id_user;
    }
    protected function getHashedPassword($password)
    {
        return password_hash($password, \PASSWORD_DEFAULT);
    }
    public function updateFromManual($id_user, $data)
    {
        if(!isset($data))
        {
            throw new \Exception("no_data");
            return;
        }
        $manual_data = array();
        $manual_data["email"] = trim($data["email"]);
        $manual_data["password"] = $data["password"];//$this->getHashedPassword(trim($data["password"]));
        $manual_data["id_user"] = $id_user;
        $this->addAPIToUser("manual", $manual_data, $id_user);
        $this->updateUser($data, NULL, $id_user);
    }
    public function createUserFromAPI($api, $data)
    {
        if(!isset($data))
        {
            return FALSE;
        }
        $api_id = "id_".$api;
        $data = $this->cleanUser($data, $api);
        $underScoreData = array();
        foreach($data as $key=>$value)
        {
            $underScoreData[from_camel_case($key)] = $data[$key];
        }
        $id_user = $this->createUser($underScoreData);
        $underScoreData["id_user"] = $id_user;
        $this->table($api)->insert($underScoreData);

        $this->_addAPIToUserAPIList($api, $id_user);
    }
     private function cleanUser($user, $api)
     {
         $api_id = "id_".$api;
         if(!array_key_exists($api_id, $user) && array_key_exists("id", $user))
         {
             $user[$api_id] = $user["id"];
             unset($user["id"]);
         }
         return $user;
     }
    public function addAPIToUser($api, $data, $id_user = NULL)
    {
        if(!isset($data) || (!$this->sm->get("Identity")->isLoggued() && !isset($id_user)))
        {
            return False;
        }
        if($api == "manual")
        {
            $api_id = "id_user";
            $data["password"] = $this->getHashedPassword(trim($data["password"]));
        }else
        {
            $api_id = "id_".$api;
        }
        if(!array_key_exists($api_id, $data) && array_key_exists("id", $data))
        {
            $data[$api_id] = $data["id"];
            unset($data["id"]);
        }
        if(!isset($id_user))
        {
            $id_user = $this->sm->get("Identity")->user->id;
        }
        $row = $this->table($api)->select(array($api_id=>$data[$api_id]));
        //throw new \Exception("argh");
        if(($row=$row->current())!==False && $row["id_user"] != $id_user && !$this->sm->get("APIManager")->isSharable($api))
        {
            throw new Exception("api.already_used");
        }
        if($row["id_user"] == $id_user)
        {
            //already connected to this account api
            return;
        }
        $row = $this->table($api)->select(array("id_user"=>$id_user));
        if(($row=$row->current())!==False)
        {
            throw new Exception("api.already_added");
        }
        $underScoreData = array();
        foreach($data as $key=>$value)
        {
            $underScoreData[from_camel_case($key)] = $data[$key];
        }
        if($api != "manual")
        {
            $underScoreData["access_token"] = $this->sm->get("Identity")->$api->getAccessToken();

            if ($api === 'twitter')
            {
                $underScoreData["access_token_secret"] = $this->sm->get("Identity")->$api->getAccessTokenSecret();
            }
        }
        $underScoreData["id_user"] = $id_user;



        $this->table($api)->insert($underScoreData);

        $this->_addAPIToUserAPIList($api, $id_user);
    }

     /**
      * Flag the API into user_api table
      */
    private function _addAPIToUserAPIList($api, $id_user = NULL)
     {
         if(!isset($id_user))
         {
             $id_user = $this->sm->get("Identity")->user->id;
         }
         $this->table(UserTable::TABLE)->update(array("temp"=>0), array("id_user"=>$id_user));
        $update = $this->update("user_api")->where(
            array("id_user"=>$id_user
        ))->set(array($api=>True));

        $this->execute($update);
    }
    public function createUser($data, $keys = array("id_user", "first_name", "last_name", "email"))
    {

        if(empty($keys))
        {
            $keys = array_keys($data);
        }
        $insert = array();
        foreach($keys as $key)
        {
            if(array_key_exists($key, $data))
                $insert[$key] = $data[$key];
        }

        $data = $this->additionalCreationData();

        foreach($data as $key => $value)
        {
            //additional data can't override given data
            if(!array_key_exists($key, $insert))
                $insert[$key] = $value;
        }
        //if id_user is already specified
        if(isset($this->session->createUser()->id_user))
        {
            $id_user = $this->session->createUser()->id_user;
            unset($this->session->createUser()->id_user);
            $this->table()->update($insert, array("id_user"=>$id_user));
        }else
        {
            $this->table()->insert($insert);
            $id_user =  $this->table()->lastInsertValue;
            $insert = $this->insert("user_api")->columns(array("id_user"))->values(array("id_user"=>$id_user));
            $this->execute($insert);
        }






        return $id_user;
    }

     public function removeApiToUser($api, $id_user = NULL)
     {
         if(!isset($id_user))
         {
             if(!$this->sm->get("Identity")->isLoggued())
             {
                 return False;
             }
             $id_user = $this->sm->get("Identity")->user->id;
         }
         //we check if user has another "strong" account left
         $apis = $this->getApis($id_user);
         /** @var \Core\Service\ApiManager $api_manager APIManager */
         $api_manager = $this->sm->get("APIManager");
         if($api_manager->canLogin($api))
         {
             $found = False;
             foreach($apis as $_api)
             {
                 if($api!=$_api && $api_manager->canLogin($_api) === True)
                 {
                     $found = True;
                     break;
                 }
             }
             if(!$found)
             {
                 throw new Exception("You can't delete this account or you will not be able to reconnect again");
                 //no other account
                 return False;
             }
         }

         $update = $this->update("user_api")->set(array($api=>0));
         $this->execute($update);
         $this->table($api)->delete(array("id_user"=>$id_user));

     }

     /**
      * Gets the list of API accounts of the user
      * @param $id_user int User id
      */
     public function getApis($id_user = NULL, $excludes = array("manual"))
     {

         if(!isset($id_user))
         {
             $id_user = $this->sm->get("Identity")->user->id;
         }
            $select = $this->select("user_api")->where(array("id_user"=>$id_user));

         $row = $this->execute($select);
         if($row->current()!==False)
         {
             $data = $row->current();
             unset($data["id_user"]);
             unset($data["updated_time"]);
             unset($data["created_time"]);
             $apis = array();
             foreach($data as $api=>$enabled)
             {
                 if(!in_array($api, $excludes))
                 {
                     if($enabled)
                     {
                         $apis[] = $api;
                     }
                 }
             }
             return $apis;
         }
         return array();
     }

     /**
      * Additional data (key=>value) to set on the user creation
      * @return array
      */
     protected function additionalCreationData()
     {
         return array();
     }

}
