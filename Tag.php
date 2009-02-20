<?php

/**
 * Tag base class definition
 *
 * PHP version 5
 *
 * LICENSE: The contents of this file are subject to the Mozilla Public License Version 1.1
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 *
 * The Original Code is Red Tree Systems Code.
 *
 * The Initial Developer of the Original Code is
 * Brandon Prudent <php-stl@redtreesystems.com>. All Rights Reserved.
 *
 * @category     Tag
 * @author       Red Tree Systems, LLC <php-stl@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.4
 * @link         http://php-stl.redtreesystems.com
 */

/**
 * Tag
 *
 * This class provides a tag handler base class
 *
 * @category     Tag
 */
abstract class Tag
{
    /**
     * The compiler to write to
     *
     * @var PHPSTLCompiler
     */
    protected $compiler;

    /**
     * Constructor
     *
     * @param PHPSTLCompiler $compiler
     */
    public function __construct(PHPSTLCompiler &$compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Dispatches a DOMElement to be handled by this Tag subclass instance
     *
     * Given an element named <ns:method />, this will look for a method
     * "method" first, then "_method", if neither is found, if method begins
     * with '__' or if the method is one defined direectly by the Tag class, a
     * PHPSTLCompilerException is thrown.
     *
     * The return value of the handler method is passed through, this is
     * typically void and doesn't matter.
     *
     * @param element DOMElement the element to handle
     * @return mixed usually void
     * @see PHPSTLCompiler::process
     */
    public function __dispatch(DOMElement &$element)
    {
        $method = substr(strstr($element->nodeName, ':'), 1);

        if (! method_exists($this, $method)) {
            if (! method_exists($this, "_$method")) {
                throw new PHPSTLCompilerException($this->compiler,
                    'Tag class '.get_class($this).
                    ' unable to handle element '.$element->nodeName
                );
            }
            $method = "_$method";
        }

        if (
            substr($method, 0, 2) == '__' ||
            in_array($method, get_class_methods('Tag'))
        ) {
            throw new PHPSTLCompilerException($this->compiler,
                "Won't call internal ".get_class($this).
                " method for element ".$element->nodeNode
            );
        }

        return $this->$method($element);
    }

    /**
     * Requires the attribute to be on $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param boolean $quote true if the value should be quoted [default]
     * @return the attribute value for key $attr
     */
    protected function requiredAttr(DOMElement &$element, $attr, $quote=true)
    {
        if (!$element->hasAttribute($attr)) {
            throw new InvalidArgumentException(
                "required attribute $attr missing from element $element->nodeName"
            );
        }

        $value = $element->getAttribute($attr);
        if ($quote) {
            $value = $this->quote($value);
        }
        return $value;
    }

    /**
     * Get an attribute from $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param mixed $default the default value
     * @return the attribute value for key $attr
     */
    protected function getAttr(DOMElement &$element, $attr, $default=null)
    {
        if ($element->hasAttribute($attr)) {
            return $this->quote($element->getAttribute($attr));
        } else {
            return $this->quote($default);
        }
    }

    /**
     * Get a raw attribute from $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param mixed $default the default value
     * @return the attribute value for key $attr
     */
    protected function getUnquotedAttr(DOMElement &$element, $attr, $default=null)
    {
        if ($element->hasAttribute($attr)) {
            return $element->getAttribute($attr);
        } else {
            return $default;
        }
    }

    /**
     * Get a boolean attribute from $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param mixed $default the default value
     * @return boolean a value matching the users intent
     */
    protected function getBooleanAttr(DOMElement &$element, $attr, $default=false)
    {
        if (!$element->hasAttribute($attr)) {
            return $default;
        }

        switch ($element->getAttribute($attr)) {
            case 'true':
            case 'yes':
                return true;
            case 'false':
            case 'no':
                return false;
        }

        throw new InvalidArgumentException(
            "Invalid boolean attribute $attr specified for $element->nodeName"
        );
    }

    /**
     * Processes child elements
     *
     * @param DOMElement $element
     * @return void
     */
    protected function process(DOMElement &$element)
    {
        if ($element->hasChildNodes()) {
            foreach($element->childNodes as $node) {
                $this->compiler->process($node);
            }
        }
    }

    /**
     * Quotes a subject if it's found to require one
     *
     * @param string $val The subject to quote (or not)
     * @return string The quoted (or not) value
     */
    protected function quote($val)
    {
        if (! isset($val)) {
            return null;
        }

        if ($this->needsQuote($val)) {
            return "'$val'";
        }

        return $val;
    }

    /**
     * Formats an array of elements as a comma-separated argument list suitable
     * for building a function call string.
     *
     * Example:
     *   $this->argList(array("'a'", null, "'b'", null))
     *   == "'a', null, 'b'"
     *
     *   $this->argList(array("'a'", null, "'b'", null), false)
     *   == "'a', null, 'b', null"
     *
     * Note, this function does NOT quote any arguments, since it is presumed
     * the most likely use case is something like:
     *   $this->argList(array(
     *     $this->getAttr(...),
     *     ...
     *   );
     * Where most arguments come from attribute parsing methods which already
     * quote things.
     *
     * @param args array the array list
     * @param pruneTail boolean default true, if true drops trailing nulls
     * from the arg list
     * @return string
     */
    protected function argList($args, $pruneTail=true)
    {
        if ($pruneTail) {
            while (! isset($args[count($args)-1])) {
                array_pop($args);
            }
        }
        $a = array();
        foreach ($args as &$arg) {
            array_push($a, isset($arg) ? $arg : 'null');

        }
        return implode(', ', $a);
    }

    /**
     * Collects attributes from an element and return them
     *
     * @param element DOMElement the element
     * @param attrs array array of attribute names to process; can
     * also contain named key => value pairs specifying default values in
     * case the element lacks an attribute; ordinal elements are equivalent to
     * specifying name => null
     * @param asArray boolean optional, default false, if true return an
     * associative array of collected values, otherwise returns a string
     * like ' attr="val" attr="val"'
     * @return string or array
     */
    protected function getAttributes(DOMElement &$element, $attrs, $asArray=false)
    {
        assert(is_array($attrs));

        $opts = array();
        foreach ($attrs as $attr => $default) {
            if (is_int($attr)) {
                $attr = $default;
                $default = null;
            }
            $value = $this->getUnquotedAttr($element, $attr, $default);
            if (isset($value)) {
                $opts[$attr] = $value;
            }
        }
        if ($asArray) {
            return $opts;
        } else {
            return $this->getAttributeString($opts);
        }
    }

    /**
     * Returns a tag attribute string like ' name="val" name="val"' from an
     * associative array.
     *
     * @param attrs array
     * @return string
     */
    protected function getAttributeString($attrs)
    {
        assert(is_array($attrs));
        $r = '';
        foreach ($attrs as $attr => $value) {
            $r .= " $attr=\"$value\"";
        }
        return $r;
    }

    /**
     * Returns true if the value requires quoting
     *
     * @return boolean
     */
    protected function needsQuote($val)
    {
        $char = strlen($val) ? $val[0] : '';

        return $char != '$' && $char != '@';
    }
}

?>
