<?php

namespace LorenzV\PhpVueTemplatePrerender\JsParsing;

use RuntimeException;

class NegationOperator implements ParsedExpression {

    /**
     * @var ParsedExpression
     */
    private $expression;

    public function __construct(ParsedExpression $expression) {
        $this->expression = $expression;
    }

    /**
     * @param array $data
     *
     * @throws RuntimeException
     * @return bool
     */
    public function evaluate(array $data) {
        return !$this->expression->evaluate($data);
    }

}
