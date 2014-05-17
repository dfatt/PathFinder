<?php
namespace PathFinder;

/**
 * Class Router
 * @package PathFinder
 */
class Router {
    /**
     * User-defined function for start controller
     * Args: string $controller, string $action, array $args
     * @var callable
     */
    public $processCallable = null;

    /**
     * List of route rules
     * @var array
     */
    protected $rules = [];

    /**
     * @param array    $rules List of rules(generated with ConfigParser!)
     * @param callable $processCallable [string $controller, string $action, array $args]
     */
    public function __construct(array $rules, $processCallable = null) {
        $this->rules = $rules;
        $this->processCallable = $processCallable;
    }

    public function delegate($url, $method = 'GET') {
        if ($rules = $this->parseUrl($url, $method)) {
            return $this->process($rules->controller, $rules->action, $rules->args);
        } else throw new NoRouteException();
    }

    /**
     * Get params of url
     * @param        $url
     * @param string $method
     * @return array|object
     */
    public function parseUrl($url, $method) {
        $url = rtrim($url, '/');
        foreach ($this->rules as $rules) {
            if (!$this->methodIs($method, $rules->method)) continue;
            if (!$rules->regex && ($rules->url === $url)) { // простой шаблон
                return $rules;
            } elseif ($rules->regex && preg_match($rules->regex, $url, $matches)) { // регулярка
                foreach ($matches as $k => $v) if (!is_numeric($k)) $rules->args[$k] = $v;
                return $rules;
            }
        }
        return null;
    }

    /**
     * Create url of params
     * @param       $handler
     * @param array $args
     * @param null  $prefix
     * @return null|string
     */
    public function makeUrl($handler, array $args = [], $prefix = null) {
        $hash = ConfigParser::makeHash($handler, $args);
        if (isset($this->rules[$hash])) {
            $rules = $this->rules[$hash];
            $urlTemplate = $rules->url . $rules->slash;
            $keys = array_map(function ($var) { return '{' . $var . '}'; }, array_keys($args));
            $values = array_values($args);
            return $prefix . str_ireplace($keys, $values, $urlTemplate);
        } else return null;
    }

    /**
     * Check method
     * @param $method
     * @param $expectedMethod
     * @return bool
     */
    protected function methodIs($method, $expectedMethod) {
        if (in_array($expectedMethod, ['ANY', 'ALL', '*']) || $method == $expectedMethod) return true;
        return false;
    }

    /**
     * Run application with user-defined function
     * @param       $controller
     * @param       $action
     * @param array $args
     * @return mixed
     */
    public function process($controller, $action, array $args = []) {
        return call_user_func($this->processCallable, $controller, $action, $args);
    }
}