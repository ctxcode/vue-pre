<?php

namespace LorenzV\PhpVueTemplatePrerender\JsParsing;

use RuntimeException;

interface ParsedExpression {

    /**
     * @param array $data
     *
     * @throws RuntimeException
     * @return mixed
     */
    public function evaluate(array $data);

}
