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
        return $this->parseValue($this->expression);
    }

    public function parseValue($expr) {

        if ($expr === '') {
            return '';
        }

        $match = null;

        $numReg = '[0-9]+';
        $boolReg = '(?:true|false)';
        $strReg = "'[^']+'";
        $varReg = '\!?[a-zA-Z_][a-zA-Z0-9_.]*';
        $opReg = ' ?(?:===|==|<=|=>|<|>|!==|!=|&&|\|\|) ?';
        $exprReg = '\(?:[^()]+\)';
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
            return $this->parseValue(trim($expr, '()'));
        }
        if (preg_match("/^($valueReg)($opReg)($valueReg)$/", $expr, $match)) {
            return $this->parseValue($match[1]) . $match[2] . $this->parseValue($match[3]);
        }

        // return $this->expression;
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