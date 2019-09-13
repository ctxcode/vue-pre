<?php

namespace VuePre;

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

        // dump('O: ' . $expr);

        $result = $this->parseValue($expr);
        // dump('R: ' . $result);
        return $result;
    }

    public function parseValue($expr) {

        $expr = trim($expr);

        if ($expr === '') {
            return '';
        }

        $length = strlen($expr);

        $depth = 0;
        $newExpr = '';
        $lastExprType = null;
        $lastValueExpr = '';

        $expectValue = true;
        $inStringChar = '';
        $inString = false;
        $inFuncParams = false;
        $plus = false;

        $expectAtDepth = [];

        $getExprUntilClosingBracket = function ($start, $closeChar = ')') use (&$expr) {
            $depth = 0;
            $result = '';
            $inString = false;
            $inStringChar = '';
            $openChar = $closeChar == ')' ? '(' : '[';
            while (isset($expr[$start])) {
                $c = $expr[$start];
                $result .= $c;
                if ($inString) {
                    $prevChar = $start > 0 ? $expr[$start - 1] : null;
                    if ($c === $inStringChar && $prevChar !== '/') {
                        // End string
                        $inString = false;
                    }
                    $start++;
                    continue;
                }
                if (preg_match('/[\'\"]/', $c)) {
                    $inString = true;
                    $inStringChar = $c;
                    $start++;
                    continue;
                }
                if ($c == $openChar && !$inString) {
                    $depth++;
                }
                if ($c == $closeChar && !$inString) {
                    $depth--;
                    if ($depth == 0) {
                        break;
                    }
                }
                $start++;
            }
            if ($depth > 0 || $inString) {
                throw new \Exception('Cannot find matching closing bracket ")"');
            }
            return $result;
        };

        $getParamExpressions = function ($expr) {
            $result = [];
            $param = '';
            $depth = 0;
            $start = 0;
            $inString = false;
            $inStringChar = '';
            while (isset($expr[$start])) {
                $c = $expr[$start];
                $param .= $c;
                if ($inString) {
                    $prevChar = $start > 0 ? $expr[$start - 1] : null;
                    if ($c === $inStringChar && $prevChar !== '/') {
                        // End string
                        $inString = false;
                    }
                    $start++;
                    continue;
                }
                if (preg_match('/[\'\"]/', $c)) {
                    $inString = true;
                    $inStringChar = $c;
                    $start++;
                    continue;
                }
                if ($c == '(' && !$inString) {
                    $depth++;
                }
                if ($c == ')' && !$inString) {
                    $depth--;
                }
                if ($c == ',' && $depth === 0) {
                    $param = substr($param, 0, -1);
                    $result[] = $param;
                    $param = '';
                }
                $start++;
            }
            $param = trim($param);
            if (!empty($param)) {
                $result[] = $param;
            }
            if ($depth > 0 || $inString) {
                throw new \Exception('Cannot find function params closing bracket ")"');
            }
            return $result;
        };

        // Expect
        // ValueChar: variable, object property, boolean, number, function call
        // ValueCharOrScope: valueChar or open bracket

        for ($i = 0; $i < $length; $i++) {
            $char = $expr[$i];

            if ($inString) {
                $prevChar = $i > 0 ? $expr[$i - 1] : null;
                if ($char === $inStringChar && $prevChar !== '/') {
                    // End string
                    $inString = false;
                    $expectValue = false;
                    $lastExprType = 'value';
                }
                $lastValueExpr .= $char;
                continue;
            }

            if ($char == ' ') {
                $lastValueExpr .= $char;
                continue;
            }

            if ($char == '.' && $lastExprType == 'value') {
                // Either value
                $lastValueExpr .= $char;
                $expectValue = true;
                continue;
            }

            if ($expectValue) {
                if (preg_match('/[\'\"]/', $char)) {
                    $inString = true;
                    $inStringChar = $char;
                    $lastValueExpr .= $char;
                    continue;
                }
                if (!preg_match('/[a-zA-Z0-9_\(,\[]/', $char)) {
                    throw new \Exception('Unexpected character "' . $char . '"');
                }
                if ($char === '[') {
                    // Array
                    $newExpr .= $lastValueExpr;
                    $lastValueExpr = '';

                    $params = [];
                    $paramsExpr = $getExprUntilClosingBracket($i, ']');
                    $i += strlen($paramsExpr);
                    $paramsExpr = substr($paramsExpr, 1, -1);
                    $params = $getParamExpressions($paramsExpr);
                    $phpParams = [];
                    foreach ($params as $par) {
                        $phpParams[] = $this->parseValue($par);
                    }
                    $lastValueExpr = '[' . implode(',', $phpParams) . ']';
                    $i--;

                    $lastExprType = 'value';
                    $expectValue = false;
                    continue;

                } elseif ($char !== '(') {
                    // Capture value string

                    $valueExpr = '';

                    $path = '';
                    while (isset($expr[$i])) {
                        if (!preg_match('/[a-zA-Z0-9_\.\$]/', $expr[$i])) {
                            $lastExprType = 'variable';
                            break;
                        }
                        $path .= $expr[$i];
                        $i++;
                    }

                    // Convert valueExpr to phpExpr and add to result
                    $pathEx = explode('.', $path);

                    if (empty($lastValueExpr)) {
                        $varName = '$' . $pathEx[0];
                        unset($pathEx[0]);
                    } else {
                        $varName = $lastValueExpr;
                    }

                    if (is_numeric($path)) {
                        $valueExpr .= $path;
                    } elseif (strtolower($path) == 'null') {
                        $valueExpr .= 'null';
                    } elseif (count($pathEx) > 0) {
                        $valueExpr .= '(\VuePre\ConvertJsExpression::getObjectValue(' . $varName . ', "' . implode('.', $pathEx) . '", $this))';
                    } else {
                        $valueExpr .= '(' . $varName . ')';
                    }

                    // Look ahead for function
                    $isFunc = false;
                    while (isset($expr[$i])) {
                        if ($expr[$i] == ' ') {
                            $i++;
                            continue;
                        }
                        if ($expr[$i] == '(') {
                            $isFunc = true;
                            break;
                        }
                        $i--;
                        break;
                    }

                    if ($isFunc) {
                        // Function
                        $params = [];
                        $paramsExpr = $getExprUntilClosingBracket($i);
                        $i += strlen($paramsExpr);
                        $paramsExpr = substr($paramsExpr, 1, -1);
                        $params = $getParamExpressions($paramsExpr);
                        $phpParams = [];
                        foreach ($params as $par) {
                            $phpParams[] = $this->parseValue($par);
                        }
                        $valueExpr = '(' . $valueExpr . '(' . implode(',', $phpParams) . '))';
                    }

                    $lastValueExpr = $valueExpr;
                    //
                    $expectValue = false;
                    continue;
                }
            }

            if ($char == '(') {
                $bracketExpr = $getExprUntilClosingBracket($i);
                $i += strlen($bracketExpr);
                $bracketExpr = substr($bracketExpr, 1, -1);
                $lastValueExpr .= '(' . $this->parseValue($bracketExpr) . ')';
                $lastExprType = 'value';
                $expectValue = false;
                continue;
            }

            if ($char == ')') {
                throw new \Exception('Unexpected character "' . $char . '"');
            }

            $operators = '/[=+\-<>?:*%!]/';
            if (preg_match($operators, $char)) {
                if (!in_array($lastExprType, ['variable', 'value'], true)) {
                    throw new \Exception('Unexpected character "' . $char . '"');
                }

                $lastExprType = 'operator';
                $expectValue = true;

                if ($plus) {
                    $lastValueExpr .= ')';
                }

                $op = $char;

                $nextChar = isset($expr[$i + 1]) ? $expr[$i + 1] : null;
                if ($nextChar) {
                    if ($nextChar == '=' && in_array($char, ['=', '-', '+', '*', '>', '<', '!'], true)) {
                        $i++;
                        $op .= $nextChar;
                        if ($nextChar == '=' && (isset($expr[$i + 1]) ? $expr[$i + 1] : null) == '=') {
                            $i++;
                            $op .= '=';
                        }
                    } elseif ($nextChar == '-' && in_array($char, ['-'], true)) {
                        $i++;
                        $op .= $nextChar;
                    } elseif ($nextChar == '+' && in_array($char, ['+'], true)) {
                        $i++;
                        $op .= $nextChar;
                    } else {
                    }
                }

                if ($op === '+') {
                    $plus = true;
                    $lastValueExpr = '\VuePre\ConvertJsExpression::plus(' . $lastValueExpr . ', ';
                } else {
                    $lastValueExpr .= $op;
                }
                continue;
            }

            throw new \Exception('Unexpected character "' . $char . '"');
        }

        if ($plus) {
            $lastValueExpr .= ')';
        }

        $newExpr .= $lastValueExpr;

        return $newExpr;
    }

    public function _parseValue($expr) {

        $expr = trim($expr);

        if ($expr === '') {
            return '';
        }

        $match = null;

        $numReg = '-?\d+\.?\d*';
        $boolReg = '(?:true|false|null)';
        $strReg = "'[^']*'";
        $strDqReg = '"[^"]*"';
        $varReg = '\!?[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_]+|\g<arrReg>)*';
        $opReg = ' *(?:===|==|<=|=>|<|>|!==|!=|\+|-|\/|\*|&&|\|\|) *';

        // Recursive regexes
        $exprReg = '\g<exprReg>';
        $arrReg = '\g<arrReg>';
        $objReg = '\g<objReg>';

        $funcReg = "\!?[a-zA-Z_][a-zA-Z0-9_]*$exprReg";
        $funcRegGroups = "\!?([a-zA-Z_][a-zA-Z0-9_]*)($exprReg)";

        $arrOrStrReg = "(?:$varReg|$strReg|$strDqReg|$arrReg|$objReg|$exprReg|$funcReg)";

        $indexOfReg = "$arrOrStrReg\.indexOf$exprReg";
        $indexOfRegGroups = "($arrOrStrReg)\.indexOf($exprReg)";
        $lengthReg = "$arrOrStrReg\.length";
        $lengthRegGroups = "($arrOrStrReg)\.length";

        $valueReg = "(?:$numReg|$strReg|$strDqReg|$boolReg|$lengthReg|$varReg|$arrReg|$objReg|$exprReg|$indexOfReg|$funcReg)";

        if ($this->match($numReg, $expr, $match)) {
            return $expr;
        }
        if ($this->match($strReg, $expr, $match)) {
            return $expr;
        }
        if ($this->match($strDqReg, $expr, $match)) {
            return $expr;
        }
        if ($this->match($boolReg, $expr, $match)) {
            return $expr;
        }
        // .length , must be before var regex
        if (strpos($expr, '.length') > 0 && $this->match($lengthReg, $expr, $match)) {
            $this->match($lengthRegGroups, $expr, $match);
            $value = $this->parseValue($match[1]);
            return "\VuePre\ConvertJsExpression::length($value)"; // Make to return -1 instead of false
        }
        // Variable
        if ($this->match($varReg, $expr, $match)) {
            $pre = '';
            if ($expr[0] === '!') {
                $expr = substr($expr, 1);
                $pre = '!';
            }

            // Var name
            $varName = $expr;
            if ($pos = strpos($varName, ".")) {$varName = substr($varName, 0, $pos);}
            if ($pos = strpos($varName, "[")) {$varName = substr($varName, 0, $pos);}

            $subExpr = substr($expr, strlen($varName));
            $this->match("(\.[a-zA-Z0-9_]+|\g<arrReg>)", $expr, $matches, ['all' => true]);

            if (count($matches) === 0 || count($matches[1]) === 0) {
                return $pre . '$' . $expr;
            }

            // Recursive
            $path = [];
            foreach ($matches[1] as $amatch) {
                if ($amatch[0] === '.') {
                    $path[] = "'" . substr($amatch, 1) . "'";
                }
                if ($amatch[0] === '[') {
                    $path[] = $this->parseValue(substr($amatch, 1, -1));
                }
            }

            return $pre . '\VuePre\ConvertJsExpression::getObjectValue($' . $varName . ', [' . implode(", ", $path) . '], $this)';
        }
        // ( ... )
        if ($expr[0] === '(') {
            if ($this->match($exprReg, $expr, $match)) {
                return '(' . $this->parseValue(substr($expr, 1, -1)) . ')';
            }
        }
        // [ ... ]
        if ($expr[0] === '[') {
            if ($this->match($arrReg, $expr, $match)) {
                $values = substr($expr, 1, -1);
                $values = explode(',', $values);
                $result = [];
                foreach ($values as $value) {
                    $result[] = $this->parseValue(trim($value));
                }
                return '[' . implode(',', $result) . ']';
            }
        }
        // something ? this : that
        if ($this->match("($valueReg) *\? *($valueReg) *\: *($valueReg)", $expr, $match)) {
            return '\VuePre\ConvertJsExpression::toBool(' . $this->parseValue($match[1]) . ') ? ' . $this->parseValue($match[2]) . ' : ' . $this->parseValue($match[3]);
        }
        // something === something && ... || ... + ...
        if (preg_match("/$opReg/", $expr) && $this->match("($valueReg)((?:$opReg$valueReg)+)", $expr, $match)) {
            $result = $this->parseValue($match[1]);

            $this->match("$opReg$valueReg", $match[2], $matches, ['all' => true]);

            // Recursive
            foreach ($matches[0] as $amatch) {
                if ($this->match("($opReg)($valueReg)", $amatch, $subMatch)) {

                    $expr2 = $this->parseValue($subMatch[2]);
                    $op = trim($subMatch[1]);
                    if ($op === '+') {
                        $result = "\VuePre\ConvertJsExpression::plus($result, $expr2)";
                    } else {
                        $result = $result . ' ' . $op . ' ' . $expr2;
                    }
                }
            }

            return $result;
        }

        // Functions: myFunc(...)
        if ($this->match($funcReg, $expr, $match)) {
            $this->match($funcRegGroups, $expr, $match);
            $pre = '';
            if ($expr[0] === '!') {
                $pre = '!';
            }
            $subExpr = substr($match[2], 1, -1);
            $isVar = true;
            if ($match[1] == 'typeof') {
                $isVar = false;
                $match[1] = '\VuePre\ConvertJsExpression::typeof';
            }
            $params = explode(',', $subExpr);
            foreach ($params as $k => $param) {
                $params[$k] = $this->parseValue($param);
            }

            return $pre . ($isVar ? '$' : '') . $match[1] . '(' . implode(', ', $params) . ')';
        }

        // { ... }
        if ($expr[0] === '{') {
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
            $this->fail();
        }

        // .indexOf()
        if (strpos($expr, '.indexOf(') > 0 && $this->match($indexOfReg, $expr, $match)) {
            $this->match($indexOfRegGroups, $expr, $match);
            $haystack = $this->parseValue($match[1]);
            $needle = $this->parseValue(substr($match[2], 1, -1));
            return "\VuePre\ConvertJsExpression::indexOf($haystack, $needle)"; // Make to return -1 instead of false
        }

        $this->fail();
    }

    public function match($regx, $expr, &$match, $options = []) {

        // Recursive definitions
        $exprReg = '(?:(?<exprReg>\((?:[^\(\)]|(?:\g<exprReg>))*\))){0}';
        $arrReg = '(?:(?<arrReg>\[(?:[^\[\]]|(?:\g<arrReg>))*\])){0}';
        $objReg = '(?:(?<objReg>\{(?:[^\{\}]|(?:\g<objReg>))*\})){0}';

        $pre = "$exprReg$arrReg$objReg";
        $regex = "/^(?J)$pre$regx$/";
        $regexAll = "/(?J)$pre$regx/";

        if (isset($options['debug']) && $options['debug']) {
            echo '<div>' . $expr . '</div>';
            echo htmlspecialchars($regex);
            exit;
        }

        if (isset($options['all']) && $options['all']) {
            $result = preg_match_all($regexAll, $expr, $matches);
            $match = [];
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {continue;}
                $v = array_values(array_filter($v, '\VuePre\ConvertJsExpression::filterRegexResults'));
                if (count($v) > 0) {
                    $match[] = $v;
                }
            }
        } else {
            $result = preg_match($regex, $expr, $match);
            $match = array_values(array_filter($match, '\VuePre\ConvertJsExpression::filterRegexResults'));
        }

        return $result;
    }

    public static function filterRegexResults($res) {
        if ($res === '') {return false;}
        return true;
    }

    public function fail() {
        throw new \Exception('VuePre doesnt understand this JS expression: "' . $this->expression . '"', 100);
    }

    // Runtime functions

    // Handle plus sign between 2 values
    public static function plus($val1, $val2) {
        if (is_object($val1) && get_class($val1) == 'VuePre\Undefined') {
            $val1 = 'undefined';
        }
        if (is_object($val2) && get_class($val2) == 'VuePre\Undefined') {
            $val2 = 'undefined';
        }
        if (is_string($val1)) {
            return $val1 . $val2;
        }
        if (is_numeric($val1)) {
            return $val1 + $val2;
        }

        return $val1 . $val2;
    }

    public static function getObjectValue($obj, $path, $cacheTemplate) {
        $path = explode('.', $path);
        foreach ($path as $key) {
            if (is_array($obj)) {
                if (!array_key_exists($key, $obj)) {
                    // return '';
                    return new Undefined($cacheTemplate);
                }
                $obj = $obj[$key];
            } else {
                if (!property_exists($obj, $key)) {
                    // return '';
                    return new Undefined($cacheTemplate);
                }
                $obj = $obj->$key;
            }
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

    public static function toBool($value) {
        if (gettype($value) == 'object' && get_class($value) === 'VuePre\Undefined') {
            return false;
        }

        return $value;
    }

    public static function typeof($value) {
        $type = gettype($value);
        if ($type == 'object' && get_class($value) === 'VuePre\Undefined') {
            return 'undefined';
        }

        return $type;
    }

}