<?php

namespace Kunststube\Routing;

use \InvalidArgumentException;

class Route {

	protected $pattern,
              $dispatch     = array(),
              $lastWildcard = false,
              $passedArgs   = array(),
              $url;

    private $regex;

    public function __construct($pattern, array $dispatch = array()) {
        if (!is_string($pattern)) {
            throw new InvalidArgumentException('$pattern must be a string, got ' . gettype($pattern));
        }
        if (strlen($pattern) === 0) {
            throw new InvalidArgumentException ('$pattern is empty');
            
        }
        if ($pattern[0] != '/') {
            throw new InvalidArgumentException("Pattern '$pattern' must start with a /");
        }
 
        $this->initialize($pattern, $dispatch);
    }

    private function initialize($pattern, array $dispatch) {
        $parts = explode('/', trim($pattern, '/'));
        $parts = $this->parseWildcard($parts);
        $parts = array_map(array($this, 'parsePart'), $parts);
        $parts = call_user_func_array('array_merge', $parts);
        
        foreach ($dispatch as &$value) {
            $value = array('value' => $value, 'regex' => null);
        }

        $this->pattern  = $pattern;
        $this->regex    = $this->partsToRegex($parts);
        $this->dispatch = $this->partsToDispatch($parts, $dispatch);
    }

    private function parseWildcard(array $parts) {
        $lastIndex = count($parts) - 1;
        if ($parts[$lastIndex] === '*') {
            $this->lastWildcard = true;
            unset($parts[$lastIndex]);
        }
        return $parts;
    }

    private function parsePart($part) {
        if (preg_match('/^:(?<name>\w+)$/', $part, $match)) {
            // simple named part (/:foo/)
            return array($match['name'] => '[^\/]+');
        }
        if (!preg_match('/^\[(?<pattern>.+?)(:(?<name>\w+))?\]$/', $part, $matches)) {
            // literal pattern (/foo/)
            return array(preg_quote($part, '/'));
        }
        if (isset($matches['name'])) {
            // regex named part (/[.+:foo]/)
            return array($matches['name'] => $matches['pattern']);
        }
        // regex part (/[.+]/)
        return array($matches['pattern']);
    }

    private function partsToRegex(array $parts) {
        foreach ($parts as $key => &$value) {
            if (is_string($key)) {
                $value = "(?<$key>$value)";
            }
        }
        return '\/' . join('\/', $parts);
    }

    private function partsToDispatch(array $parts, array $dispatch) {
        foreach ($parts as $key => $regex) {
            if (is_string($key)) {
                if (isset($dispatch[$key])) {
                    throw new InvalidArgumentException("Both the pattern '{$this->pattern}' and the dispatch information contain the parameter '$key', route is invalid");
                }
                $dispatch[$key] = array('value' => null, 'regex' => $regex);
            }
        }

        if (!$dispatch) {
            throw new InvalidArgumentException("Both the pattern '{$this->pattern}' and the dispatch information contain no routable information");
        }

        return $dispatch;
    }

    private function buildRegex() {
        return sprintf('/^%s%s$/', $this->regex, $this->lastWildcard ? '(.*)' : null);
    }

    public function matchUrl($url) {
        $regex = $this->buildRegex();
        if (!preg_match($regex, $url, $matches)) {
            return false;
        }

        $route = clone $this;
        $route->url = $url;
        
        unset($matches[0]);
        $i = 1;
        foreach ($matches as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $route->dispatch[$key]['value'] = $value;
            unset($matches[$key], $matches[$i++]);
        }

        if ($this->lastWildcard) {
            $passedArgs = reset($matches);
            $passedArgs = trim($passedArgs, '/');
            $passedArgs = explode('/', $passedArgs);
            $route->passedArgs = $passedArgs;
        }

        return $route;
    }

    public function __get($name) {
        if (isset($this->dispatch[$name]['value'])) {
            return $this->dispatch[$name]['value'];
        }
        return false;
    }

    public function passedArgs() {
        return $this->passedArgs();
    }

    public function url() {
        return $this->url;
    }

    public function pattern() {
        return $this->pattern;
    }

}
