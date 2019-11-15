<?php

namespace VuePre;

class ConvertJsExpression {

    private $expression;
    private $inAttribute = null;

    public static function convert(String $expr, $options = []) {
        $ex = new self();
        $ex->expression = $expr;

        // Check options
        if (isset($options['inAttribute'])) {
            $ex->inAttribute = $options['inAttribute'];
        }

        //
        return $ex->parse();
    }

    public function parse() {

        $expr = $this->expression;

        try {
            // dump('O: ' . $expr);
            $result = $this->parseValue($expr);
            // dump('R: ' . $result);
        } catch (\Exception $e) {
            $this->fail();
        }

        return $result;
    }

    public function parseValue($expr, $inParams = false) {

        $expr = trim($expr);

        if ($expr === '') {
            return '';
        }

        $newExpr = '';
        $length = strlen($expr);
        $expects = [['value']];
        // $inString = false;
        // $inStringEndChar = null;
        $plus = false;

        $getString = function ($i, $endChar) use (&$expr) {
            $prevChar = $endChar;
            $string = $endChar;
            $i++;
            while (isset($expr[$i])) {
                $char = $expr[$i];
                if ($char === $endChar && $prevChar !== '\\') {
                    break;
                }
                $string .= $char;
                $prevChar = $char;
                $i++;
            }
            $string .= $endChar;
            return $string;
        };

        $parseBetween = function ($i, $openChar, $endChar, $allowCommas = false, $isObject = false) use (&$expr, &$getString) {
            $startIndex = $i;
            $depth = 0;
            $subExpr = '';
            $subExpressions = [];
            $i++;
            while (isset($expr[$i])) {
                $subChar = $expr[$i];

                if ($allowCommas) {
                    if ($subChar == ',' && $depth === 0) {
                        $subExpressions[] = $subExpr;
                        $subExpr = '';
                        $i++;
                        continue;
                    }
                }

                if ($depth === 0 && $subChar == $endChar) {
                    $subExpressions[] = $subExpr;
                    $phpExpressions = [];
                    if ($isObject) {
                        $result = '[';
                        foreach ($subExpressions as $subEx) {

                            // Get Key
                            $signIndex = strpos($subEx, ':');
                            if ($signIndex === false) {
                                throw new \Exception('Invalid object key "' . $subEx . '"');
                            }
                            $key = substr($subEx, 0, $signIndex);
                            if (empty($key) || !preg_match('/[a-zA-Z0-9_]/', $key)) {
                                throw new \Exception('Invalid object key syntax "' . $subEx . '"');
                            }

                            $result .= '"' . trim($key) . '" => ';
                            $result .= $this->parseValue(substr($subEx, $signIndex + 1));
                        }
                        $result .= ']';
                        return (object) ['length' => $i - $startIndex + 1, 'expr' => $result];
                    } else {
                        foreach ($subExpressions as $subEx) {
                            $phpExpressions[] = $this->parseValue($subEx);
                        }
                        return (object) ['length' => $i - $startIndex + 1, 'expr' => $openChar . implode(',', $phpExpressions) . $endChar];
                    }

                }

                if (preg_match('/[\'\"]/', $subChar)) {
                    $str = $getString($i, $subChar);
                    $subExpr .= $str;
                    $i += strlen($str);
                    continue;
                }

                $subExpr .= $subChar;

                if ($subChar == $openChar) {
                    $depth++;
                }

                if ($subChar == $endChar) {
                    $depth--;
                }

                $i++;
            }
            throw new \Exception('Could not find closing character "' . $endChar . '"');
        };

        for ($i = 0; $i < $length; $i++) {
            $char = $expr[$i];

            if ($char == ' ') {
                $newExpr .= ' ';
                continue;
            }

            $expect = array_pop($expects);

            $foundExpectation = false;

            foreach ($expect as $ex) {

                //////////////////////////

                if ($ex == 'value') {

                    // Valid starting characters
                    if (preg_match('/[a-zA-Z0-9_\!\[\{\(\"\']/', $char)) {
                        $dollarPos = null;
                        $valueExpr = '';
                        $funcName = '';

                        $pathOfExpr = '';
                        $path = '';

                        if ($char == '(') {
                            // (...)
                            $subExpr = $parseBetween($i, '(', ')', false, false);
                            $valueExpr .= $subExpr->expr;
                            $i += $subExpr->length;
                        }

                        if (preg_match('/[\'\"]/', $char)) {
                            $str = $getString($i, $char);
                            $valueExpr .= $str;
                            $i += strlen($str);
                        }

                        if ($char == '{') {
                            // Object
                            $subExpr = $parseBetween($i, '{', '}', true, true);
                            if ($this->inAttribute) {
                                $valueExpr .= '\VuePre\ConvertJsExpression::handleArrayInAttribute("' . $this->inAttribute . '", ';
                            } else {
                                $valueExpr .= '\VuePre\ConvertJsExpression::handleArrayToString(';
                            }
                            $valueExpr .= $subExpr->expr;
                            $valueExpr .= ')';
                            $i += $subExpr->length;
                        }

                        if ($char == '[') {
                            // Array
                            $subExpr = $parseBetween($i, '[', ']', true, false);
                            $valueExpr .= $subExpr->expr;
                            $i += $subExpr->length;
                        }

                        if ($char == '!') {
                            $valueExpr .= '!';
                            $i++;
                        }

                        while (isset($expr[$i])) {
                            $subChar = $expr[$i];
                            if (!preg_match('/[a-zA-Z0-9_\.\-\(\[]/', $subChar)) {
                                break;
                            }

                            if ($subChar == '.') {
                                if (empty($pathOfExpr) && is_numeric($funcName)) {
                                    $valueExpr .= '.';
                                } else {
                                    if (empty($pathOfExpr)) {
                                        $pathOfExpr = $valueExpr;
                                    } else {
                                        $path .= '.';
                                    }
                                }
                                $funcName = '';
                                $i++;
                                continue;
                            }

                            if ($subChar == '(') {
                                // Function params
                                if ($funcName == '') {
                                    break (2);
                                }
                                if (!empty($pathOfExpr)) {
                                    if ($funcName == 'indexOf') {
                                        $path = substr($path, 0, -8);
                                        if (!$path) {$path = '';}
                                    }
                                    if (!empty($path)) {
                                        $pre = '';
                                        if ($pathOfExpr[0] === '!') {
                                            $pre = '!';
                                            $pathOfExpr = substr($pathOfExpr, 1);
                                        }
                                        $valueExpr = $pre . '\VuePre\ConvertJsExpression::getObjectValue(' . $pathOfExpr . ', "' . $path . '", $this)';
                                    } else {
                                        $valueExpr = $pathOfExpr;
                                    }
                                    $pathOfExpr = '';
                                    $path = '';
                                }
                                $subExpr = $parseBetween($i, '(', ')', true, false);
                                if ($funcName == 'indexOf') {
                                    $valueExpr = '\VuePre\ConvertJsExpression::indexOf(' . $valueExpr . ', ' . $subExpr->expr . ')';
                                } else {
                                    $valueExpr .= $subExpr->expr;
                                }
                                $i += $subExpr->length;
                            } elseif ($subChar == '[') {
                                // Object key (no commas allowed)
                                $subExpr = $parseBetween($i, '[', ']', false, false);
                                $valueExpr .= $subExpr->expr;
                                $i += $subExpr->length;
                            } else {
                                // Var name
                                if ($dollarPos === null) {
                                    $dollarPos = strlen($valueExpr);
                                    $valueExpr .= '$';
                                }
                                if (!empty($pathOfExpr)) {
                                    $path .= $subChar;
                                } else {
                                    $valueExpr .= $subChar;
                                }
                                $funcName .= $subChar;
                            }

                            $i++;
                        }
                        $i--;

                        if (!empty($pathOfExpr)) {
                            $pre = '';
                            if ($pathOfExpr[0] === '!') {
                                $pre = '!';
                                $pathOfExpr = substr($pathOfExpr, 1);
                            }
                            $valueExpr = $pre . '\VuePre\ConvertJsExpression::getObjectValue(' . $pathOfExpr . ', "' . $path . '", $this)';
                        }

                        if (strlen($valueExpr) > 0) {
                            $checkExpr = substr($valueExpr, 1);
                            if (in_array(strtolower($checkExpr), ['true', 'false', 'null'], true)) {
                                $valueExpr = $checkExpr;
                            }
                            if (is_numeric($checkExpr)) {
                                $valueExpr = $checkExpr;
                            }
                        }

                        $newExpr .= $valueExpr;

                        if ($plus) {
                            $newExpr .= ')';
                            $plus = false;
                        }

                        $expects[] = ['operator'];
                        $foundExpectation = true;
                        break;
                    }

                }

                //////////////////////////

                if ($ex == 'operator') {
                    $operator = '';
                    $operators = '/[=+\-<>?:*%!&|]/';
                    while (isset($expr[$i]) && preg_match($operators, $expr[$i])) {
                        $operator .= $expr[$i];
                        $i++;
                    }
                    $i--;

                    $allowed = ['==', '===', '!=', '!==', '<=', '>=', '<', '>', '&&', '||', '?', ':', '+', '+=', '-', '-=', '*', '*=', '/', '/='];

                    if (empty($operator)) {
                        throw new \Exception('Expected an operator, but instead found "' . $char . '" at index: ' . $i);
                    }

                    if (!in_array($operator, $allowed, true)) {
                        throw new \Exception('Unexpected operator "' . $operator . '"');
                    }

                    if ($operator === '+') {
                        $plus = true;
                        $newExpr = '\VuePre\ConvertJsExpression::plus(' . $newExpr . ', ';
                    } elseif ($operator === '?') {
                        $newExpr = '\VuePre\ConvertJsExpression::toBool(' . $newExpr . ') ?';
                    } else {
                        $newExpr .= $operator;
                    }

                    $expects[] = ['value'];
                    $foundExpectation = true;
                }

                //////////////////////////

            }

            if (!$foundExpectation) {
                throw new \Exception('Unexpected character "' . $char . '"');
            }
        }

        return $newExpr;
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
            if ($key === 'length') {
                $obj = static::length($obj);
                continue;
            }
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

    public static function handleArrayInAttribute($name, $array) {
        if ($name == 'class') {
            $classes = [];
            foreach ($array as $className => $value) {
                $className = str_replace("'", '', $className);
                if ($value) {
                    $classes[] = $className;
                }
            }
            return implode(' ', $classes);
        }
        if ($name == 'style') {
            $styles = [];
            foreach ($array as $prop => $value) {
                $prop = str_replace("'", '', $prop);
                if ($value) {
                    $styles[] = $prop . ': ' . $value . ';';
                }
            }
            return implode(' ', $styles);
        }
        return '';
    }
    public static function handleArrayToString($array) {
        return json_encode($array);
    }

}