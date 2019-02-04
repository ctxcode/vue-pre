<?php

namespace LorenzV\PhpVueTemplatePrerender\JsParsing;

class StringLiteral implements ParsedExpression {

    /**
     * @var string
     */
    private $string;

    public function __construct($string) {
        $this->string = $string;
    }

    /**
     * @param array $data ignored
     *
     * @return string as provided on construction time
     */
    public function evaluate(array $data) {
        return $this->string;
    }

}
