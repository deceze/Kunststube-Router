<?php

namespace Kunststube\Router;

use InvalidArgumentException,
    LogicException;


class Route {

    protected $pattern,
              $dispatch = array(),
              $wildcard = false,
              $parts    = array(),
              $regex;

    protected $url,
              $wildcardArgs = array();

    /**
     * @param string $pattern The pattern for the route.
     *  Consists of parts separated by forward slashes.
     *  Three types of parts are supported:
     *      - literal:     /foo/
     *      - named:       /:foo/
     *      - named regex: /\d+:foo/
     *
     *  The final part may be a '*' to allow for trailing wildcard arguments.
     *
     *  Example: /foo/:bar/\d+:baz/*
     *
     * @param array $dispatch Default values for the dispatcher.
     * @throws InvalidArgumentException
     */
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

    /**
     * Tries to match a URL to the route's pattern.
     *
     * @param string $url The URL to match.
     * @return Route A copy of the route object with the matches populated, or false on non-match.
     */
    public function matchUrl($url) {
        if (!preg_match($this->buildRegex(), $url, $matches)) {
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

    /**
     * Tries a reverse match of dispatcher information to route.
     * The route matches if all elements of the original $dispatch array match
     * in addition to all named parts of the pattern.
     * Any additional elements will only match if the route allows wildcard arguments.
     *
     * @param array $comparison The dispatch array to match.
     * @return Route A copy of the route object with the matches populated, or false on non-match.
     */
    public function matchDispatch(array $comparison) {
        if (array_diff_key($this->dispatch, $comparison)) {
            return false;
        }

        $dispatch     = $this->dispatch;
        $wildcardArgs = array();

        foreach ($comparison as $key => $value) {
            if (is_string($key) && isset($this->parts[$key])) {
                if ($this->matchPart($key, $value)) {
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

    /**
     * Formats a matched route into a URL.
     *
     * @return string A URL representing the route with current matched values.
     */
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

    /**
     * Access any value directly as property. Will return dispatch values and wildcard arguments.
     * If identically named dispatch and wildcard arguments exist, only the dispatch values are returned.
     * To access a wildcard argument with conflicting name, use wildcardArg($name). Better yet: avoid conflicts.
     *
     * @return mixed The requested value or null if it does not exist.
     */
    public function __get($name) {
        return $this->dispatchValue($name) ?: $this->wildcardArg($name);
    }

    /**
     * Returns the complete dispatch array.
     *
     * @return array
     */
    public function dispatchValues() {
        return $this->dispatch;
    }

    /**
     * Returns a specific dispatch value.
     *
     * @param string $name
     * @return mixed The value or false if no such value exists.
     */
    public function dispatchValue($name) {
        return isset($this->dispatch[$name]) ? $this->dispatch[$name] : false;
    }

    /**
     * Returns an array of matched wildcard arguments.
     *
     * @return array
     */
    public function wildcardArgs() {
        return $this->wildcardArgs;
    }

    /**
     * Access a matched wildcard arg directly by name or index.
     *
     * @param mixed $name Name of the named argument or index of unnamed argument.
     * @return mixed The value or false if no such argument exists.
     */
    public function wildcardArg($name) {
        return isset($this->wildcardArgs[$name]) ? $this->wildcardArgs[$name] : false;
    }

    /**
     * @return boolean Whether this route supports wildcard args.
     */
    public function supportsWildcardArgs() {
        return $this->wildcard;
    }

    /**
     * Returns the last matched URL if any.
     * May not be in sync with the current values set on the class if it has been modified.
     *
     * @return mixed The URL or null if non matched yet.
     */
    public function matchedUrl() {
        return $this->url;
    }

    /**
     * Returns the original pattern.
     *
     * @return string
     */
    public function pattern() {
        return $this->pattern;
    }

    /**
     * Set a dispatch value or wildcard value. If the value is not specified in the pattern, it will be set as wildcard value.
     * If the route does not support wildcards, an exception will be thrown.
     * If the value does not match the regex defined for the parameter (if any), an exception is thrown.
     *
     * @param string $name
     * @param mixed $value
     * @throws InvalidArgumentException if the name/value combintation is invalid for this route.
     */
    public function __set($name, $value) {
        if (array_key_exists($name, $this->dispatch)) {
            $this->setDispatchValue($name, $value);
        } else {
            $this->setWildcardArg($name, $value);
        }
    }

    /**
     * Sets a dispatch value.
     * If the value does not match the regex defined for the parameter (if any), an exception is thrown.
     *
     * @param string $name
     * @param mixed $value
     * @throws InvalidArgumentException if the name/value combintation is invalid for this route.
     */
    public function setDispatchValue($name, $value) {
        if (!array_key_exists($name, $this->dispatch)) {
            throw new InvalidArgumentException("Route does not specify dispatch value called $name");
        }
        if (isset($this->parts[$name]) && !$this->matchPart($name, $value)) {
            throw new InvalidArgumentException("Value '$value' does not match the rule {$this->parts[$name]} specified for $name");
        }
        $this->dispatch[$name] = $value;
    }

    /**
     * Set a wildcard value. If the route does not support wildcards, an exception will be thrown.
     *
     * @param string $name
     * @param mixed $value
     * @throws InvalidArgumentException if the route does not support wildcards.
     */
    public function setWildcardArg($name, $value) {
        if (!$this->wildcard) {
            throw new InvalidArgumentException("Parameter '$name' not specified in route and route does not allow wildcard arguments");
        }
        $this->wildcardArgs[$name] = $value;
    }


    /**
     * Initializes the object.
     */
    protected function initialize($pattern, array $dispatch) {
        $parts = explode('/', trim($pattern, '/'));
        $parts = $this->parseWildcard($parts);
        $parts = array_map(array($this, 'parsePart'), $parts);
        if ($parts) {
            $parts = call_user_func_array('array_merge', $parts);
        }

        $this->pattern  = $pattern;
        $this->parts    = $parts;
        $this->regex    = $this->partsToRegex($parts);
        $this->dispatch = $this->partsToDispatch($parts, $dispatch);
    }

    /**
     * Sets the wildcard flag based on the parts and returns
     * the parts array without wildcard part of it was found.
     *
     * @param array $parts
     * @return array Modified $parts array.
     */
    protected function parseWildcard(array $parts) {
        $lastIndex = count($parts) - 1;
        if ($parts[$lastIndex] === '*') {
            $this->wildcard = true;
            unset($parts[$lastIndex]);
        }
        return $parts;
    }

    /**
     * Parses a part into an array containing the name and regex pattern.
     *
     * @param string $part A single part without leading or trailing slash.
     * @return array Array of the format array(name => regex).
     *  Name is numeric for unnamed literal patterns.
     */
    protected function parsePart($part) {
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

    /**
     * Confirms whether a part matches a value.
     *
     * @param string $name Name of the part, i.e. key from $this->parts.
     * @param mixed $value A value to compare to.
     * @return boolean
     */
    protected function matchPart($name, $value) {
        if (!isset($this->parts[$name])) {
            throw new LogicException("Part called $name does not exist");
        }
        return preg_match("/^{$this->parts[$name]}\$/", $value);
    }

    /**
     * Turns an array of parts into a regular expression.
     *
     * @param array $parts
     * @return string Regular expression for all parts, without delimiters.
     *  Items are escaped expecting / as delimiters to be added later.
     */
    protected function partsToRegex(array $parts) {
        foreach ($parts as $key => &$value) {
            if (is_string($key)) {
                $value = "(?<$key>$value)";
            }
        }
        return '\/' . join('\/', $parts);
    }

    /**
     * Adds named parts to dispatch array with null values.
     * Validates that the pattern and dispatch array together form a valid route.
     *
     * @param array $parts
     * @param array $dispatch
     * @return array Modified $dispatch array.
     * @throws InvalidArgumentException if route is invalid due to duplicate keys in pattern and dispatch,
     *  or if both the pattern and dispatch information contain no named parameters.
     */
    protected function partsToDispatch(array $parts, array $dispatch) {
        foreach ($parts as $key => $regex) {
            if (is_string($key)) {
                if (isset($dispatch[$key])) {
                    throw new InvalidArgumentException("Both the pattern '{$this->pattern}' and the dispatch information contain the parameter '$key', route is invalid");
                }
                $dispatch[$key] = null;
            }
        }

        return $dispatch;
    }

    /**
     * Builds a complete regex that will match a valid URL.
     *
     * @return string
     */
    protected function buildRegex() {
        return sprintf('/^%s%s\/?$/', $this->regex, $this->wildcard ? '(.*)' : null);
    }

    /**
     * Merges named values matched from a URL into the dispatch array.
     * Modifies the matches in place to remove processed items, leaving unprocessed wildcard args.
     *
     * @param array &$matches
     */
    protected function mergeNamedMatches(array &$matches) {
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

    /**
     * Parses a wildcard argument string into named arguments.
     *
     * @param string $args Example: foo/bar:baz/42
     * @return array The $args string parsed into a key => value array.
     */
    protected function parseWildcardArgs($args) {
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

    /**
     * Interpolates dispatch values into a pattern, replacing named parts in the pattern with values.
     * Modifies the dispatch array in place, removing processed items, leaving over items not in the pattern.
     *
     * @param string $pattern
     * @param array &$dispatch
     * @return string Interpolated pattern, forming a URL
     */
    protected function interpolateParts($pattern, array &$dispatch) {
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
