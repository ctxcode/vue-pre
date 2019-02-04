<?php

namespace LorenzV\PhpVueTemplatePrerender\FilterExpressionParsing;

class FilterCall {

    /**
     * @var string
     */
    private $filterName;

    /**
     * @var string[]
     */
    private $arguments = [];

    /**
     * @param string $filterName
     * @param string[] $arguments
     */
    public function __construct($filterName, array $arguments) {
        $this->filterName = $filterName;
        $this->arguments = $arguments;
    }

    /**
     * @return string
     */
    public function filterName() {
        return $this->filterName;
    }

    /**
     * @return string[]
     */
    public function arguments() {
        return $this->arguments;
    }

}
