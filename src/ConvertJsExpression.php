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
        $expr = trim($expr);

        if ($expr === '') {
            return '';
        }

        return $this->parseValue($expr);
    }

    public function parseValue($expr) {

        $match = null;

        $numReg = '[0-9]+';
        $boolReg = '(?:true|false)';
        $strReg = "'[^']+'";
        $varReg = '\!?[a-zA-Z_][a-zA-Z0-9_.]*';
        $opReg = ' ?(?:===|==|<=|=>|<|>|!==|!=|&&|\|\|) ?';
        $exprReg = '\([^()]+\)';
        $objReg = '\{[^{}]+\}';
        $valueReg = "(?:$numReg|$strReg|$boolReg|$varReg|$exprReg)";

        if (preg_match("/^$numReg$/", $expr, $match)) {
            return $expr;
        }
        if (preg_match("/^$strReg$/", $expr, $match)) {
            return $expr;
        }
        if (preg_match("/^$boolReg$/", $expr, $match)) {
            return $expr;
        }
        if (preg_match("/^$varReg$/", $expr, $match)) {
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
        if (preg_match("/^($exprReg)$/", $expr, $match)) {
            return '(' . $this->parseValue(trim($expr, '()')) . ')';
        }
        // something ? this : that
        if (preg_match("/^($valueReg) ?\? ?($valueReg) ?\: ($valueReg)?$/", $expr, $match)) {
            return $this->parseValue($match[1]) . ' ? ' . $this->parseValue($match[2]) . ' : ' . $this->parseValue($match[3]);
        }
        // something === something
        if (preg_match("/^($valueReg)($opReg)($valueReg)$/", $expr, $match)) {
            return $this->parseValue($match[1]) . $match[2] . $this->parseValue($match[3]);
        }
        // something === something && true
        if (preg_match("/^($valueReg)($opReg)($valueReg)($opReg)($valueReg)$/", $expr, $match)) {
            return $this->parseValue($match[1]) . $match[2] . $this->parseValue($match[3]) . $match[4] . $this->parseValue($match[5]);
        }
        // something === something && true || something !== 5
        if (preg_match("/^($valueReg)($opReg)($valueReg)($opReg)($valueReg)($opReg)($valueReg)$/", $expr, $match)) {
            return $this->parseValue($match[1]) . $match[2] . $this->parseValue($match[3]) . $match[4] . $this->parseValue($match[5]) . $match[6] . $this->parseValue($match[7]);
        }

        // :class="{ active: true }"
        if (preg_match("/^($objReg)$/", $expr, $match)) {
            $expr = trim($expr, '{}');
            $pairs = explode(',', $expr);
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
        }

        $this->fail();
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

}