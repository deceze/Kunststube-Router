<?php

namespace Kunststube\Routing;

use \InvalidArgumentException,
    \LogicException;

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

    public function matchUrl($url) {
        $regex = $this->buildRegex();
        if (!preg_match($regex, $url, $matches)) {
            return false;
        }
        array_shift($matches);

        $route = clone $this;
        $route->url = $url;
        $route->mergeNamedMatches($matches);
        if ($route->lastWildcard) {
            $route->passedArgs = $route->parsePassedArgs(reset($matches));
        }

        return $route;
    }

    public function matchDispatch(array $comparison) {
        if (array_diff_key($this->dispatch, $comparison)) {
            return false;
        }

        $dispatch   = $this->dispatch;
        $passedArgs = array();

        foreach ($comparison as $key => $value) {
            if (isset($dispatch[$key])) {
                if ($this->matchPart($dispatch[$key], $value)) {
                    $dispatch[$key]['value'] = $value;
                } else {
                    return false;
                }
            } else if (!$this->lastWildcard) {
                return false;
            } else if (is_numeric($key)) {
                $passedArgs[] = $value;
            } else {
                $dispatch[$key]['value'] = $value;
            }
        }

        $route = clone $this;
        $route->dispatch   = $dispatch;
        $route->passedArgs = $passedArgs;
        print_r($route);
        return $route;
    }

    public function url() {
        $dispatch = $this->dispatch;
        $url = $this->interpolateParts($this->pattern, $dispatch);
        $url = rtrim($url, '/*');

        $args = $this->passedArgs;
        foreach ($dispatch as $key => $value) {
            if (is_string($key) && !array_key_exists('regex', $value)) {
                $args[] = "$key:$value[value]";
            }
        }
        if ($args) {
            $url .= '/' . implode('/', $args);
        }

        return $url;
    }

    public function __get($name) {
        if (isset($this->dispatch[$name]['value'])) {
            return $this->dispatch[$name]['value'];
        }
        return false;
    }

    public function passedArgs() {
        return $this->passedArgs;
    }

    public function passedArg($name) {
        return isset($this->passedArgs[$name]) ? $this->passedArgs[$name] : false;
    }

    public function matchedUrl() {
        return $this->url;
    }

    public function pattern() {
        return $this->pattern;
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
        if (!preg_match('/^(?<pattern>.+?)?:(?<name>\w+)$/', $part, $matches)) {
            // literal pattern (/foo/)
            return array(preg_quote($part, '/'));
        }
        if (!isset($matches['pattern'])) {
            // simple named part (/:foo/)
            $matches['pattern'] = '[^\/]+';
        }
        // named regex part (/.+:foo/)
        return array($matches['name'] => $matches['pattern']);
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

    private function mergeNamedMatches(array &$matches) {
        $i = 0;
        foreach ($matches as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $this->dispatch[$key]['value'] = $value;
            unset($matches[$key], $matches[$i++]);
        }
    }

    private function parsePassedArgs($args) {
        $args = trim($args, '/');
        $args = explode('/', $args);

        $passedArgs = array();
        foreach ($args as $arg) {
            $arg = explode(':', $arg, 2);
            if (isset($arg[1])) {
                $passedArgs[$arg[0]] = $arg[1];
            } else {
                $passedArgs[] = $arg[0];
            }
        }

        return $passedArgs;
    }

    private function matchPart(array $part, $value) {
        if (!$part['regex']) {
            return $part['value'] === $value;
        } else {
            return preg_match("/^$part[regex]$/", $value);
        }
    }

    private function interpolateParts($pattern, array &$dispatch) {
        return preg_replace_callback('!(?<=/)[^/]*:(\w+)(?=/|$)!', function ($m) use ($pattern, &$dispatch) {
            if (!isset($dispatch[$m[1]])) {
                throw new LogicException("Pattern '$pattern' does not contain placeholder for $m[1]");
            }
            $value = $dispatch[$m[1]]['value'];
            unset($dispatch[$m[1]]);
            return $value;
        }, $pattern);
    }

}
