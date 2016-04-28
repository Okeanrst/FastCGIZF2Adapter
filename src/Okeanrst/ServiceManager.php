<?php

namespace Okeanrst\FastCGIZF2Adapter;

use Zend\ServiceManager\ServiceManager as BaseServiceManager;

class ServiceManager extends BaseServiceManager
{
    public function getAbstractFactorys()
	{
		return $this->abstractFactories;
	}
    
    public function unsetAbstractFactorys()
    {
        $this->abstractFactories = [];
    }
    
    public function unregisterService($canonical)
    {
        parent::unregisterService($canonical);
    }
    
    
}