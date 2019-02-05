<?php

namespace LorenzV\VuePre;

class ConvertJsExpression {

    private $expression;
    private $inAttribute = false;

    public static function convert(String $expr, $options = []) {
        $ex = new self();
        $ex->expression = $expr;

        // Check options
        if (isset($options['inAttribute'])) {
            $ex->inAttribute = $options['inAttribute'] ? true : false;
        }

        //
        return $ex->parse();
    }

    public function parse() {

        $expr = $this->expression;

        return $this->parseValue($expr);
    }

    public function parseValue($expr) {

        $expr = trim($expr);

        if ($expr === '') {
            return '';
        }

        $match = null;

        $numReg = '[0-9]+';
        $boolReg = '(?:true|false)';
        $strReg = "'[^']+'";
        $varReg = '\!?[a-zA-Z_][a-zA-Z0-9_.]*';
        $opReg = ' *(?:===|==|<=|=>|<|>|!==|!=|\+|-|\/|\*|&&|\|\|) *';

        // Recursive regexes
        $exprReg = '\g<exprReg>';
        $arrReg = '\g<arrReg>';
        $objReg = '\g<objReg>';

        $funcReg = "\!?[a-zA-Z_][a-zA-Z0-9_]*$exprReg";
        $funcRegGroups = "\!?([a-zA-Z_][a-zA-Z0-9_]*)($exprReg)";

        $arrOrStrReg = "(?:$varReg|$strReg|$arrReg|$objReg|$exprReg|$funcReg)";

        $indexOfReg = "$arrOrStrReg\.indexOf$exprReg";
        $indexOfRegGroups = "($arrOrStrReg)\.indexOf($exprReg)";
        $lengthReg = "$arrOrStrReg\.length";
        $lengthRegGroups = "($arrOrStrReg)\.length";

        $valueReg = "(?:$numReg|$strReg|$boolReg|$lengthReg|$varReg|$arrReg|$objReg|$exprReg|$indexOfReg|$funcReg)";

        $compareReg = "$valueReg$opReg$valueReg";
        $compareRegGroups = "($valueReg)($opReg)($valueReg)";

        // $valueReg = "(?:$numReg|$strReg|$boolReg|$lengthReg|$varReg|$arrReg|$objReg|$exprReg|$indexOfReg|$funcReg|$compareReg)";

        if ($this->match($numReg, $expr, $match)) {
            return $expr;
        }
        if ($this->match($strReg, $expr, $match)) {
            return $expr;
        }
        if ($this->match($boolReg, $expr, $match)) {
            return $expr;
        }
        // .length , must be before var regex
        if ($this->match($lengthReg, $expr, $match)) {
            $this->match($lengthRegGroups, $expr, $match);
            $value = $this->parseValue($match[1]);
            return "\LorenzV\VuePre\ConvertJsExpression::length($value)"; // Make to return -1 instead of false
        }
        if ($this->match($varReg, $expr, $match)) {
            $pre = '';
            if ($expr[0] === '!') {
                $expr = substr($expr, 1);
                $pre = '!';
            }
            $path = explode('.', $expr);
            if (count($path) === 1) {
                return $pre . '$' . $expr;
            }
            $varName = $path[0];
            array_shift($path);
            return $pre . '\LorenzV\VuePre\ConvertJsExpression::getObjectValue($' . $varName . ', "' . implode(".", $path) . '")';
        }
        // (this === something)
        if ($this->match($exprReg, $expr, $match)) {
            return '(' . $this->parseValue(substr($expr, 1, -1)) . ')';
        }
        // [1, 'test', bool, true, hit ? or : miss]
        if ($this->match($arrReg, $expr, $match)) {
            $values = substr($expr, 1, -1);
            $values = explode(',', $values);
            $result = [];
            foreach ($values as $value) {
                $result[] = $this->parseValue(trim($value));
            }
            return '[' . implode(',', $result) . ']';
        }
        // something ? this : that
        if ($this->match("($valueReg) *\? *($valueReg) *\: *($valueReg)", $expr, $match)) {
            return $this->parseValue($match[1]) . ' ? ' . $this->parseValue($match[2]) . ' : ' . $this->parseValue($match[3]);
        }
        // something === something
        // echo htmlspecialchars("^$compareRegGroups$");
        // exit;
        if ($this->match($compareRegGroups, $expr, $match)) {
            // var_dump($expr);
            // exit;
            return $this->parseValue($match[1]) . $match[2] . $this->parseValue($match[3]);
        }
        // something === something && true
        if ($this->match("($valueReg)($opReg)($valueReg)($opReg)($valueReg)", $expr, $match)) {
            return $this->parseValue($match[1]) . $match[2] . $this->parseValue($match[3]) . $match[4] . $this->parseValue($match[5]);
        }
        // something === something && true || something !== 5
        if ($this->match("($valueReg)($opReg)($valueReg)($opReg)($valueReg)($opReg)($valueReg)", $expr, $match)) {
            return $this->parseValue($match[1]) . $match[2] . $this->parseValue($match[3]) . $match[4] . $this->parseValue($match[5]) . $match[6] . $this->parseValue($match[7]);
        }
        // Functions: myFunc(...)
        if ($this->match($funcReg, $expr, $match)) {
            $this->match($funcRegGroups, $expr, $match);
            $pre = '';
            if ($expr[0] === '!') {
                $pre = '!';
            }
            $subExpr = substr($match[2], 1, -1);
            return $pre . '$' . $match[1] . '(' . $this->parseValue($subExpr) . ')';
        }

        // Objects
        if ($this->match($objReg, $expr, $match)) {
            if ($expr === $this->expression) {
                // :class="{ active: true }"
                $subExpr = substr($expr, 1, -1);
                $pairs = explode(',', $subExpr);
                $result = [];
                foreach ($pairs as $pair) {
                    $split = explode(':', $pair);
                    if (count($split) < 2) {
                        $this->fail();
                    }
                    $key = trim($split[0]);
                    array_shift($split);
                    $value = $this->parseValue(trim(implode(':', $split)));
                    $result[] = '((' . $value . ') ? "' . $key . '" : "")';
                }
                return implode(' ', $result);
            } else {
                // if sub expresion like {hi:'hello'} === {hi:helloMessage}
                // These are pretty useless i think, but why not support it?

                // convert it to a string
                // FEATURE: maybe later we can convert it to a php object
                return "'" . addslashes($expr) . "'";
            }
        }

        // .indexOf()
        if ($this->match($indexOfReg, $expr, $match)) {
            $this->match($indexOfRegGroups, $expr, $match);
            $haystack = $this->parseValue($match[1]);
            $needle = $this->parseValue(substr($match[2], 1, -1));
            return "\LorenzV\VuePre\ConvertJsExpression::indexOf($haystack, $needle)"; // Make to return -1 instead of false
        }

        $this->fail();
    }

    public function match($regex, $expr, &$match) {

        // Recursive definitions
        $exprReg = '(?:(?<exprReg>\((?:[^\(\)]|(?:\g<exprReg>))*\))){0}';
        $arrReg = '(?:(?<arrReg>\[(?:[^\[\]]|(?:\g<arrReg>))*\])){0}';
        $objReg = '(?:(?<objReg>\{(?:[^\{\}]|(?:\g<objReg>))*\})){0}';

        $pre = "$exprReg$arrReg$objReg";

        $result = preg_match("/^(?J)$pre$regex$/", $expr, $match);
        $match = array_values(array_filter($match));
        return $result;
    }

    public function fail() {
        throw new \Exception('Cannot parse expression: ' . $this->expression);
    }

    public static function getObjectValue($obj, $path) {
        foreach (explode('.', $path) as $key) {
            $obj = is_array($obj) ? $obj[$key] : $obj->$key;
        }

        return $obj;
    }

    public static function indexOf($haystack, $needle) {
        if (is_array($haystack)) {
            $res = array_search($needle, $haystack, true); // true for strict
            if ($res === false) {return -1;}
            return $res;
        }
        if (is_string($haystack)) {
            $res = strpos($haystack, $needle);
            if ($res === false) {return -1;}
            return $res;
        }

        throw new Exception('indexOf() : variable was not a string or array');
    }

    public static function length($value) {
        if (is_array($value)) {
            return count($value);
        }
        if (is_string($value)) {
            return mb_strlen($value);
        }

        throw new Exception('length() : variable was not a string or array');
    }

}