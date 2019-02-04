<?php

namespace LorenzV\PhpVueTemplatePrerender\JsParsing;

class CachingExpressionParser implements JsExpressionParser {

    /**
     * @var JsExpressionParser
     */
    private $parser;

    /**
     * @var ParsedExpression[] Indexed by expression string
     */
    private $expressionCache;

    public function __construct(JsExpressionParser $parser) {
        $this->parser = $parser;
    }

    /**
     * @param string $expression
     *
     * @return ParsedExpression
     */
    public function parse($expression) {
        $expression = $this->normalizeExpression($expression);
        if (isset($this->expressionCache[$expression])) {
            return $this->expressionCache[$expression];
        }

        $result = $this->parser->parse($expression);
        $this->expressionCache[$expression] = $result;
        return $result;
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
