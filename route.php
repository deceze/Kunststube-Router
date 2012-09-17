<?php

namespace Kunststube\Routing;

use \InvalidArgumentException,
    \LogicException;

class Route {

	protected $pattern,
              $dispatch = array(),
              $wildcard = false;

    protected $url,
              $wildcardArgs = array();

    private $regex,
            $parts = array();

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
        static $regex = null;
        if (!$regex) {
            $regex = $this->buildRegex();
        }

        if (!preg_match($regex, $url, $matches)) {
            return false;
        }
        
        array_shift($matches);

        $route = clone $this;
        $route->url = $url;
        $route->mergeNamedMatches($matches);
        if ($route->wildcard) {
            $route->wildcardArgs = $route->parseWildcardArgs(reset($matches));
        }

        return $route;
    }

    public function matchDispatch(array $comparison) {
        if (array_diff_key($this->dispatch, $comparison)) {
            return false;
        }

        $dispatch     = $this->dispatch;
        $wildcardArgs = array();

        foreach ($comparison as $key => $value) {
            if (is_string($key) && isset($this->parts[$key])) {
                if (preg_match("/^{$this->parts[$key]}\$/", $value)) {
                    $dispatch[$key] = $value;
                } else {
                    return false;
                }
            } else if (isset($dispatch[$key])) {
                if ($dispatch[$key] !== $value) {
                    return false;
                }
            } else if (!$this->wildcard) {
                return false;
            } else if (is_integer($key)) {
                $wildcardArgs[] = $value;
            } else {
                $wildcardArgs[$key] = $value;
            }
        }

        $route = clone $this;
        $route->dispatch     = $dispatch;
        $route->wildcardArgs = $wildcardArgs;
        return $route;
    }

    public function url() {
        $dispatch = $this->dispatch;
        $url = $this->interpolateParts($this->pattern, $dispatch);
        $url = rtrim($url, '/*');

        if ($this->wildcardArgs) {
            $wildcardArgs = array();
            foreach ($this->wildcardArgs as $key => $value) {
                if (is_numeric($key)) {
                    $wildcardArgs[] = $value;
                } else {
                    $wildcardArgs[] = "$key:$value";
                }
            }
            $url .= '/' . implode('/', $wildcardArgs);
        }

        return $url;
    }

    public function __get($name) {
        return isset($this->dispatch[$name]) ? $this->dispatch[$name] : false;
    }

    public function wildcardArgs() {
        return $this->wildcardArgs;
    }

    public function wildcardArg($name) {
        return isset($this->wildcardArgs[$name]) ? $this->wildcardArgs[$name] : false;
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

        $this->pattern  = $pattern;
        $this->parts    = $parts;
        $this->regex    = $this->partsToRegex($parts);
        $this->dispatch = $this->partsToDispatch($parts, $dispatch);
    }

    private function parseWildcard(array $parts) {
        $lastIndex = count($parts) - 1;
        if ($parts[$lastIndex] === '*') {
            $this->wildcard = true;
            unset($parts[$lastIndex]);
        }
        return $parts;
    }

    private function parsePart($part) {
        if (!preg_match('/^(?<pattern>.+?)?:(?<name>\w+)$/', $part, $match)) {
            // literal pattern (/foo/)
            return array(preg_quote($part, '/'));
        }
        if ($match['pattern'] === '') {
            // simple named part (/:foo/)
            $match['pattern'] = '[^\/]+';
        }
        // named regex part (/.+:foo/)
        return array($match['name'] => $match['pattern']);
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
                $dispatch[$key] = null;
            }
        }

        if (!$dispatch) {
            throw new InvalidArgumentException("Both the pattern '{$this->pattern}' and the dispatch information contain no routable information");
        }

        return $dispatch;
    }

    private function buildRegex() {
        return sprintf('/^%s%s$/', $this->regex, $this->wildcard ? '(.*)' : null);
    }

    private function mergeNamedMatches(array &$matches) {
        $i = 0;
        foreach ($matches as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (!array_key_exists($key, $this->dispatch)) {
                throw new LogicException("Route has no dispatch key '$key', should not have matched");
            }
            $this->dispatch[$key] = $value;
            unset($matches[$key], $matches[$i++]);
        }
    }

    private function parseWildcardArgs($args) {
        if ($args === '') {
            return array();
        }

        $args = trim($args, '/');
        $args = explode('/', $args);

        $wildcardArgs = array();
        foreach ($args as $arg) {
            $arg = explode(':', $arg, 2);
            if (isset($arg[1])) {
                $wildcardArgs[$arg[0]] = $arg[1];
            } else {
                $wildcardArgs[] = $arg[0];
            }
        }

        return $wildcardArgs;
    }

    private function interpolateParts($pattern, array &$dispatch) {
        return preg_replace_callback('!(?<=/)[^/]*:(\w+)(?=/|$)!', function ($m) use ($pattern, &$dispatch) {
            if (!isset($dispatch[$m[1]])) {
                throw new LogicException("Pattern '$pattern' does not contain placeholder for $m[1]");
            }
            $value = $dispatch[$m[1]];
            unset($dispatch[$m[1]]);
            return $value;
        }, $pattern);
    }

}
