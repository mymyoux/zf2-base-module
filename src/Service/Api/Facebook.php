<?php
/**
 * Created by PhpStorm.
 * User: jeremy.dubois
 * Date: 03/10/2014
 * Time: 17:28
 */

namespace Core\Service\Api;



use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookSession;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class Facebook extends AbstractAPI implements ServiceLocatorAwareInterface
{
    /**
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    private $sm;
    /**
     * @var bool
     */
    private $initialized = False;
    private $app_id;
    private $app_secret;
    /**
     * @var \Facebook\FacebookSession
     */
    private $session;
    public function __construct($app_id, $app_secret)
    {
        $this->app_id;
        $this->app_secret;
        FacebookSession::setDefaultApplication($app_id, $app_secret);
    }
    public function init()
    {

    }
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        if($this->initialized)
        {
            throw new \Exception("already initialized");
        }
        $this->sm = $serviceLocator;
        $this->init();
        $this->initialized = True;
    }
    public function isAuthenticated()
    {
        if($this->session)
        {
            return True;
        }
        //retrieve given URL
        $url_helper = $this->sm->get("application")->getServiceManager()->get('ViewHelperManager')->get('ServerUrl');
        $url = $url_helper($_SERVER['REQUEST_URI']);
        $position = mb_strpos($url, "?");
        $url = mb_substr($url, 0,$position);
        $helper = new FacebookRedirectLoginHelper($url);
        try {
            $this->session = $helper->getSessionFromRedirect();
        } catch(FacebookRequestException $ex) {
            // When Facebook returns an error
            return False;
        } catch(\Exception $ex) {
            // When validation fails or other local issues
            return False;
        }
        if ($this->session) {
            // Logged in
            return True;
        }

        return False;
    }
    public function getLoginUrl($data)
    {
        $helper = new FacebookRedirectLoginHelper($data["redirect_uri"]);
        $loginUrl = $helper->getLoginUrl();
        //TODO:mixed data and url params
        return $loginUrl;
    }
    /**
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->sm;
    }

    /**
     * @return
     * @throws \Exception
     */
    public function getUser()
    {
            $me = $this->api("/me?fields=first_name,last_name,gender,locale,name,timezone,updated_time,verified,link,email");//->getGraphObject(GraphUser::className());
        $me = toArray($me);
        return $me;
    }
    public function api($path, $params = NULL, $method = "GET", $version = FacebookRequest::GRAPH_API_VERSION, $session = NULL)
    {
        if(empty($session))
        {
            $session = $this->session;
        }
        if(empty($session))
        {
            throw new \Exception("You must be loggued to use Facebook API");
        }
        $request = new FacebookRequest(
            $session, $method, $path, $params, $version
        );
        return $request->execute()->getResponse();
    }
    protected function getDatabaseColumns()
    {
        return array("id","name","first_name","last_name", "email", "link","locale","name","timezone","verified","updated_time","gender");
    }
    /**
     * @inheritDoc
     */
    public function getAccessToken()
    {
        return $this->session->getToken();
    }
}
