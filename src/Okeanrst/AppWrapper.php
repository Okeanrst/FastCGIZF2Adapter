<?php

namespace Okeanrst\FastCGIZF2Adapter;

use PHPFastCGI\FastCGIDaemon\KernelInterface;
use PHPFastCGI\FastCGIDaemon\Http\RequestInterface;
use Zend\Mvc\Service\ServiceManagerConfig;
use Okeanrst\FastCGIZF2Adapter\ServiceManager;
use Zend\ModuleManager\ModuleEvent;
use Zend\Psr7Bridge\Psr7ServerRequest;
use Zend\Psr7Bridge\Psr7Response;

/**
 * Wraps a Zend Framework 2 application object as an implementation of the kernel
 * interface.
 */
class AppWrapper implements KernelInterface
{
    protected $serviceManager;
    
    protected $moduleManager;	
	
    protected $config;    
     
    protected $startServicesInstances = [];
    
    protected $startAbstractFactorys = [];   
     
    public function __construct($configuration = [])
    {
        @define('PHP_SAPI', ' cgi-fcgi');
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : [];
        $smConfig = new ServiceManagerConfig($smConfig);
        
        $this->serviceManager = new ServiceManager();
        $smConfig->configureServiceManager($this->serviceManager);
        $this->serviceManager->setService('ApplicationConfig', $configuration);

        $this->moduleManager = $this->serviceManager->get('ModuleManager');
        $events = $this->moduleManager->getEventManager();
        $event  = $this->moduleManager->getEvent();
        $event->setName(ModuleEvent::EVENT_LOAD_MODULES);
        $events->triggerEvent($event);
        $this->serviceManager->setAllowOverride(true);
        
        $startInstServ = $this->serviceManager->getRegisteredServices()['instances'];
        foreach($startInstServ as $v) {
            array_push($this->startServicesInstances, $v);
        }
        $this->startServicesInstances = array_flip($this->startServicesInstances);
        
        $this->startAbstractFactorys = $this->serviceManager->getAbstractFactorys();
    }
    
    public function handleRequest(RequestInterface $request)
    {
        ini_set('session.use_cookies', false);        
        
        $sesId = '';
        if ($request->getCookies() && ($sesId = $request->getCookies()[session_name()]) && $sesId !== '') {
            session_id($sesId);
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
		$application->bootstrap($listeners);       
        
		ob_start();
		$application->run();
		$out = ob_get_contents();
		ob_end_clean();       
		$response = $application->getResponse();        
        
        $sid = defined('SID') ? constant('SID') : false;
        if (session_status() == PHP_SESSION_ACTIVE || ($sid !== false && session_id())) {
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
               
        $finishRegisteredServices = $serviceManager->getRegisteredServices();
        
        foreach ($finishRegisteredServices['instances'] as $num => $name) {
            if (!in_array($name, $services, true)) { 
       
                if (!array_key_exists($name, $this->startServicesInstances) ) {
                    $serviceManager->unregisterService($name);                    
                }
            }
        }
        
        $serviceManager->unsetAbstractFactorys();
        foreach ($this->startAbstractFactorys as $key => $fab) {
            $serviceManager->addAbstractFactory($fab);
        }        
        
		$diactorosResponse = Psr7Response::fromZend($response);
        //var_dump($out);
        return $diactorosResponse;
    }
}