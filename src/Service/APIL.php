<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 27/09/2014
 * Time: 16:12
 */

namespace Core\Service;


use Core\Service\Api\Request;
use Core\Annotations\Paginate;
use Core\Annotations\Doc;
use Core\Annotations\Table;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\View\Variables;
use Zend\View\Model\ConsoleModel;
use Core\Exception\ApiException;
use Core\Jobs\Api as JobAPI;
/**
 * Class API
 * @package Core\Service
 */
class APIL extends \Core\Service\CoreService implements ServiceLocatorAwareInterface
{
    protected $path;
    protected $id_user;
    protected $params;
    public function __construct()
    {
    }

    public function path($path)
    {
        $this->path = $path;
        return $this;
    }
    public function params($params)
    {
        $this->params = $params;
        return $this;
    }
    public function param($name, $value)
    {
        if(!isset($this->params))
        {
            $this->params = [];
        }
        $this->params[$name] = $value;
        return $this;
    }
    public function user($user)
    {
        $this->id_user = $user->id;
        return $this;
    }
    public function send()
    {
        $job = $this->sm->get('QueueService')->createLJob(JobAPI::class, ["path"=>$this->path,"api_user"=>$this->id_user,"params"=>$this->params]);
        $job->send();
        $this->id_user = NULL;
        $this->path = NULL;
        $this->params = NULL;
    }
}
