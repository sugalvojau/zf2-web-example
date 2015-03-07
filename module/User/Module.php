<?php
namespace User;

use Zend\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function onBootstrap(MvcEvent $e)
    {
        // existing code...

        $services = $e->getApplication()->getServiceManager();
        $dbAdapter = $services->get('database');
        // Set the default database adapter
        \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::setStaticAdapter($dbAdapter);

        // Protect module requests
        $eventManager = $e->getApplication()->getEventManager();
        $eventManager->attach(MvcEvent::EVENT_ROUTE, array($this, 'protectPage'), -100);

        $sharedEventManager = $eventManager->getSharedManager();

        // Attach logger to log-fail of user
        $sharedEventManager->attach('user', 'log-fail', function($event) use ($services) {
            $username = $event->getParam('username');
            $log = $services->get('log');
            // This is shorthand form of this:
            // $log->log(Zend\Log\Logger::WARN, 'Error logging user [' . $username . ']')
            $log->warn('Error logging user [' . $username . ']');
        });

        // Attach logger to register of user
        $sharedEventManager->attach('user', 'register', function($event) use ($services) {
            $user = $event->getParam('user');
            $log = $services->get('log');
            // This is shorthand form of this:
            // $log->log(Zend\Log\Logger::WARN, 'Error logging user [' . $username . ']')
            $log->warn('Registered user [' . $user->getName() . '/' . $user->getId() . ']');
        });

    }

    /**
     * @param MvcEvent $event
     */
    public function protectPage(MvcEvent $event)
    {
        $match = $event->getRouteMatch(); // use RouteMatch object to get the name of the controller
            //, action, and other parameters

        if(!$match) {
            // we cannot do anything without a resolved route
            return;
        }

        $controller = $match->getParam('controller');
        $action     = $match->getParam('action');
        $namespace  = $match->getParam('__NAMESPACE__');

        $parts = explode('\\', $namespace);
        $moduleNamespace = $parts[0];

        $services = $event->getApplication()->getServiceManager();
        $config = $services->get('config');

        // check if the current module wants to use the ACL
        $aclModules = $config['acl']['modules'];
        if(!empty($aclModules) && !in_array($moduleNamespace, $aclModules)) {
            return;
        }

        $auth = $services->get('auth');
        $acl = $services->get('acl');

        // get the role of the current user
        $currentUser = $services->get('user');
        $role = $currentUser->getRole();

        /* @todo: Make sure the following commented part is not needed anymore. If so then remove it.
        // setup default roles for the current user
        $role = $config['acl']['defaults']['guest_role'];
        if($auth->hasIdentity()) {
            $user = $auth->getIdentity();
            $role = $user->getRole();
            if(!$role) {
                $role = $config['acl']['defaults']['member_role'];
            }
        }
        */

        // Get the short name of the controller and use it as resource name
        // Example: User\Controller\Course -> course
        $resourceAliases = $config['acl']['resource_aliases'];
        if(isset($resourceAliases[$controller])) {
            $resource = $resourceAliases[$controller];
        } else {
            $resource = strtolower(substr($controller, strpos($controller, '\\') + 1));
        }

        var_dump($resource, $currentUser, $role, $acl->hasResource($resource));

        // If a resource is not in the ACL add it
        if(!$acl->hasResource($resource)) {
            $acl->addResource($resource);
        }
        try {
            if($acl->isAllowed($role, $resource, $action)) {
                return;
            }
        } catch(AclException $ex) {
            // @todo: log in the warning log the missing resource
        }

        // If the role is not allowed access to the resource we have to redirect the
        // current user to the log in page.

        // Set the response code to HTTP 403: Forbidden
        $response = $event->getResponse();
        $response->setStatusCode(403);
        // and redirect the current user to the denied action
        $match->setParam('controller', 'User\Controller\Account');
        $match->setParam('action', 'denied');

        /*
        // Limit the execution of redirect to the current module only
        if(strpos($namespace, __NAMESPACE__) !== 0) {
            return;
        } elseif( // Also, let such user to login or add (aka. register)
            strpos($namespace, __NAMESPACE__) === 0
            && in_array($controller, array('User\Controller\Account'))
            && in_array($action,     array('register', 'add'))
        ) {
            return;
        }

        if(!$auth->hasIdentity()) {
            // Set the response code to HTTP 401: Auth Required
            $response = $event->getResponse();
            $response->setStatusCode(401);
            $match->setParam('controller', 'User\Controller\Log');
            $match->setParam('action', 'in');
        }
        */

    }



}
