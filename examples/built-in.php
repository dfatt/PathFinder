<?php
use PathFinder\ConfigParser;
use PathFinder\Router;
use PathFinder\NoRouteException;

/*
 * Read config file
 * Parsing results must be cached in production!
if (!$routes = apc_fetch('route_config')) {
    $routes = (new ConfigParser)->fromFile('your-path-to-routes.conf');
    apc_add('route_config', $routes);
}
*/
$routes = (new ConfigParser)->fromFile('your-path-to-routes.conf');

// init router class with run controller function
$router = new Router($routes, function ($controller, $action, array $args) {
    header('Content-type: text/plain');
    print_r(compact("controller", "action", "args"));
    exit;
});

// run process
try {
    $router->delegate(
        $_SERVER['REQUEST_URI'],
        $_SERVER['REQUEST_METHOD']
    );
} catch (NoRouteException $e) {
    header('404 not found');
    echo 'page not found ;(';
}