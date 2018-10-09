# Anexia Monitoring

A Neos plugin used to monitor updates for active composer packages. It can be also used to check if the website
is alive and working correctly.

## Installation and configuration

Install the module via composer, therefore adapt the ``require`` part of your ``composer.json``:
```
"require": {
    "anexia/neos-monitoring": "1.0.1"
}
```

In your site package ``/app/Packages/Sites/[SITE_NAME]/Configuration/Settings.yaml`` add the following:
```
Anexia:
  Neos:
    Monitoring:
      queryParameter: 'access_token'
      accessToken: 'YOUR_CUSTOM_ACCESS_TOKEN'
      status:
        checks:
          class: Anexia\Neos\Monitoring\Check\DatabaseCheck
```

Now run
```
composer update [-o]
```

To enable the plugin, run
```
php ./flow neos.flow:package:rescan
php ./flow package:activate Anexia.Neos.Monitoring
```

## Usage

The package registers some custom REST endpoints which can be used for monitoring. Make sure that the
**Anexia.Neos.Monitoring.accessToken** is defined, since this is used for authorization. The endpoints will return a 503
HTTP_STATUS code if the token is not configured, 403 HTTP_STATUS code if its missing or a 401 HTTP_STATUS code if its 
invalid. If everything is ok, a 200 HTTP_STATUS code will be returned.

#### Version monitoring of core and composer packages

Returns all a list with platform and composer package information.

**URL:** `/anxapi/v1/modules?access_token=custom_access_token`

Response headers:
```
Status Code: 200 OK
Access-Control-Allow-Origin: *
Access-Control-Allow-Credentials: true
Allow: GET
Content-Type: application/json
```

Response body:
```
{
   "runtime":{
      "platform":"php",
      "platform_version":"7.0.0",
      "framework":"neos",
      "framework_installed_version":"3.0.0",
      "framework_newest_version":"5.0.0"
   },
   "modules":[
      {
         "name":"package-1",
         "installed_version":"3.1.10",
         "installed_version_licences":[
            "BSD-2-Clause"
         ],
         "newest_version":"3.3.2",
         "newest_version_licences":[
            "BSD-3-Clause"
         ]
      },
      {
         "name":"package-2",
         "installed_version":"1.4",
         "installed_version_licences":[
            "MIT"
         ],
         "newest_version":"1.4",
         "newest_version_licences":[
            "MIT"
         ]
      },
      ...
   ]
}
```

#### Live monitoring

This endpoint can be used to verify if the application is alive and working correctly. It checks if the database
connection is working. It allows to register custom checks by simply adding classes to the config.

**URL:** `/anxapi/v1/up?access_token=custom_access_token`

Response headers:
```
Status Code: 200 OK
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, OPTIONS
Access-Control-Allow-Credentials: true
Content-Type: text/plain
```

Response body:
```
OK
```


**Custom up check failure (without custom error message):**

Response headers (custom check failed without additional error message):
```
Status Code: 500 Internal Server Error
Access-Control-Allow-Origin: *
Access-Control-Allow-Credentials: true
Allow: GET
Content-Type: text/plain
```

Response body (containing default error message):
```
CLASS didn't pass the check.
```

**Custom up check failure (with custom error message):**

Response headers (custom check failed without additional error message):
```
Status Code: 500 Internal Server Error
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, OPTIONS
Access-Control-Allow-Credentials: true
Content-Type: text/plain
```

Response body (containing custom error message):
```
This is an example for a custom db check error message!
```

**Custom live monitoring hooks:**

The ``anexia/neos-monitoring`` only checks the DB connection.
To add further up checks a customized class can be defined. This class must implement the 
``Anexia\Neos\Monitoring\Check\CheckInterface``.

Add a new class to the project source code tree as ``/app/Packages/Sites/[SITE_NAME]/Classes/Check/CustomCheck.php``, e.g.:
```php
<?php

namespace Anexia\Neos\Monitoring\Check;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\Exception\DatabaseConnectionException;
use Neos\Flow\Persistence\PersistenceManagerInterface;

class DatabaseCheck implements CheckInterface
{
    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * Check if a specific function works correctly.
     * Return false for a generic error message, otherwise throw an exception with a custom message
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function run(): bool
    {
        if ($this->persistenceManager->isConnected()) {
            return true;
        }
        throw new DatabaseConnectionException('Could not connect to the database.');
    }
}
```

The ``run`` method gets automatically called by the ``anexia/neos-monitoring`` plugin up check. If the ``run`` method 
returns ``false`` or throws an exception, the ``anexia/neos-monitoring`` up check will fail. 
If the ``run`` method returns ``false`` a generic error will be displayed. To customize error messages, simply throw an
exception with your own message. 

## List of developers

* Nikita Bernthaker <NBernthaler@anexia-it.com>, Lead developer

## Project related external resources

* [Neos documentation](https://neos.readthedocs.io/en/stable/)
