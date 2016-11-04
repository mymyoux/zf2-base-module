<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 08/10/2014
 * Time: 21:43
 */

namespace Core\Service\Api;

use Zend\Http\Request as ZendRequest;
use Core\Service\Api\AbstractAPI;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use GuzzleHttp\Post\PostFile;

class Gmap extends AbstractAPI implements ServiceLocatorAwareInterface
{
    /**
     * @var \Twitter\Twitter
     */
    private $api;
    private $consumer_key;

    private $access_token;

    private $user = null;

    private $path = 'https://maps.googleapis.com/maps/api/place/';

    public function __construct($key)
    {
        $this->client 	= new \GuzzleHttp\Client();
        $this->key     	= $key;
    }

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->sm = $serviceLocator;

        $this->init();
    }

    public function getServiceLocator()
    {
        return $this->sm;
    }

    public function init()
    {
        $apis           = $this->sm->get('AppConfig')->get('apis');
        $this->config   = $apis['gmap'];
    }

    public function post( $ressource, $_params = [] )
    {
        return $this->request('POST', $ressource, ['body' => $_params]);
    }

    public function put( $ressource, $_params = [] )
    {
        return $this->request('PUT', $ressource, ['json' => $_params]);
    }

    public function json( $ressource, $_params = [] )
    {
        return $this->request('POST', $ressource, ['json' => $_params]);
    }

    public function get( $ressource, $_params = [] )
    {
        return $this->request('GET', $ressource, ['query' => $_params]);
    }

    public function request( $method, $ressource, $_params )
    {
        $path   = $this->path ;//'https://api.smartrecruiters.com/';

        try
        {
            $params = $_params;

            if (!isset($params['query']))
                $params['query'] = ['key' => $this->key];
            else
                $params['query']['key'] = $this->key;

            $ressource .= '/json';

            $this->sm->get('Log')->normal('[' . $method . '] ' . $path . $ressource . ' ' . json_encode($params));

            $data = $this->client->{ strtolower($method) }($path . $ressource, $params);
        }
        catch (\Exception $e)
        {
            throw $e;
        }

        $data   = $data->json();

        return $data;
    }

    /**
     * Must be called when the callback url for an api is called
     * @param Request $request
     */
    public function callbackRequest(ZendRequest $request)
    {
        return NULL;
    }

    public function getUser()
    {
        return null;//$this->user;
    }

    protected function getDatabaseColumns()
    {
        return [];//array("id","name", "email", "first_name", "last_name", "access_token", 'refresh_token', "role", "active");
    }
}

class FullContactException extends \Exception
{

}
