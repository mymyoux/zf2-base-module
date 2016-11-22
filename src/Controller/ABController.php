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


/**
 * @ghost\Table(name="ABTable")
 * @return JsonModel
 */
class ABController extends FrontController
{
    public function testAPIGET()
    {
        $model = $this->sm->get("ABTesting")->create("test", 4, NULL, 5);
        $model->result = "ok";
        $model->state = "end_state";
        $model->save();
    }
    /**
     * /ab/create [POST]
     * @ghost\Param(name="name", required=true)
     * @ghost\Param(name="test", required=false)
     * @ghost\Param(name="version", required=false)
     * @ghost\Table(method="create")
     * @return JsonModel
     */
    public function createAPIPOST()
    {
    }
    /**
     * /ab/get [GET]
     * @ghost\Param(name="name", required=false)
     * @ghost\Param(name="id_abtesting", required=false)
     * @ghost\Param(name="test", required=false)
     * @ghost\Param(name="version", required=false)
     * @ghost\Table(method="get")
     * @return JsonModel
     */
    public function getAPIGET()
    {

    }
    /**
     * /ab/update [POST]
     * @ghost\Param(name="id_abtesting", required=false)
     * @ghost\Param(name="name", required=false)
     * @ghost\Param(name="test", required=false)
     * @ghost\Param(name="version", required=false)
     * @ghost\Param(name="previous", required=false)
     * @ghost\Param(name="value", required=false)
     * @ghost\Param(name="result", required=false)
     * @ghost\Param(name="state", required=false)
     * @ghost\Table(method="updateAB")
     * @return JsonModel
     */
    public function updateAPIPOST()
    {

    }
}
