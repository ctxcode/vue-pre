<?php

namespace LorenzV\PhpVueTemplatePrerender\JsParsing;

class BasicJsExpressionParser implements JsExpressionParser {

    /**
     * @param string $expression
     *
     * @return ParsedExpression
     */
    public function parse($expression) {
        $expression = $this->normalizeExpression($expression);
        if (strncmp($expression, '!', 1) === 0) {
            return new NegationOperator($this->parse(substr($expression, 1)));
        } elseif (strncmp($expression, "'", 1) === 0) {
            return new StringLiteral(substr($expression, 1, -1));
        } else {
            $parts = explode('.', $expression);
            return new VariableAccess($parts);
        }
    }

    /**
     * @param string $expression
     *
     * @return string
     */
    protected function normalizeExpression($expression) {
        return trim($expression);
    }

}
