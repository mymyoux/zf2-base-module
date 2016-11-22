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
 * @ghost\Table(name="AskTable")
 * @return JsonModel
 */
class AskController extends FrontController
{
    /**
     * /ask/get [GET]
     * @ghost\Param(name="non_answered", requirements="boolean", required=false, value=true)
     * @ghost\Param(name="type", required=false)
     * @ghost\Paginate(allowed="id_ask,created_time,updated_time",key="created_time",direction=1, limit=10)
     * @ghost\Table(method="getAll")
     * @return JsonModel
     */
    public function getAPIGET()
    {
    }
    /**
    * @ghost\Table(method="getAskByIDAPI",useDoc=true)
    */
    public function getByIdAPIGET()
    {
    }
    /**
    * @ghost\Table(method="getAskByExternalID",useDoc=true)
    */
    public function getByExternalIdAPIGET()
    {
    }
     /**
     * /ask/gettypes [GET]
     * @ghost\Table(method="getAllTypes")
     * @return JsonModel
     */
    public function gettypesAPIGET()
    {
    }

    /**
     * /ask/add [POST]
     * @ghost\Param(name="type", required=true)
     * @ghost\Param(name="value", required=false)
     * @ghost\Param(name="id_external_ask", required=false)
     * @return JsonModel
     */
    public function addAPIPOST()
    {
        $request            = $this->params('request');
        $identity_user      = $request->user;

        $this->sm->get('Notifications')->ask($request->params->type->value, $request->params->value->value, $request->params->id_external_ask->value);

        return $this->sm->get('AskTable')->askAPI($identity_user, $request);
    }
    /**
     * /ask/answer [POST]
     * @ghost\Param(name="id_ask", required=true, requirements="\d+")
     * @ghost\Param(name="answer", required=false)
     * @ghost\Param(name="id_external_answer", required=false)
     * @ghost\Table(method="answerAPI")
     * @return JsonModel
     */
    public function answerAPIPOST()
    {
    }
}
