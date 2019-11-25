<?php

namespace VuePre;

use DOMElement;
use DOMNode;

class Node {

    public $settings = null;
    public $template = null;

    private $errorLineNr = '?';

    public function __construct(CacheTemplate $template = null) {
        $this->template = $template;

        $this->settings = (object) [
            'line' => null,
            'nodeType' => null,
            'content' => null,
            //
            'childNodes' => [],
            //
            'isTemplate' => false,
            'isRootEl' => false,
            'isComponent' => null,
            //
            'vfor' => null,
            'vforIndexName' => null,
            'vforAsName' => null,
            'vif' => null,
            'velseif' => null,
            'velse' => null,
            'vhtml' => null,
            //
            'class' => null,
            'style' => null,
            'mustacheValues' => [],
            'bindedValues' => [],
            // Slots
            'vslot' => null,
            'slotNodes' => (object) [],
        ];
    }

    public function parseDomNode(DOMNode $node, $options = []) {

        try {

            $lineNr = $node->getLineNo() - 1;
            $this->errorLineNr = $lineNr;

            $this->settings->line = $lineNr;
            $this->settings->nodeType = $node->nodeType;
            $this->settings->content = $node->nodeType === 3 ? $node->textContent : null;

            $this->replaceMustacheVariables($node);
            if ($node->nodeType === 1) {

                $this->stripEventHandlers($node);
                $this->handleFor($node, $options);
                $this->handleIf($node, $options);
                $this->handleTemplateTag($node, $options);
                $this->handleSlot($node, $options);
                $this->handleAttributeBinding($node);
                $this->handleComponent($node, $options);
                $this->handleRawHtml($node);

                $subNodes = iterator_to_array($node->childNodes);
                $this->settings->childNodes = [];
                foreach ($subNodes as $index => $childNode) {
                    if (!$this->settings->isComponent) {
                        $newNode = new Node($this->template);
                        $this->settings->childNodes[] = $newNode->parseDomNode($childNode);
                    }
                    $node->removeChild($childNode);
                }

                $newNode = $node->ownerDocument->createTextNode('_VUEPRE_HTML_PLACEHOLDER_');
                $node->appendChild($newNode);
                $this->settings->content = $node->ownerDocument->saveHTML($node);
            }

        } catch (\Exception $e) {
            if ($e->getCode() === 100) {
                $this->parseError($e);
            } else {
                throw $e;
            }
        }

        return $this;
    }

    public function parseError($e) {
        $template = $this->template->engine->errorCurrentTemplate;
        $lineNr = $this->errorLineNr;
        $error = $e->getMessage();
        CacheTemplate::templateError($template, $lineNr, $error);
    }

    public function export(): \stdClass{
        $result = (object) [];
        $settings = $this->settings;

        if ($settings->isTemplate) {
            $result->isTemplate = true;
        }

        $copyIfNotNull = ['line', 'nodeType', 'content', 'isComponent', 'vfor', 'vforIndexName', 'vforAsName', 'vif', 'velseif', 'velse', 'vhtml', 'class', 'style', 'vslot'];
        foreach ($copyIfNotNull as $k) {
            if ($settings->$k !== null) {
                $result->$k = $settings->$k;
            }
        }

        $copyIfHasItems = ['mustacheValues', 'bindedValues'];
        foreach ($copyIfHasItems as $k) {
            if (count($settings->$k) > 0) {
                $result->$k = $settings->$k;
            }
        }

        if (count($settings->childNodes) > 0) {
            $result->childNodes = [];
            foreach ($settings->childNodes as $k => $v) {
                $result->childNodes[$k] = $v->export();
            }
        }

        if (count((array) $settings->slotNodes) > 0) {
            $result->slotNodes = (object) [];
            foreach ($settings->slotNodes as $k => $v) {
                foreach ($settings->slotNodes->$k as $kk => $vv) {
                    $result->slotNodes->$k[$kk] = $vv->export();
                }
            }
        }

        return $result;
    }

    private function stripEventHandlers(DOMNode $node) {
        foreach ($node->attributes as $attribute) {
            if (strpos($attribute->name, 'v-on:') === 0) {
                $node->removeAttribute($attribute->name);
            }
        }
    }

    private function replaceMustacheVariables(DOMNode $node) {
        if ($node->nodeType === 3) {
            // $text = $node->nodeValue;
            $text = $this->settings->content;
            $count = 0;
            $this->settings->mustacheValues = [];
            $regex = '/\{\{(?P<expression>.*?)\}\}/x';
            preg_match_all($regex, $text, $matches);
            foreach ($matches['expression'] as $index => $expression) {
                $phpExpr = ConvertJsExpression::convert($expression);
                $tag = '_VUEPRE_MUSHTAG' . $count . '_';
                $text = str_replace($matches[0][$index], $tag, $text);
                $this->settings->mustacheValues[$tag] = $phpExpr;
                $count++;
            }
            $this->settings->content = $text;
        }
    }

    private function handleAttributeBinding(DOMElement $node) {
        $removeAttrs = [];
        foreach (iterator_to_array($node->attributes) as $attribute) {

            if (!preg_match('/^(?:v-bind)?:([\w-]+)$/', $attribute->name, $matches)) {
                continue;
            }

            // Remove attribute
            $node->removeAttribute($attribute->name);

            // Handle attribute
            $name = $matches[1];

            if ($name === 'class') {
                $currentClass = $node->getAttribute('class');
                $node->setAttribute($name, '_VUEPRE_CLASS_');
                $phpExpr = ConvertJsExpression::convert($attribute->value, ['inAttribute' => 'class']);
                $this->settings->class = "'" . $currentClass . " ' . (" . $phpExpr . ")";
                continue;
            }

            if ($name === 'style') {
                $currentStyle = $node->getAttribute('style');
                $node->setAttribute($name, '_VUEPRE_STYLE_');
                $phpExpr = ConvertJsExpression::convert($attribute->value, ['inAttribute' => 'style']);
                $this->settings->style = "'" . addslashes($currentStyle) . " ' . (" . $phpExpr . ")";
                continue;
            }

            if ($node->tagName === 'component' && $name === 'is') {
                $phpExpr = ConvertJsExpression::convert($attribute->value);
                $this->settings->isComponent = $phpExpr;
                continue;
            }

            // Add to bindings
            $phpExpr = ConvertJsExpression::convert($attribute->value);
            $this->settings->bindedValues[$name] = $phpExpr;
            $node->setAttribute($name, '_VUEPRE_ATR_' . $name . '_ATREND_');
        }

        // Check for class/style
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = $attribute->name;
            if ($name === 'class' && !$this->settings->class) {
                $this->settings->class = "'" . ($attribute->value) . "'";
                $node->setAttribute($name, '_VUEPRE_CLASS_');
            }
            if ($name === 'style' && !$this->settings->style) {
                $this->settings->style = "'" . addslashes($attribute->value) . "'";
                $node->setAttribute($name, '_VUEPRE_STYLE_');
            }
        }

        if ($this->settings->isRootEl) {
            if (!$node->hasAttribute('class')) {
                $this->settings->class = '';
                $node->setAttribute('class', '_VUEPRE_CLASS_');
            }
        }
    }

    private function handleTemplateTag(DOMNode $node, $options) {
        $tagName = $node->tagName;
        if ($tagName !== 'template') {
            return;
        }

        $this->settings->isTemplate = true;
    }

    private function handleSlot(DOMNode $node, $options) {
        $tagName = $node->tagName;
        if ($tagName !== 'slot') {
            return;
        }

        $slotName = '_DEFAULTSLOT_';
        if ($node->hasAttribute('name')) {
            $slotName = $node->getAttribute('name');
        }

        $this->settings->vslot = $slotName;
    }

    private function handleComponent(DOMNode $node) {

        $componentName = $node->tagName;

        if (!$this->settings->isComponent && !in_array($componentName, $this->template->knownComponentNames, true)) {
            return;
        }
        if (!$this->settings->isComponent) {
            $componentNameExpr = '\'' . $componentName . '\'';
            $this->settings->isComponent = $componentNameExpr;
        }

        $slotNodes = (object) [];
        $subNodes = iterator_to_array($node->childNodes);
        foreach ($subNodes as $index => $childNode) {
            $slotName = '_DEFAULTSLOT_';

            if ($childNode->nodeType === 1) {
                foreach ($childNode->attributes as $attribute) {
                    if (strpos($attribute->name, 'v-slot:') === 0) {
                        $slotName = substr($attribute->name, strlen('v-slot:'));
                    }
                }
            }

            $slotNode = new Node($this->template);
            $slotNode->parseDomNode($childNode);

            if (!isset($slotNodes->$slotName)) {
                $slotNodes->$slotName = [];
            }
            $slotNodes->$slotName[] = $slotNode;
        }

        $this->settings->slotNodes = $slotNodes;
    }

    private function handleIf(DOMNode $node, array $options) {
        if ($node->hasAttribute('v-if')) {
            $conditionString = $node->getAttribute('v-if');
            $node->removeAttribute('v-if');
            $phpExpr = ConvertJsExpression::convert($conditionString);
            $this->settings->vif = '\VuePre\ConvertJsExpression::toBool(' . $phpExpr . ')';
        } elseif ($node->hasAttribute('v-else-if')) {
            $conditionString = $node->getAttribute('v-else-if');
            $node->removeAttribute('v-else-if');
            $phpExpr = ConvertJsExpression::convert($conditionString);
            $this->settings->velseif = '\VuePre\ConvertJsExpression::toBool(' . $phpExpr . ')';
        } elseif ($node->hasAttribute('v-else')) {
            $node->removeAttribute('v-else');
            $this->settings->velse = true;
        }
    }
    private function handleFor(DOMNode $node, array $options) {
        /** @var DOMElement $node */
        if ($node->hasAttribute('v-for')) {
            list($itemName, $listName) = explode(' in ', $node->getAttribute('v-for'));
            $node->removeAttribute('v-for');

            // Support for item,index in myArray
            $itemName = trim($itemName, '() ');
            $itemIndex = null;
            $itemNameEx = explode(',', $itemName);
            if (count($itemNameEx) === 2) {
                $itemName = trim($itemNameEx[0]);
                $itemIndex = trim($itemNameEx[1]);
            }

            $phpExpr = ConvertJsExpression::convert($listName);
            $this->settings->vfor = $phpExpr;
            $this->settings->vforAsName = $itemName;
            $this->settings->vforIndexName = $itemIndex;
        }
    }

    private function handleRawHtml(DOMNode $node) {
        /** @var DOMElement $node */
        if ($node->hasAttribute('v-html')) {
            $expr = $node->getAttribute('v-html');
            $node->removeAttribute('v-html');
            $phpExpr = ConvertJsExpression::convert($expr);
            $this->settings->vhtml = $phpExpr;
        }
    }

}
