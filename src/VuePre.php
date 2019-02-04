<?php

namespace LorenzV\PhpVueTemplatePrerender;

use DOMAttr;
use DOMCharacterData;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMText;
use Exception;
use LibXMLError;
//
use LorenzV\PhpVueTemplatePrerender\FilterExpressionParsing\FilterParser;
use LorenzV\PhpVueTemplatePrerender\JsParsing\BasicJsExpressionParser;
use LorenzV\PhpVueTemplatePrerender\JsParsing\CachingExpressionParser;

class VuePre {

    private $componentDir = null;
    private $cacheDir = null;
    private $componentAlias = [];
    public $devMode = false;

    private $filterParser;
    private $filters = [];

    public function __construct() {
        $this->expressionParser = new CachingExpressionParser(new BasicJsExpressionParser());
        $this->filterParser = new FilterParser();
    }

    public function setCacheDirectory(String $dir) {
        $dir = realpath($dir);
        if (!file_exists($dir) || !is_dir($dir)) {
            throw new Exception('Component directory not found: ' . $dir);
        }
        $this->cacheDir = $dir;
    }

    public function setComponentDirectory(String $dir) {
        $dir = realpath($dir);
        if (!file_exists($dir) || !is_dir($dir)) {
            throw new Exception('Component directory not found: ' . $dir);
        }
        $this->componentDir = $dir;
    }

    public function setComponentAlias($aliasses) {
        foreach ($aliasses as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $this->componentAlias[$k] = $v;
            }
        }
    }

    public function renderComponent($path, $data = []) {

        if (!$this->componentDir) {
            throw new Exception('Trying to find component, but componentDirectory was not set');
        }

        if (!$this->cacheDir) {
            throw new Exception('Cache directory was not set');
        }

        $fullPath = $this->componentDir . '/' . implode('/', explode('.', $path)) . '.html';
        if (!file_exists($fullPath)) {
            throw new Exception('Component template not found: ' . $fullPath);
        }

        // Cache
        // $hash = hash_file('md5', $fullPath);
        $hash = md5($fullPath . filesize($fullPath));
        $cacheFile = $this->cacheDir . '/' . $hash . '.php';

        // Create cache template
        if (!file_exists($cacheFile) || $this->devMode) {
            $html = file_get_contents($fullPath);
            $html = $this->createCachedTemplate($html, $data);
            file_put_contents($cacheFile, $html);
        }

        // Render cached template
        $html = $this->renderCachedTemplate($cacheFile, $data);
        return $html;
    }

    private function createCachedTemplate($html, $data) {
        $dom = $this->parseHtml($html);

        $rootNode = $this->getRootNode($dom);
        $this->handleNode($rootNode, $data);

        return $dom->saveHTML($rootNode);
    }

    // reallyUnrealisticVariableNameForVuePre is the variable that holds the template data
    private function renderCachedTemplate($file, $reallyUnrealisticVariableNameForVuePre) {

        foreach ($reallyUnrealisticVariableNameForVuePre as $k => $v) {
            if ($k === 'this') {
                throw new Exception('Variable "this" is not allowed');
            }
            ${$k} = $v;
        }

        ob_start();
        include $file;
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private function parseHtml($html) {
        $entityLoaderDisabled = libxml_disable_entity_loader(true);
        $internalErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        // Ensure $html is treated as UTF-8, see https://stackoverflow.com/a/8218649
        if (!$document->loadHTML('<?xml encoding="utf-8" ?>' . $html)) {
            //TODO Test failure
        }
        /** @var LibXMLError[] $errors */
        $errors = libxml_get_errors();
        libxml_clear_errors();
        // Restore previous state
        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($entityLoaderDisabled);
        foreach ($errors as $error) {
            //TODO html5 tags can fail parsing
            //TODO Throw an exception
        }
        return $document;
    }

    private function getRootNode(DOMDocument $document) {
        $rootNodes = $document->documentElement->childNodes->item(0)->childNodes;
        if ($rootNodes->length > 1) {
            throw new Exception('Template should have only one root node');
        }
        return $rootNodes->item(0);
    }

    private function handleNode(DOMNode $node, array $data) {
        $this->replaceMustacheVariables($node, $data);
        if (!$this->isTextNode($node)) {
            $this->stripEventHandlers($node);
            $this->handleFor($node, $data);
            $this->handleRawHtml($node, $data);
            if (!$this->isRemovedFromTheDom($node)) {
                $this->handleAttributeBinding($node, $data);
                $this->handleIf($node->childNodes, $data);
                foreach (iterator_to_array($node->childNodes) as $childNode) {
                    $this->handleNode($childNode, $data);
                }
            }
        }
    }

    private function stripEventHandlers(DOMNode $node) {
        if ($this->isTextNode($node)) {
            return;
        }
        /** @var DOMAttr $attribute */
        foreach ($node->attributes as $attribute) {
            if (strpos($attribute->name, 'v-on:') === 0) {
                $node->removeAttribute($attribute->name);
            }
        }
    }

    /**
     * @param DOMNode $node
     * @param array $data
     */
    private function replaceMustacheVariables(DOMNode $node, array $data) {
        if ($node instanceof DOMText) {
            $text = $node->wholeText;
            $regex = '/\{\{(?P<expression>.*?)\}\}/x';
            preg_match_all($regex, $text, $matches);
            foreach ($matches['expression'] as $index => $expression) {
                $value = $this->filterParser->parse($expression)
                    ->toExpression($this->expressionParser, $this->filters)
                    ->evaluate($data);
                $text = str_replace($matches[0][$index], $value, $text);
            }
            if ($text !== $node->wholeText) {
                $newNode = $node->ownerDocument->createTextNode($text);
                $node->parentNode->replaceChild($newNode, $node);
            }
        }
    }
    private function handleAttributeBinding(DOMElement $node, array $data) {
        /** @var DOMAttr $attribute */
        foreach (iterator_to_array($node->attributes) as $attribute) {
            if (!preg_match('/^:[\w-]+$/', $attribute->name)) {
                continue;
            }
            $value = $this->filterParser->parse($attribute->value)
                ->toExpression($this->expressionParser, $this->filters)
                ->evaluate($data);
            $name = substr($attribute->name, 1);
            if (is_bool($value)) {
                if ($value) {
                    $node->setAttribute($name, $name);
                }
            } else {
                $node->setAttribute($name, $value);
            }
            $node->removeAttribute($attribute->name);
        }
    }
    /**
     * @param DOMNodeList $nodes
     * @param array $data
     */
    private function handleIf(DOMNodeList $nodes, array $data) {
        // Iteration of iterator breaks if we try to remove items while iterating, so defer node
        // removing until finished iterating.
        $nodesToRemove = [];
        foreach ($nodes as $node) {
            if ($this->isTextNode($node)) {
                continue;
            }
            /** @var DOMElement $node */
            if ($node->hasAttribute('v-if')) {
                $conditionString = $node->getAttribute('v-if');
                $node->removeAttribute('v-if');
                $condition = $this->evaluateExpression($conditionString, $data);
                if (!$condition) {
                    $nodesToRemove[] = $node;
                }
                $previousIfCondition = $condition;
            } elseif ($node->hasAttribute('v-else')) {
                $node->removeAttribute('v-else');
                if ($previousIfCondition) {
                    $nodesToRemove[] = $node;
                }
            }
        }
        foreach ($nodesToRemove as $node) {
            $this->removeNode($node);
        }
    }
    private function handleFor(DOMNode $node, array $data) {
        if ($this->isTextNode($node)) {
            return;
        }
        /** @var DOMElement $node */
        if ($node->hasAttribute('v-for')) {
            list($itemName, $listName) = explode(' in ', $node->getAttribute('v-for'));
            $node->removeAttribute('v-for');
            foreach ($data[$listName] as $item) {
                $newNode = $node->cloneNode(true);
                $node->parentNode->insertBefore($newNode, $node);
                $this->handleNode($newNode, array_merge($data, [$itemName => $item]));
            }
            $this->removeNode($node);
        }
    }
    private function appendHTML(DOMNode $parent, $source) {
        $tmpDoc = $this->parseHtml($source);
        foreach ($tmpDoc->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $node = $parent->ownerDocument->importNode($node, true);
            $parent->appendChild($node);
        }
    }
    private function handleRawHtml(DOMNode $node, array $data) {
        if ($this->isTextNode($node)) {
            return;
        }
        /** @var DOMElement $node */
        if ($node->hasAttribute('v-html')) {
            $variableName = $node->getAttribute('v-html');
            $node->removeAttribute('v-html');
            $newNode = $node->cloneNode(true);
            $this->appendHTML($newNode, $data[$variableName]);
            $node->parentNode->replaceChild($newNode, $node);
        }
    }
    /**
     * @param string $expression
     * @param array $data
     *
     * @return bool
     */
    private function evaluateExpression($expression, array $data) {
        return $this->expressionParser->parse($expression)->evaluate($data);
    }
    private function removeNode(DOMElement $node) {
        $node->parentNode->removeChild($node);
    }
    /**
     * @param DOMNode $node
     *
     * @return bool
     */
    private function isTextNode(DOMNode $node) {
        return $node instanceof DOMCharacterData;
    }
    private function isRemovedFromTheDom(DOMNode $node) {
        return $node->parentNode === null;
    }

}
