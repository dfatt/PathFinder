<?php
namespace PathFinder;

class SimpleAdapter extends Router {
    /**
     * Read config file in constructor
     */
    public function __construct() {
        /* Parsing results must be cached in production!
        if (!$this->rules = apc_fetch('route_config')) {
            $this->rules = (new ConfigParser)->fromFile('your-path-to-routes.conf');
            apc_add('route_config', $this->rules);
        }
        */
        $this->routes = (new ConfigParser)->fromFile('your-path-to-routes.conf');
    }

    /**
     * You'r run controller function
     * @param       $controller
     * @param       $action
     * @param array $args
     * @return mixed|void
     */
    public function process($controller, $action, array $args = []) {
        header('Content-type: text/plain');
        print_r(compact("controller", "action", "args"));
        exit;
    }

    /**
     * Catch NoRouteException and generate 404 page
     * @param        $url
     * @param string $method
     * @return mixed|void
     */
    public function delegate($url, $method = 'GET') {
        try {
            parent::delegate($url, $method);
        } catch (NoRouteException $e) {
            header('404 not found');
            echo 'page not found ;(';
        }
        exit;
    }
}

//using:
(new SimpleAdapter)->delegate(
    $_SERVER['REQUEST_URI'],
    $_SERVER['REQUEST_METHOD']
);