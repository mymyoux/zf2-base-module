<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 11/10/2014
 * Time: 19:51
 */

namespace Core\Service;


use Core\Model\UserModel;

class ACL extends CoreService implements \JsonSerializable
{
    const FORBIDDEN = "forbidden";
    /**
     * @var string Type of role (user, admin, candidate, recruitment firm, company)
     */
    private $type;
    /**
     * @var array Category of required roles
     */
    public $categories;
    protected $tree;
    public function newInstance()
    {
        $acl = new ACL();
        $acl->init();
        $acl->setServiceLocator($this->sm);
        $acl->tree = $this->tree;
        return $acl;
    }
    public function init()
    {
        $this->tree = array();
        $this->categories = array();
        /*$identity = $this->sm->get("Identity");
        $this->type = "visitor";
        if($identity->isLoggued())
        {
            if(isset($identity->user->type))
            {
                $this->type =  $identity->user->type;
            }
        }*/
    }

    /**
     * Check if a user or current user is allowed
     * @param string|UserModel|NULL $user If UserModel is given this method will test rights of the given user
     * otherwise it will test current connected user. If a string is given it will be treated as additional role
     * @param ... A list of additional roles than the user must have
     * @return bool allowed or not
     */
    public function is_allowed($user = NULL)
    {

        $start = 1;
        if(is_string($user))
        {
            $start = 0;
            $user = NULL;
        }
        if(!isset($user))
        {
            $user = $this->sm->get("Identity")->user;
        }
        $roles = isset($user)?$this->_level($user->getRoles()):array("visitor");

        $no_category = True;
        if(isset($this->categories[ACL::FORBIDDEN]))
        {
            $forbidden = $this->categories[ACL::FORBIDDEN];
            foreach($forbidden as $role)
            {
                if(in_array($role, $roles))
                {
                    return False;
                }
            }
        }
        foreach($this->categories as $key=>$categoryRoles)
        {
            if($key == ACL::FORBIDDEN)
            {
                continue;
            }
            $no_category = False;
            $needed = $categoryRoles;
            for($i=$start;$i<func_num_args();$i++) {
                $role = func_get_arg($i);
                if(!in_array($role, $needed))
                    $needed[]= $role;
            }

            foreach($needed as $role)
            {
                if(!in_array($role, $roles))
                {
                    continue 2;
                }
            }
            return True;
        }
        return $no_category;
    }
    /**
     * Check if a user or current user is allowed by a specific category
     * @param string category's name
     * @param string|UserModel|NULL $user If UserModel is given this method will test rights of the given user
     * otherwise it will test current connected user. If a string is given it will be treated as additional role
     * @param ... A list of additional roles than the user must have
     * @return bool allowed or not
     */
    public function is_allowed_on_category($category, $user = NULL)
    {
        if(!array_key_exists($category, $this->categories))
        {
            return True;
        }

        $start = 2;
        if(is_string($user))
        {
            $start = 1;
            $user = NULL;
        }
        if(!isset($user))
        {
            $user = $this->sm->get("Identity")->user;
        }
        $roles = isset($user)?$this->_level($user->getRoles()):array("visitor");



        $needed = $this->categories[$category];
        for($i=$start;$i<func_num_args();$i++) {
            $role = func_get_arg($i);
            if(!in_array($role, $needed))
                $needed[]= $role;
        }
        foreach($needed as $role)
        {
            if(!in_array($role, $roles))
            {
                return False;
            }
        }
        return True;
    }

    /**
     * Tests if a user is allowed on the main category
     * @param $user UserModel
     * @return bool
     */
    public function is_main(UserModel $user = NULL)
    {
        return $this->is_category("main", $user);
    }

    /**
     * Tests if a user is admin
     * @param $user UserModel
     * @return bool
     */
    public function is_admin(UserModel $user = NULL)
    {
        if(!isset($user))
        {
            $user = $this->sm->get("Identity")->user;
        }
        if(isset($user))
        {
            $roles = $user->getRoles();
            return in_array("admin", $roles);
        }
        return False;
    }
    /**
     * Tests if a user is allowed on a specific category
     * @param $user UserModel
     * @return bool
     */
    public function is_category($category, UserModel $user = NULL)
    {
        return $this->is_allowed_on_category($category, $user);
    }

    /**
     * Gets first category that match
     * @param UserModel|NULL $user
     * @return string|NULL
     */
    public function get_category($user = NULL)
    {
        foreach($this->categories as $key=>$value)
        {
            if($this->is_allowed_on_category($key, $user))
            {
                return $key;
            }
        }
        return NULL;
    }

    /**
     * Gets available categories list
     * @return array
     */
    public function get_category_list()
    {
        return array_keys($this->categories);
    }

    /**
     * Gets available categories list width children
     * @return array
     */
    public function get_categories()
    {
        return $this->categories;
    }

    /**
     * Sets descendants roles for a role
     * @param $role string role's name
     * @param $children array children's role of role. role = role UNION children's role
     */
    public function add_role_definition($role, $children)
    {
        if(!is_string($role))
        {
            dd($role);
        }
        $this->tree[$role] = $children;
    }

    /**
     * Adds role as allowed
     * @param $role string
     * @param $category string category for the allowed role
     */
    public function add($role, $category = "main")
    {
        if(!array_key_exists($category, $this->categories))
        {
            $this->categories[$category] = array();
        }
        if(!in_array($role, $this->categories[$category]))
        {
            $this->categories[$category][] = $role;
        }
    }
    /**
     * Removes role as allowed
     * @param $role string
     * @param $category string category for the removed role
     */
    public function remove($role, $category = "main")
    {

        if(!array_key_exists($category, $this->categories))
        {
            return;
        }
        $index = array_search($role, $this->categories[$category]);
        if($index !== FALSE)
        {
            array_splice($this->categories[$category], $index, 1);
        }
    }
    ///PRIVATE FUNCTIONS
    /**
     * Unfold parent's role
     * @param $roles array roles to unfold
     * @return array
     */
    private function _level($roles)
    {
        $leveled = array();
        foreach($roles as $role)
        {
            if(array_key_exists($role, $this->tree))
            {
                //recursive tree
                $leveled = array_merge($leveled, $this->_level($this->tree[$role]));
            }
            $leveled[] = $role;
        }
        return array_unique($leveled);
    }
    public function getRolesFromCategory($category)
    {
        if(isset($this->tree[$category]))
        {
            return $this->tree[$category];
        }   
        return NULL;
    }
    public function addRoles($user)
    {
        if(!isset($user))
        {
            return;
        }
        $roles = $user->getRoles();

        foreach($roles as $role)
        {
            $subroles = $this->getRolesFromCategory($role);
            if(isset($subroles))
            {
                foreach($subroles as $subrole)
                {
                    $user->addRole($subrole);
                }
            }
        }
            
    }
    public function __debugInfo()
    {
        return array("__class"=>"ACL", "categories"=>$this->categories, "roles_tree"=>$this->tree);
    }
    public function jsonSerialize() {
        return array("__class"=>"ACL", "categories"=>$this->categories, "roles_tree"=>$this->tree);
    }

}
