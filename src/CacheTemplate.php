<?php

namespace VuePre;

use DOMNode;

class CacheTemplate {

    public $knownComponentNames = [];
    public $nodes = [];
    public $engine = null;

    public function __construct(Engine $engine) {
        $this->engine = $engine;
    }

    public function render($data): String {
        $html = '';

        foreach ($this->nodes as $node) {
            $html .= $this->renderNode($node, $data);
        }

        return $html;
    }

    public function addDomNode(DOMNode $node) {
        $cacheNode = new Node($this);
        $cacheNode->parseDomNode($node);
        $this->nodes[] = $cacheNode->export();
    }

    public function export() {
        $result = (object) [
            'nodes' => $this->nodes,
        ];

        return $result;
    }

    public function import($exportData) {

        if (isset($exportData->nodes)) {
            $this->nodes = $exportData->nodes;
        }

        return $this;
    }

    public function renderNode($node, $data): String {
        $html = isset($node->content) ? $node->content : '';

        // VFOR
        if (isset($node->vfor)) {
            $html = '';
            $items = static::eval($node->vfor, $data);
            foreach ($items as $k => $v) {
                if (isset($node->vforIndexName)) {$data[$node->vforIndexName] = $k;}
                if (isset($node->vforAsName)) {$data[$node->vforAsName] = $v;}
                $node->vfor = null;
                $html .= $this->renderNode($node, $data);
            }
            return $html;
        }

        // VIF
        if (isset($node->vif)) {
            $node->vifResult = static::eval($node->vif, $data);
            if (!$node->vifResult) {return '';}
        }
        if (isset($node->velseif) && (!isset($node->vifResult) || !$node->vifResult)) {
            $node->vifResult = static::eval($node->velseif, $data);
            if (!$node->vifResult) {return '';}
        }
        if (isset($node->velse) && (!isset($node->vifResult) || $node->vifResult)) {
            $node->vifResult = null;
            return '';
        }

        // VSLOT
        if (isset($node->vslot)) {
            $slotHtml = $this->engine->getSlotHtml($node->vslot);
            return $slotHtml;
        }

        // VHTML
        if (isset($node->vhtml)) {
            $html = str_replace('_VUEPRE_HTML_PLACEHOLDER_', static::eval($node->vhtml, $data), $html);
        }

        // Components
        if (isset($node->isComponent)) {
            $options = [];
            // Render slots
            $slotHtml = [];
            if (isset($node->slotNodes)) {
                foreach ($node->slotNodes as $slotName => $nodes) {
                    if (!isset($slotHtml[$slotName])) {
                        $slotHtml[$slotName] = '';
                    }

                    foreach ($nodes as $slotNode) {
                        $slotHtml[$slotName] .= $this->renderNode($slotNode, $data);
                    }
                }
            }
            $options['slots'] = $slotHtml;
            // Render component
            $newData = [];
            if (isset($node->bindedValues)) {
                foreach ($node->bindedValues as $k => $expr) {
                    $newData[$k] = static::eval($expr, $data);
                }
            }
            return $this->engine->renderComponent(static::eval($node->isComponent, $data), $newData, $options);
        }

        // {{ }}
        if (isset($node->mustacheValues)) {
            foreach ($node->mustacheValues as $k => $v) {
                $html = str_replace($k, static::eval($v, $data), $html);
            }
        }

        // SUBNODES
        if ($node->nodeType === 1) {
            $subHtml = '';
            $vifResult = null;
            if (isset($node->childNodes)) {
                foreach ($node->childNodes as $cnode) {
                    $cnode->vifResult = $vifResult;
                    $subHtml .= $this->renderNode($cnode, $data);
                    $vifResult = $cnode->vifResult ?? null;
                }
            }
            // <template>
            if (isset($node->isTemplate)) {
                return $subHtml;
            }
            $html = str_replace('_VUEPRE_HTML_PLACEHOLDER_', $subHtml, $html);
        }

        return $html;
    }

    public static function eval($expr, $reallyUnrealisticVariableNameForVuePre) {

        foreach ($reallyUnrealisticVariableNameForVuePre as $k => $v) {
            if ($k === 'this') {
                throw new Exception('Variable "this" is not allowed');
            }
            ${$k} = $v;
        }

        try {
            $result = eval('return ' . $expr . ';');
        } catch (\Exception $e) {
            throw new \Exception('Could not execute: ' . $expr);
        }

        return $result;
    }

}