<?php

namespace LorenzV\VuePre;

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

class VuePre {

    private $componentDir = null;
    private $cacheDir = null;
    private $componentAlias = [];
    public $devMode = false;

    const PHPOPEN = '__VUEPREPHPTAG__';
    const PHPEND = '__VUEPREPHPENDTAG__';

    public function __construct() {
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
            $html = $this->createCachedTemplate($html);
            file_put_contents($cacheFile, $html);
        }

        // Render cached template
        $html = $this->renderCachedTemplate($cacheFile, $data);
        return $html;
    }

    private function createCachedTemplate($html) {
        $dom = $this->parseHtml($html);

        $rootNode = $this->getRootNode($dom);
        $this->handleNode($rootNode);

        $html = $dom->saveHTML($rootNode);

        // Replace php tags
        $html = str_replace(static::PHPOPEN, '<?php', $html);
        $html = str_replace(static::PHPEND, '?>', $html);

        return $html;
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

    private function handleNode(DOMNode $node, array $options = []) {
        if (count($options) === 0) {
            $options = [
                'nodeDepth' => 0,
                'nextSibling' => null,
            ];
        }
        $this->replaceMustacheVariables($node);
        if (!$this->isTextNode($node)) {
            $this->stripEventHandlers($node);
            $this->handleFor($node, $options);
            $this->handleRawHtml($node);
            // if (!$this->isRemovedFromTheDom($node)) {
            $this->handleAttributeBinding($node);
            $this->handleIf($node, $options);
            $subOptions = $options;
            $subOptions['nodeDepth'] += 1;
            $subNodes = iterator_to_array($node->childNodes);
            foreach ($subNodes as $index => $childNode) {
                $subOptions['nextSibling'] = isset($subNodes[$index + 1]) ? $subNodes[$index + 1] : null;
                $this->handleNode($childNode, $subOptions);
            }
            // }
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
    private function replaceMustacheVariables(DOMNode $node) {
        if ($node instanceof DOMText) {
            $text = $node->nodeValue;
            $regex = '/\{\{(?P<expression>.*?)\}\}/x';
            preg_match_all($regex, $text, $matches);
            foreach ($matches['expression'] as $index => $expression) {
                $phpExpr = ConvertJsExpression::convert($expression);
                $text = str_replace($matches[0][$index], static::PHPOPEN . ' echo htmlspecialchars(' . $phpExpr . '); ' . static::PHPEND, $text);
            }
            if ($text !== $node->nodeValue) {
                $newNode = $node->ownerDocument->createTextNode($text);
                $node->parentNode->replaceChild($newNode, $node);
            }
        }
    }
    private function handleAttributeBinding(DOMElement $node) {
        /** @var DOMAttr $attribute */
        foreach (iterator_to_array($node->attributes) as $attribute) {
            if (!preg_match('/^:[\w-]+$/', $attribute->name)) {
                continue;
            }
            $phpExpr = ConvertJsExpression::convert($attribute->value);
            $name = substr($attribute->name, 1);
            $node->setAttribute($name, static::PHPOPEN . ' echo (' . $phpExpr . '); ' . static::PHPEND);
            $node->removeAttribute($attribute->name);
        }
    }
    /**
     * @param DOMNodeList $nodes
     * @param array $data
     */
    private function handleIf(DOMNode $node, array $options) {
        if ($this->isTextNode($node)) {
            return;
        }
        if ($node->hasAttribute('v-if')) {
            $conditionString = $node->getAttribute('v-if');
            $node->removeAttribute('v-if');
            $phpExpr = ConvertJsExpression::convert($conditionString);
            // Add php code
            $beforeNode = $node->ownerDocument->createTextNode(static::PHPOPEN . ' if($_HANDLEIFRESULT' . ($options["nodeDepth"]) . ' = ' . $phpExpr . ') { ' . static::PHPEND);
            $afterNode = $node->ownerDocument->createTextNode(static::PHPOPEN . ' } ' . static::PHPEND);
            $node->parentNode->insertBefore($beforeNode, $node);
            if ($options['nextSibling']) {$node->parentNode->insertBefore($afterNode, $options['nextSibling']);} else { $node->parentNode->appendChild($afterNode);}
        } elseif ($node->hasAttribute('v-else-if')) {
            $conditionString = $node->getAttribute('v-else-if');
            $node->removeAttribute('v-else-if');
            $phpExpr = ConvertJsExpression::convert($conditionString);
            // Add php code
            $beforeNode = $node->ownerDocument->createTextNode(static::PHPOPEN . ' if(!$_HANDLEIFRESULT' . ($options["nodeDepth"]) . ' && $_HANDLEIFRESULT' . ($options["nodeDepth"]) . ' = ' . $phpExpr . ') { ' . static::PHPEND);
            $afterNode = $node->ownerDocument->createTextNode(static::PHPOPEN . ' } ' . static::PHPEND);
            $node->parentNode->insertBefore($beforeNode, $node);
            if ($options['nextSibling']) {$node->parentNode->insertBefore($afterNode, $options['nextSibling']);} else { $node->parentNode->appendChild($afterNode);}
        } elseif ($node->hasAttribute('v-else')) {
            $node->removeAttribute('v-else');
            // Add php code
            $beforeNode = $node->ownerDocument->createTextNode(static::PHPOPEN . ' if(!$_HANDLEIFRESULT' . ($options["nodeDepth"]) . ') { ' . static::PHPEND);
            $afterNode = $node->ownerDocument->createTextNode(static::PHPOPEN . ' } ' . static::PHPEND);
            $node->parentNode->insertBefore($beforeNode, $node);
            if ($options['nextSibling']) {$node->parentNode->insertBefore($afterNode, $options['nextSibling']);} else { $node->parentNode->appendChild($afterNode);}
        }
    }
    private function handleFor(DOMNode $node, array $options) {
        if ($this->isTextNode($node)) {
            return;
        }
        /** @var DOMElement $node */
        if ($node->hasAttribute('v-for')) {
            list($itemName, $listName) = explode(' in ', $node->getAttribute('v-for'));
            $node->removeAttribute('v-for');

            $phpExpr = ConvertJsExpression::convert($listName);

            $beforeNode = $node->ownerDocument->createTextNode(static::PHPOPEN . ' foreach((' . $phpExpr . ') as $' . $itemName . ') { ' . static::PHPEND);
            $afterNode = $node->ownerDocument->createTextNode(static::PHPOPEN . ' } ' . static::PHPEND);
            $node->parentNode->insertBefore($beforeNode, $node);
            if ($options['nextSibling']) {$node->parentNode->insertBefore($afterNode, $options['nextSibling']);} else { $node->parentNode->appendChild($afterNode);}
        }
    }
    private function appendHTML(DOMNode $parent, $source) {
        $tmpDoc = $this->parseHtml($source);
        foreach ($tmpDoc->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $node = $parent->ownerDocument->importNode($node, true);
            $parent->appendChild($node);
        }
    }
    private function handleRawHtml(DOMNode $node) {
        if ($this->isTextNode($node)) {
            return;
        }
        /** @var DOMElement $node */
        if ($node->hasAttribute('v-html')) {
            $expr = $node->getAttribute('v-html');
            $node->removeAttribute('v-html');
            $phpExpr = ConvertJsExpression::convert($expr);
            $text = static::PHPOPEN . ' echo (' . $phpExpr . '); ' . static::PHPEND;
            $node->textContent = $text;
        }
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
