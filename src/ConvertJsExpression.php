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
            $openChars = [
                ']' => '[',
                ')' => '(',
                '}' => '{',
            ];
            $openChar = $openChars[$closeChar];
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
                throw new \Exception('Cannot find matching closing bracket ")" in expression "' . $this->expression . '"');
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
                throw new \Exception('Cannot find function params closing bracket ")" in expression "' . $this->expression . '"');
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
                if (!preg_match('/[a-zA-Z0-9_\(,\[\-\{\!]/', $char)) {
                    throw new \Exception('Unexpected character "' . $char . '" in expression "' . $this->expression . '"');
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

                } elseif ($char === '{') {
                    // Objects
                    $newExpr .= $lastValueExpr;
                    $lastValueExpr = '';

                    $params = [];
                    $paramsExpr = $getExprUntilClosingBracket($i, '}');
                    $i += strlen($paramsExpr);
                    $paramsExpr = substr($paramsExpr, 1, -1);
                    $params = $getParamExpressions($paramsExpr);
                    $lastValueExpr = '\VuePre\ConvertJsExpression::' . ($this->inAttribute ? 'handleArrayInAttribute' : 'handleArrayToString') . '([ ';
                    $first = true;
                    foreach ($params as $par) {
                        // Get & Remove key
                        $index = strpos($par, ':');
                        if ($index === false) {
                            throw new \Exception('Cant find object key in expression "' . $this->expression . '"');
                        }
                        $key = substr($par, 0, $index);
                        if (empty($key)) {
                            throw new \Exception('Cant find object key in expression "' . $this->expression . '"');
                        }
                        $par = substr($par, $index + 1);
                        $phpParam = $this->parseValue($par);
                        if (!$first) {
                            $lastValueExpr .= ',';
                        }
                        $lastValueExpr .= '"' . $key . '" => ' . $phpParam;
                        $first = false;
                    }
                    $lastValueExpr .= '])';
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

                    // Convert valueExpr to phpExpr and add to result
                    $pathEx = explode('.', $path);
                    $pathEx = array_filter($pathEx);
                    $indexOfFunc = false;
                    $lengthFunc = false;

                    if (count($pathEx) > 0 && $pathEx[count($pathEx) - 1] === 'indexOf' && $isFunc) {
                        $indexOfFunc = true;
                        array_pop($pathEx);
                    }
                    if (count($pathEx) > 0 && $pathEx[count($pathEx) - 1] === 'length' && !$isFunc) {
                        $lengthFunc = true;
                        array_pop($pathEx);
                    }

                    if (empty(trim($lastValueExpr)) && count($pathEx) > 0) {
                        $varName = '$' . $pathEx[0];
                        array_shift($pathEx);
                    } else {
                        $varName = $lastValueExpr;
                    }

                    if (is_numeric($path)) {
                        $valueExpr .= $path;
                    } elseif (in_array(strtolower($path), ['true', 'false', 'null'], true)) {
                        $valueExpr .= strtolower($path);
                    } elseif (count($pathEx) > 0) {
                        $valueExpr .= '\VuePre\ConvertJsExpression::getObjectValue(' . $varName . ', "' . implode('.', $pathEx) . '", $this)';
                    } else {
                        $valueExpr .= $varName;
                    }

                    if ($lengthFunc) {
                        $valueExpr .= '\VuePre\ConvertJsExpression::length(' . $valueExpr . ')';
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

                        if ($indexOfFunc) {
                            $valueExpr = '\VuePre\ConvertJsExpression::indexOf(' . $valueExpr . ', ' . implode(',', $phpParams) . ')';
                        } else {
                            $valueExpr = '(' . $valueExpr . '(' . implode(',', $phpParams) . '))';
                        }
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
                throw new \Exception('Unexpected character "' . $char . '" in expression "' . $this->expression . '"');
            }

            $operators = '/[=+\-<>?:*%!&|]/';
            if (preg_match($operators, $char)) {
                if (!in_array($lastExprType, ['variable', 'value'], true)) {
                    throw new \Exception('Unexpected character "' . $char . '" in expression "' . $this->expression . '"');
                }

                $lastExprType = 'operator';
                $expectValue = true;

                if ($plus) {
                    $lastValueExpr .= ')';
                    $newExpr .= $lastValueExpr;
                    $lastValueExpr = $newExpr;
                    $newExpr = '';
                    $plus = false;
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
                    } elseif ($nextChar == '&' && in_array($char, ['&'], true)) {
                        $i++;
                        $op .= $nextChar;
                    } elseif ($nextChar == '|' && in_array($char, ['|'], true)) {
                        $i++;
                        $op .= $nextChar;
                    } else {
                    }
                }

                if ($op === '+') {
                    $plus = true;
                    $lastValueExpr = '\VuePre\ConvertJsExpression::plus(' . $lastValueExpr . ', ';
                } elseif ($op === '?') {
                    $lastValueExpr = '\VuePre\ConvertJsExpression::toBool(' . $lastValueExpr . ')';
                    $lastValueExpr .= $op;
                } else {
                    $lastValueExpr .= $op;
                }

                $newExpr .= $lastValueExpr;
                $lastValueExpr = '';

                continue;
            }

            throw new \Exception('Unexpected character "' . $char . '" in expression "' . $this->expression . '"');
        }

        if ($plus) {
            $lastValueExpr .= ')';
        }

        $newExpr .= $lastValueExpr;

        return $newExpr;
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

    public static function handleArrayInAttribute($array) {
        $classes = [];
        foreach ($array as $className => $value) {
            if ($value) {
                $classes[] = $className;
            }
        }
        return implode(' ', $classes);
    }
    public static function handleArrayToString($array) {
        return json_encode($array);
    }

}