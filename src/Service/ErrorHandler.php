<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 11/10/2014
 * Time: 19:10
 */

namespace Core\Service;


use Core\Exception\Exception;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

class ErrorHandler extends CoreService implements ServiceLocatorAwareInterface{
    public function handleError(MvcEvent $event, $status = NULL)
    {
        $this->sm = $event->getApplication()->getServiceManager();
        //get the exception
        $exception = $event->getParam('exception');

        try {
            if(isset($exception))
                $this->getErrorTable()->logError($exception);
        }catch(\Exception $e)
        {

        }
        if($status == 404)
        {
            Header('Location:/404.html');
            exit();
        }
        //throw $exception;
        //...handle the exception... maybe log it and redirect to another page,
        //or send an email that an exception occurred...
    }
    public function handleRenderError(MvcEvent $event)
    {
        //force 404 error status
        $response = $event->getResponse();
        $response->setStatusCode(404);
        $response->sendHeaders();
        return $this->handleError($event);
    }

    /**
     * @return \Core\Table\ErrorTable
     */
    public function getErrorTable()
    {
        return $this->sm->get("ErrorTable");
    }
}
