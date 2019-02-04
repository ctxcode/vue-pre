<?php

namespace LorenzV\PhpVueTemplatePrerender\JsParsing;

use RuntimeException;

class FilterApplication implements ParsedExpression {

    /**
     * @var callable
     */
    private $filter;

    /**
     * @var ParsedExpression[]
     */
    private $argumentExpressions;

    /**
     * @param callable $filter
     * @param ParsedExpression[] $argumentExpressions
     */
    public function __construct(callable $filter, array $argumentExpressions) {
        $this->filter = $filter;
        $this->argumentExpressions = $argumentExpressions;
    }

    /**
     * @param array $data
     *
     * @throws RuntimeException
     * @return mixed
     */
    public function evaluate(array $data) {
        $arguments = array_map(
            function (ParsedExpression $e) use ($data) {
                return $e->evaluate($data);
            },
            $this->argumentExpressions
        );

        return call_user_func_array($this->filter, $arguments);
        // TODO: Implement evaluate() method.
    }

}
