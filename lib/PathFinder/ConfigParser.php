<?php
namespace PathFinder;
use LogicException;
use UnexpectedValueException;

/**
 * Class ConfigParser
 * @package Ffw\Routing
 */
class ConfigParser {
    const COMM = '#';
    const REGEX_MODIFICATOR = 'ui';
    const SECTIONS_DELIM = "/([\s\t]{1,})/";
    const REGEX_DELIM = "~";
    const PREFIX_VAR = '@';
    const CONTROLLER_REGEX = '/\[([A-Za-z0-9:\.\\\]+)\]/i';
    const REGEX_DEFAULT = '[^\/]+';
    protected $regexs = [];
    protected $line = 0;
    protected $handler = null;
    protected $urlHashes = [];
    protected $controller = null;
    protected $action = null;
    protected $buff = [];

    /**
     * Parse lines
     * @param array $lines
     * @return array
     */
    public function fromArray(array $lines) {
        foreach ($lines as $line) {
            $this->nextLine($line);
        }
        return $this->getResults();
    }

    /**
     * Parse file
     * @param $file
     * @return array
     */
    public function fromFile($file) {
        $handle = fopen($file, "r");
        while (!feof($handle)) {
            $this->nextLine(fgets($handle));
        }
        fclose($handle);
        return $this->getResults();
    }

    /**
     * Process next line
     * @param $str
     */
    private function nextLine($str) {
        $this->line++;
        $str = $this->stripComments($str);
        if (strlen($str)) {
            $this->prepare($str);
        }
    }

    /**
     * Clear comments
     * @param $str
     * @return null|string
     */
    private function stripComments($str) {
        if ($str[0] == self::COMM) return null;
        if (($commPos = mb_strpos($str, self::COMM)) !== false) { // удаление комментариев
            $str = mb_substr($str, 0, $commPos);
        }
        return trim($str);
    }

    /**
     * Prepare line
     * @param $section
     * @return array|null
     * @throws \Exception
     */
    private function prepare($section) {
        switch ($section[0]) {
            case (static::PREFIX_VAR): // если это объявление регулярки
                $this->regexSection($section);
                break;
            case '[': // если это секция с объявлением контроллера и метода
                $this->handlerSection($section);
                break;
            default: // это шаблон URL
                $this->urlSection($section);
        }
    }

    /**
     * Set regex section
     * @param $section
     */
    protected function regexSection($section) {
        $section = str_replace(self::PREFIX_VAR, null, $section);
        $keys = preg_split(static::SECTIONS_DELIM, $section);
        $regex = array_pop($keys); // last

        $keys = explode(',', implode(',', $keys));
        foreach ($keys as $key) {
            if ($key = trim($key)) {
                $this->regexs[$key] = $regex;
            }
        }
    }

    /**
     * Process handler section
     * @param $section
     * @throws \UnexpectedValueException
     */
    protected function handlerSection($section) {
        if (preg_match(static::CONTROLLER_REGEX, $section)) {
            $this->handler = trim($section, '][');
            $classAndMethod = $this->parseHandler($this->handler);
            $this->controller = $classAndMethod[0];
            $this->action = $classAndMethod[1];
        } else {
            throw new UnexpectedValueException($this->makeErrText("invalid handler section"));
        }
    }

    /**
     * Process url section
     * @param $section
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    protected function urlSection($section) {
        $parts = preg_split(static::SECTIONS_DELIM, $section, 3);
        if (!in_array($count = count($parts), [2, 3])) {
            throw new UnexpectedValueException($this->makeErrText("invalid expression"));
        }
        $method = $this->parseMethod($parts[0]);
        $url = mb_strtolower($parts[1]);
        $slash = (substr($url, -1) == '/') ? '/' : null;
        $url = rtrim($url, '/');
        list($pattern, $hash, $keys) = $this->parseURL($url);
        $pattern = ($pattern !== $url) ? $pattern : null;
        $hash = $method . '||' . $hash;
        if (isset($this->urlHashes[$hash])) {
            $msg = sprintf('«%s» is equals of «%s»', $url, $this->urlHashes[$hash]);
            throw new LogicException($this->makeErrText($msg));
        } else {
            $this->urlHashes[$hash] = $url;
        }
        $args = !empty($parts[2]) ? $this->jsonDecode($parts[2]) : null;
        $this->makeItem($method, $url, $pattern, $slash, $keys, (array)$args);
    }

    /**
     * Validate request method
     * @param $method
     * @return string
     * @throws \UnexpectedValueException
     */
    protected function parseMethod($method) {
        $method = strtoupper($method);
        if (!in_array($method, ['*', 'ANY', 'GET', 'POST', 'PUT', 'DELETE'])) {
            throw new UnexpectedValueException($this->makeErrText("incorrect method"));
        }
        return $method;
    }

    /**
     * Decode json with quotes-free keys: {key: "value"}
     * @param $json
     * @return mixed
     */
    protected function jsonDecode($json) {
        $json = preg_replace('/(\w+):/i', '"\1":', $json);
        return json_decode($json);
    }

    /**
     * Prepare handler
     * @param $handler
     * @return array
     * @throws UnexpectedValueException
     */
    protected function parseHandler($handler) {
        $ctrl = ucfirst($handler);
        // разделитель между классом и функцией - либо точка, либо два двоеточия
        switch (count($parts = explode(':', $ctrl))) {
            case 1:
                $parts[] = 'index';
                break;
            case 2:
                break;
            default:
                throw new UnexpectedValueException($this->makeErrText("invalid handler"));
        }
        $parts[0] = str_replace('.', '\\', $parts[0]);
        list($controller, $action) = $parts;
        return [$controller, $action];
    }

    /**
     * Prepare url
     * @param $url
     * @return array
     * @throws LogicException
     */
    protected function parseURL($url) {
        if (strpos($url, '{')) {
            $keys = [];
            $named = [];
            $pattern = preg_replace_callback("/\\\{(.+?)\\\}/", function ($match) use (&$keys, &$named) {
                $keys[] = ($key = trim($match[1]));
                if (is_numeric($key)) {
                    throw new UnexpectedValueException($this->makeErrText("key NOT be numeric"));
                } elseif (!preg_match('/^([A-Za-z0-9_]+)$/', $key)) {
                    throw new UnexpectedValueException($this->makeErrText("invalid key «{$key}»"));
                }
                $regex = isset($this->regexs[$key]) ? $this->regexs[$key] : self::REGEX_DEFAULT;
                $expr = "(?<$key>$regex)";
                $named[$expr] = "($regex)";
                return $expr;
            }, preg_quote($url, self::REGEX_DELIM));
            $hash = str_replace(array_keys($named), array_values($named), $pattern);
            $fullRegex = self::REGEX_DELIM . '^' . $pattern . '$' . self::REGEX_DELIM . self::REGEX_MODIFICATOR;
            return [$fullRegex, $hash, $keys];
        }
        return [$url, $url, []];
    }

    /**
     * Make route rules item
     * @param       $method
     * @param       $url
     * @param       $regex
     * @param       $slash
     * @param array $keys
     * @param array $args
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    protected function makeItem($method, $url, $regex, $slash, array $keys, array $args) {
        if (!$this->handler) {
            throw new UnexpectedValueException($this->makeErrText("handler not defined"));
        }
        $key = static::makeHash($this->handler, array_flip($keys));
        if (isset($this->buff[$key])) {
            $msg = "dublicate params in «{$url}» and «{$this->buff[$key]->url}»";
            throw new LogicException($this->makeErrText($msg));
        }
        $this->buff[$key] = (object)[
            'method' => $method,
            'url' => $url,
            'regex' => $regex,
            'slash' => $slash,
            'controller' => $this->controller,
            'action' => $this->action,
            'args' => $args
        ];
    }

    /**
     * Make hash of route
     * @param       $handler
     * @param array $params
     * @return string
     */
    public static function makeHash($handler, array $params) {
        $keys = [];
        foreach ($params as $key => $val) {
            if (!is_numeric($key)) $keys[] = mb_strtolower($key);
        }
        sort($keys);
        return trim(strtolower($handler . '||' . join('|', $keys)));
    }

    /**
     * Make error message
     * @param $error
     * @return string
     */
    protected function makeErrText($error) {
        return "Error: {$error} in line {$this->line}";
    }

    /**
     * Get results and clear data
     * @return array
     */
    private function getResults() {
        $buff = $this->buff;
        $this->buff = [];
        $this->regexs = [];
        $this->urlHashes = [];
        $this->handler = null;
        $this->controller = null;
        $this->action = null;
        $this->line = 0;
        return $buff;
    }
}