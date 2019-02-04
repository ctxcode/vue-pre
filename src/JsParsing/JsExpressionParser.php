<?php

namespace LorenzV\PhpVueTemplatePrerender\JsParsing;

interface JsExpressionParser {

    /**
     * @param string $expression
     *
     * @return ParsedExpression
     */
    public function parse($expression);

}
