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

    public function render($data, $options = []): String {
        $html = '';

        $firstEl = true;
        foreach ($this->nodes as $k => $node) {
            if ($firstEl && $node->nodeType === 1) {
                // First element
                $firstEl = false;
                // Merge class/style if in options
                if (isset($options['class'])) {
                    $node->class = "'" . ($options['class']) . " ' . " . (isset($node->class) ? $node->class : "''");
                }
                if (isset($options['style'])) {
                    $node->style = "'" . ($options['style']) . " ' . " . (isset($node->style) ? $node->style : "''");
                }
            }
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

        $this->errorLineNr = $node->line;

        $html = isset($node->content) ? $node->content : '';

        // VFOR
        if (isset($node->vfor)) {
            $html = '';
            $items = $this->eval($node->vfor, $data);
            $nodeCopy = json_decode(json_encode($node)); // Deep clone
            $nodeCopy->vfor = null;
            foreach ($items as $k => $v) {
                if (isset($node->vforIndexName)) {$data[$node->vforIndexName] = $k;}
                if (isset($node->vforAsName)) {$data[$node->vforAsName] = $v;}
                $html .= $this->renderNode($nodeCopy, $data);
            }
            return $html;
        }

        // VIF
        if (isset($node->vif)) {
            $node->vifResult = $this->eval($node->vif, $data);
            if (!$node->vifResult) {return '';}
        }
        if (isset($node->velseif) && (!isset($node->vifResult) || !$node->vifResult)) {
            $node->vifResult = $this->eval($node->velseif, $data);
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

        // CLASS
        if (isset($node->class)) {
            $html = str_replace('_VUEPRE_CLASS_', $this->eval($node->class, $data), $html);
        }

        // STYLE
        if (isset($node->style)) {
            $html = str_replace('_VUEPRE_STYLE_', $this->eval($node->style, $data), $html);
        }

        // VHTML
        if (isset($node->vhtml)) {
            $html = str_replace('_VUEPRE_HTML_PLACEHOLDER_', $this->eval($node->vhtml, $data), $html);
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
                    $newData[$k] = $this->eval($expr, $data);
                }
            }

            if (isset($node->class)) {
                $options['class'] = $this->eval($node->class, $data);
            }
            if (isset($node->style)) {
                $options['style'] = $this->eval($node->style, $data);
            }

            $componentName = $this->eval($node->isComponent, $data);
            return $this->engine->renderComponent($componentName, $newData, $options);
        }

        if (isset($node->bindedValues)) {
            foreach ($node->bindedValues as $k => $expr) {
                $replace = '';
                if (in_array($k, ['href'], true)) {
                    $replace = $this->eval($expr, $data);
                }
                $html = str_replace('_VUEPRE_ATR_' . $k . '_ATREND_', $replace, $html);
            }
        }

        // {{ }}
        if (isset($node->mustacheValues)) {
            foreach ($node->mustacheValues as $k => $v) {
                $html = str_replace($k, $this->eval($v, $data), $html);
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

    //////////////////
    // Errors
    //////////////////

    public $errorExpression = '';
    public $errorLineNr = '?';

    public function eval($expr, $reallyUnrealisticVariableNameForVuePre) {

        foreach ($reallyUnrealisticVariableNameForVuePre as $k => $v) {
            if ($k === 'this') {
                throw new Exception('Variable "this" is not allowed');
            }
            ${$k} = $v;
        }

        set_error_handler(array($this, 'evalError'));
        $this->errorExpression = $expr;
        try {
            $result = eval('return ' . $expr . ';');
        } catch (\Throwable $t) {
            dump('------------');
            dump($expr);
            dd($t->getMessage());
        }
        restore_error_handler();

        return $result;
    }

    public function evalError($errno, $errstr, $errFile, $errLine) {
        $template = $this->engine->errorCurrentTemplate;
        $lineNr = $this->errorLineNr;
        $error = 'Cant parse "' . htmlspecialchars($this->errorExpression) . '" : ' . $errstr;
        static::templateError($template, $lineNr, $error);
    }

    public static function templateError($template, $lineNr, $error) {
        echo '<pre><code>';
        echo 'Error: ' . $error . "\n";
        echo 'Line:' . $lineNr . "\n";
        echo 'Template:' . "\n";

        if (is_int($lineNr)) {
            $lines = explode("\n", $template);
            $before = array_slice($lines, 0, $lineNr);
            $line = array_splice($lines, $lineNr, 1)[0];
            $after = array_slice($lines, $lineNr);
            echo htmlspecialchars(implode("\n", $before)) . "\n";
            echo '<span style="color: red;">' . htmlspecialchars($line) . '</span>' . "\n";
            echo htmlspecialchars(implode("\n", $after));
        } else {
            echo htmlspecialchars($template);
        }

        echo '</code></pre>';
        exit;
    }

}