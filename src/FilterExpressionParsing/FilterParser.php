<?php

namespace LorenzV\PhpVueTemplatePrerender\FilterExpressionParsing;

class FilterParser {

    const VALID_DIVISION_CHAR_REGEX = '/[\w).+\-_$\]]/';

    private $filters = [];

    private $expressions = [];
    private $expressionStart = 0;

    private $nowParsingFilterList = false;
    private $filterArgStart = null;
    private $filterArgEnd = null;

    /**
     * @param string $exp
     *
     * @return ParseResult
     */
    public function parse($exp) {
        $inSingle = false;
        $inDouble = false;
        $inTemplateString = false;
        $inRegex = false;
        $curly = 0;
        $square = 0;
        $paren = 0;
        $currentFilterStart = 0;
        $c = null;
        $prev = null;
        $pos = null;

        $this->resetState();

        $len = strlen($exp);
        for ($pos = 0; $pos < $len; $pos++) {
            $prev = $c;
            $c = $exp[$pos];
            if ($inSingle) {
                if ($c === "'" && $prev !== '\\') {
                    $inSingle = false;
                }
            } elseif ($inDouble) {
                if ($c === '"' && $prev !== '\\') {
                    $inDouble = false;
                }
            } elseif ($inTemplateString) {
                if ($c === '`' && $prev !== '\\') {
                    $inTemplateString = false;
                }
            } elseif ($inRegex) {
                if ($c === '/' && $prev !== '\\') {
                    $inRegex = false;
                }
            } elseif ($c === '|' &&
                $exp[$pos + 1] !== '|' &&
                $exp[$pos - 1] !== '|' &&
                !$curly && !$square && !$paren
            ) {
                if (!$this->nowParsingFilterList) {
                    $this->finishExpression($exp, $pos);
                    $currentFilterStart = $pos + 1;
                } else {
                    $this->pushFilter($exp, $pos, $currentFilterStart);
                    $currentFilterStart = $pos + 1;
                }
                $this->nowParsingFilterList = true;
            } elseif ($c === ',' && !$this->nowParsingFilterList && !$curly && !$square && !$paren) {
                $this->finishExpression($exp, $pos);
            } else {
                switch ($c) {
                case '"':
                    $inDouble = true;
                    break;
                case "'":
                    $inSingle = true;
                    break;
                case '`':
                    $inTemplateString = true;
                    break;
                case '(':
                    if ($this->nowParsingFilterList && $paren === 0) {
                        $this->filterArgStart = $pos + 1;
                    }
                    $paren++;

                    break;
                case ')':
                    $paren--;
                    if ($this->nowParsingFilterList && $paren === 0) {
                        $this->filterArgEnd = $pos - 1;
                    }
                    break;
                case '[':
                    $square++;
                    break;
                case ']':
                    $square--;
                    break;
                case '{':
                    $curly++;
                    break;
                case '}':
                    $curly--;
                    break;
                }
                if ($c === '/') {
                    $p = null;
                    // find first non-whitespace prev char
                    for ($j = $pos - 1; $j >= 0; $j--) {
                        $p = $exp[$j];
                        if ($p !== ' ') {
                            break;
                        }
                    }

                    if (!$p || !preg_match(self::VALID_DIVISION_CHAR_REGEX, $p)) {
                        $inRegex = true;
                    }
                }
            }
        }

        if (!$this->nowParsingFilterList) {
            $this->finishExpression($exp, $pos);
        }

        if ($currentFilterStart !== 0) {
            $this->pushFilter($exp, $pos, $currentFilterStart);
        }

        return new ParseResult($this->expressions, $this->filters);
    }

    /**
     * @param string $exp
     * @param int $pos
     * @param int $currentFilterStart
     */
    private function pushFilter($exp, $pos, $currentFilterStart) {
        $filterBody = trim(substr($exp, $currentFilterStart, $pos - $currentFilterStart));
        $openingParenthesisPos = strpos($filterBody, '(');
        if ($openingParenthesisPos === false) {
            $filterName = $filterBody;
            $args = [];
        } else {
            $filterName = substr($filterBody, 0, $openingParenthesisPos);
            $argString = substr(
                $exp,
                $this->filterArgStart,
                $this->filterArgEnd - $this->filterArgStart + 1
            );
            $args = (new self())->parse($argString)->expressions();
        }

        $this->filters[] = new FilterCall($filterName, $args);
    }

    /**
     * @param string $exp
     * @param int $pos
     */
    private function finishExpression($exp, $pos) {
        $this->expressions[] = trim(
            substr($exp, $this->expressionStart, $pos - $this->expressionStart)
        );
        $this->expressionStart = $pos + 1;
    }

    private function resetState() {
        $this->filters = [];

        $this->expressions = [];
        $this->expressionStart = 0;

        $this->nowParsingFilterList = false;
        $this->filterArgStart = null;
        $this->filterArgEnd = null;
    }

}
