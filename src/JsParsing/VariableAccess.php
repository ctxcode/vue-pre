<?php

namespace LorenzV\PhpVueTemplatePrerender\JsParsing;

use RuntimeException;

class VariableAccess implements ParsedExpression {

    /**
     * @var string[]
     */
    private $pathParts;

    public function __construct(array $pathParts) {
        $this->pathParts = $pathParts;
    }

    /**
     * @param array $data
     *
     * @throws RuntimeException when a path element cannot be found in the array
     * @return mixed
     */
    public function evaluate(array $data) {
        $value = $data;
        foreach ($this->pathParts as $key) {
            if (!array_key_exists($key, $value)) {
                $expression = implode('.', $this->pathParts);
                throw new RuntimeException("Undefined variable '{$expression}'");
            }
            $value = $value[$key];
        }
        return $value;
    }

}
