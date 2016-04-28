<?php

namespace Okeanrst\FastCGIZF2Adapter;

use PHPFastCGI\FastCGIDaemon\KernelInterface;
use PHPFastCGI\FastCGIDaemon\Http\RequestInterface;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\Mvc\Service\HttpRouterFactory;

use Okeanrst\FastCGIZF2Adapter\ServiceManager;
use Zend\ModuleManager\ModuleEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Mvc\Router\RouteStackInterface;
use Zend\Mvc\Application;
use Zend\Mvc\View\Http\ViewManager as HttpViewManager;
use Zend\Mvc\Service\ApplicationFactory;
use Zend\Http\PhpEnvironment\Response as HttpResponse;
use Zend\Http\Cookies;
use Zend\Http\Header\SetCookie;
use Zend\Http\Headers;
use Zend\Psr7Bridge\Psr7ServerRequest;
use Zend\Psr7Bridge\Psr7Response;
use Interop\Container\ContainerInterface;

use Zend\ModuleManager\Listener\ServiceListener;
use Zend\ModuleManager\ModuleManagerInterface;


/**
 * Wraps a Zend Framework 2 application object as an implementation of the kernel
 * interface.
 */
class AppWrapper implements KernelInterface
{
    /**
     * @var Sm
     */
    protected $serviceManager;
    
    protected $moduleManager;
	
	/**
     * @var Config
     */
    protected $config;
    /**
     * Constructor.
     * 
     * @param App $app The ZendFramework 2 application object to wrap
     */
     
    protected $startServicesInstances = [];
    
    protected $startAbstractFactorys = [];
    
    protected $coun = 0;
     
    public function __construct($configuration = [])
    {
        @define('PHP_SAPI', ' cgi-fcgi');
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : [];
        $smConfig = new ServiceManagerConfig($smConfig);
        
        $this->serviceManager = new ServiceManager();
        $smConfig->configureServiceManager($this->serviceManager);
        $this->serviceManager->setService('ApplicationConfig', $configuration);

        //$this->serviceManager->setFactory('Router', HttpRouterFactory::class);

        $this->moduleManager = $this->serviceManager->get('ModuleManager');
        $events = $this->moduleManager->getEventManager();
        $event  = $this->moduleManager->getEvent();
        $event->setName(ModuleEvent::EVENT_LOAD_MODULES);
        $events->triggerEvent($event);
        $this->serviceManager->setAllowOverride(true);
        
        
        //$this->moduleManager = $moduleManager;
		
        
        $strInstServ = $this->serviceManager->getRegisteredServices()['instances'];
        foreach($strInstServ as $v) {
            array_push($this->startServicesInstances, $v);
        }
        $this->startServicesInstances = array_flip($this->startServicesInstances);
        
        $this->startAbstractFactorys = $this->serviceManager->getAbstractFactorys();
    }
    /**
     * {@inheritdoc}
     */
    public function handleRequest(RequestInterface $request)
    {
        //openlog("Fastcgizf2Log", LOG_PID | LOG_PERROR, LOG_LOCAL0);
        $stream = fopen('php://stderr', 'w');
        $message = '';
        ++$this->coun;
        ini_set('session.use_cookies', false);
        
        
        $sesId = '';
        if ($request->getCookies() && ($sesId = $request->getCookies()[session_name()]) && $sesId !== '') {
            session_id($sesId);
        } else {
            //session_id(hash('sha1', uniqid(mt_rand(), true)));
            //session_id(bin2hex(random_bytes(16)));            
        }

        $serviceManager = $this->serviceManager;
        
        $moduleManager = $this->moduleManager;  
        $events = $moduleManager->getEventManager();
        $event  = $moduleManager->getEvent();
        $event->setName(ModuleEvent::EVENT_LOAD_MODULES_POST);
        $events->triggerEvent($event);
        
        $listenersFromAppConfig = isset($configuration['listeners']) ? $configuration['listeners'] : [];
        $config = $serviceManager->get('config');
        $listenersFromConfigService = isset($config['listeners']) ? $config['listeners'] : [];
        $listeners = array_unique(array_merge($listenersFromConfigService, $listenersFromAppConfig));
        
       
        $serverRequest = $request->getServerRequest();
        $request = Psr7ServerRequest::toZend($serverRequest);
		
		$serviceManager->setService('Request', $request);
		
		
        
        $application = $serviceManager->get('Application');       
        
        /*$viewManager = new HttpViewManager();
		$serviceManager->setService('ViewManager', $viewManager);*/
        
              
		$application->bootstrap($listeners);		
        
        
		ob_start();
		$application->run();
		$out = ob_get_contents();
		ob_end_clean();       
		$response = $application->getResponse();
        
        //$cookies = new Cookies();
        
        //__construct($name = null, $value = null, $expires = null, $path = null, $domain = null, $secure = false, $httponly = false, $maxAge = null, $version = null)
        //$cookies->addCookie($cookieSession);
        $sid = defined('SID') ? constant('SID') : false;
        if (session_status() == PHP_SESSION_ACTIVE || ($sid !== false && session_id())) {            
            if (session_status() == PHP_SESSION_ACTIVE) {
                //session_regenerate_id();
                //$message = $message.', session_regenerate_id ';
            }
            //'PHPSESSID'
            //$cookieSession = new SetCookie(session_name(), session_id(), null, null, 'fastcgi_zf2'/*, [int $expires, [string $path, [boolean $secure]]]*/);
            //(new Headers) ->clearHeaders();
            //$cookieSession = Headers::fromString($cookieSession->toString()); //суммируе Headers со всех вызовов, нужно обнулять
            if ($sesId !== session_id()) {
                $stringCookieSession = session_name().'='.session_id().'; path=/';
                $response->getHeaders()->addHeaders(['Set-Cookie' => $stringCookieSession]);
            }            
            session_write_close();
            @define('SID', false);           
            session_id(strtr(base64_encode(random_bytes(20)), ['=' => '', '+' => '-', '/' => '-' ]));
        }
        
        if (isset($_SESSION)) {
            foreach ($_SESSION as $key => $val) {
                unset($_SESSION[$key]);
            }
        }
        
        
        /*$headers = new Headers();
        $headers->addHeaders(Headers::fromString($cookieSession->toString()));
        var_dump($headers->toString()); */       
		
        
        $diactorosResponse = Psr7Response::fromZend($response);
        //var_dump($out);        
        
               
        $finishRegisteredServices = $serviceManager->getRegisteredServices();
        $services = [/*'request', 'response', 'application', 'viewmanager', 'zendi18ntranslatortranslatorinterface', 'doctrine.configuration.ormdefault', 'doctrine.eventmanager.ormdefault','zendservicemanagerservicemanager', 'applicationconfig', 'servicelistener', 'modulemanager', 'config', 'controllermanager', 'sharedeventmanager', 'zendi18ntranslatortranslatorinterface',*/];
        //Дополнительно сбросим некоторые сервисы, которые были определены при загрузке модулей - Load_Modules_Post
        $otherServices = [/*'controllerpluginmanager',*/ ];
        
        
        foreach ($finishRegisteredServices['instances'] as $num => $name) {
            
            if (!in_array($name, $services, true)) { 
    //$message = $message. ', '.$name;    
                if (!array_key_exists($name, $this->startServicesInstances) || in_array($name, $otherServices)) {                
    //$message = $message. ', '.$name;                    
                    //$serviceManager->unsetServiceInstance($name);
                    $serviceManager->unregisterService($name);
                    /*$isShared = $serviceManager->isShared($name);             
                    $serviceManager->setService($name, NULL);
                    
                    if (array_key_exists($name, $factories)) {
                        $serviceManager->setFactory($name, $factories[$name]);
    //var_dump('setFactory  '.$name);                    
                    }                
                    if (array_key_exists($name, $invokableClasses)) {
                        $serviceManager->setInvokableClass($name, $invokableClasses[$name]);
    //var_dump('setInvokableClass  '.$invokableClasses[$name]);                    
                    }
                    if (array_key_exists($name, $aliases)) {
                        $serviceManager->setAlias($name, $aliases[$name]);
    //var_dump('setAlias  '.$aliases[$name]); 
                    }
                    if ($serviceManager->has($name)) {
                        $serviceManager->setShared($name, $isShared);
                    }*/
                }
            }
        }
        
        $serviceManager->unsetAbstractFactorys();
        foreach ($this->startAbstractFactorys as $key => $fab) {
            $serviceManager->addAbstractFactory($fab);
        }
        
        if (isset($serverRequest->getQueryParams()['dump'])) {
            fwrite($stream, var_dump($this->moduleManager)."\n");
        }
            

        //$servicelistener = $serviceManager->get('servicelistener');
        //var_dump($servicelistener);
        
        /*$serviceManager->unsetServiceInstance('controllerpluginmanager'); 
        
        $moduleManager = $serviceManager->get('ModuleManager');
        $event  = $moduleManager->getEvent();
        $event->setName(ModuleEvent::EVENT_LOAD_MODULES);

        $events = $moduleManager->getEventManager();
        $events->attach(new ServiceListener($serviceManager));
        $events->triggerEvent($event);
        
        $controllerPluginManager = $serviceManager->get('controllerpluginmanager');
        $controllerPluginManagerServices = $controllerPluginManager->getRegisteredServices();*/
        
        
        /*foreach ($controllerPluginManagerServices['instances'] as $num => $serv) {
            $message = $message.', '.$serv;
        }
        
        syslog(LOG_WARNING, $message);*/
        
        //var_dump($request);
        //var_dump($handleSessionReset);
        $message = $message.$this->coun;
        //syslog(LOG_WARNING, $message);
        //closelog();
		return $diactorosResponse;
    }
}