<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 21/10/14
 * Time: 10:58
 */

namespace Core\Controller;
use Core\Annotations as ghost;
use Zend\View\Model\JsonModel;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


/**
 * @ghost\Table(name="UserTable")
 * @return JsonModel
 */
class UserController extends FrontController
{
    /**
     * @ghost\Roles("user")
     * @ghost\Table(name="PushTable", method="pushRegistration", useDoc=true)
     */
    public function pushRegistrationAPIPOST()
    {

    }
      /**
     * @ghost\Back
     * @ghost\Table(name="PushTable", method="getPushRegistrations", useDoc=true)
     */
    public function getPushRegistrationsAPIGET()
    {

    }
}
