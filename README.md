# PHPFastCGI Zend Framework 2 Adapter

Experimental!!! First, the application shows 10-fold increase a performance. With the accumulation of the number of requests , the amount of RAM occupied by the application increases , increasing the response time. Tests were conducted with and without the module "zf-commons/zfc-user-doctrine-orm".

## Usage

Below is an example of FastCGI application based on Zend Framework 2.

Install [ZendSkeletonApplication](https://github.com/zendframework/ZendSkeletonApplication).
Add "PHPFastCGI Zend Framework 2 Adapter" to your application: 
 
    php composer.phar require okeanrst/fastcgi-zf2-adapter

Add the file below into the project directory:

```php
<?php // fcgi.php

chdir(__DIR__);

// Setup autoloading
require_once 'vendor/autoload.php';

use PHPFastCGI\FastCGIDaemon\ApplicationFactory;
use Okeanrst\FastCGIZF2Adapter\AppWrapper;

define('DEV_MOVE', true);

if (DEV_MOVE) {    
    error_reporting(E_ALL & ~E_USER_DEPRECATED);
    ini_set("display_errors", 1);
}
		
// Create the kernel for the FastCGIDaemon library
$kernel = new AppWrapper(require 'config/application.config.php');

// Create the symfony console application
$consoleApplication = (new ApplicationFactory)->createApplication($kernel);

// Run the symfony console application
$consoleApplication->run();
```

### Supervisor setup

To setup supervisor, open your `/path/to/supervisor.d/`, create, eg, program_fastcgi_ZF2_1.conf and add
the following:

    [program:fastcgi_zf2_1]
    command=php /var/www/FastCGIDaemonZF2/fcgi.php run --port=5001 --host=localhost
    autostart=true
    autorestart=true
    stderr_logfile=/var/log/supervisor/fastcgi_zf2_1.err.log
    stdout_logfile=/var/log/supervisor/fastcgi_zf2_1.out.log
    
Run supervisorctl, execute: reread and then reload.

### Nginx setup

To setup nginx, open your `/path/to/nginx/nginx.conf` and add an
[include directive](http://nginx.org/en/docs/ngx_core_module.html#include) below
into `http` block if it does not already exist:

    http {
        # ...
        include sites-enabled/*.conf;
    }


Create a virtual host configuration file for your project under `/path/to/nginx/sites-enabled/zf2-app.localhost.conf`
it should look something like below:

    upstream workers {
        server localhost:5001;
        #server localhost:5002;
    }

    server {
        listen 80;
        server_name fastcgi_zf2;
        root /var/www/FastCGIDaemonZF2/public;

        location / {
            # try to serve file directly, fallback to app.php
            try_files $uri /index.php$is_args$args;
        }

        location ~ ^/index\.php(/|$) {
            fastcgi_pass workers;
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
        
            fastcgi_param  SCRIPT_FILENAME  $realpath_root$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT $realpath_root;
        
            internal;
        }

        error_log /var/log/nginx/fastcgi_zf2_error.log;
        access_log /var/log/nginx/fastcgi_zf2_access.log;
    }

Restart the nginx, now you should be ready to go!
