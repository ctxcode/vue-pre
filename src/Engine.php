<?php

namespace VuePre;

use DOMDocument;
use Exception;
use LibXMLError;

//

class Engine {

    private $componentDir = null;
    private $cacheDir = null;

    public $componentAlias = [];
    private $components = [];
    private $methods = [];
    private $renderedComponentNames = [];
    private $componentBeforeRender = [];
    private $settingsLoaded = [];
    private $componentTemplates = [];

    public $disableCache = false;
    public $disableAutoScan = false;

    public $errorCurrentTemplate = null;

    private $slotHtml = [];
    private $globalData = [];

    private static $fileCache = [];

    public function __construct() {
    }

    /////////////////////////
    // Settings
    /////////////////////////

    public function setCacheDirectory(String $dir) {
        $dir = realpath($dir);
        if (!file_exists($dir) || !is_dir($dir)) {
            throw new Exception('Cache directory not found: ' . $dir);
        }
        $this->cacheDir = $dir;
    }

    public function setComponentDirectory(String $dir) {
        $dir = realpath($dir);
        if (!file_exists($dir) || !is_dir($dir)) {
            throw new Exception('Component directory not found: ' . $dir);
        }
        $this->componentDir = $dir;
        if (!$this->disableAutoScan) {
            $this->scanComponentDirectoryForComponents();
        }
    }

    /////////////////////////
    // Getters / Setters
    /////////////////////////

    public function getRenderedComponentNames() {
        return array_values($this->renderedComponentNames);
    }

    public function setGlobals(Array $data) {
        $this->globalData = array_merge($this->globalData, $data);
    }

    public function getGlobals() {
        return $this->globalData;
    }

    /////////////////////////
    // Component finding
    /////////////////////////

    public function setComponentAlias(array $aliasses) {
        foreach ($aliasses as $componentName => $alias) {
            if (is_string($componentName) && is_string($alias)) {
                $this->componentAlias[$componentName] = $alias;
            }
        }
    }

    public function getComponentAlias($componentName, $default = null) {
        if (isset($this->componentAlias[$componentName])) {
            return $this->componentAlias[$componentName];
        }
        if ($default) {
            return $default;
        }
        throw new \Exception('Cannot find alias for "' . $componentName . '"');
    }

    /////////////////////////
    // Helper functions
    /////////////////////////

    public function loadComponent($componentName) {
        if (!isset($this->components[$componentName])) {
            $component = [
                'settings' => null,
                'template' => null,
                'js' => null,
            ];

            $alias = $this->getComponentAlias($componentName);
            $dirPath = $this->componentDir . '/' . implode('/', explode('.', $alias));
            $path = $dirPath . '.php';

            if (!file_exists($path)) {
                throw new Exception('Component file not found: ' . $path);
            }

            $content = "\n" . file_get_contents($path);

            $php = static::getStringBetweenTags($content, '\n<\?php\s', '\n\?>');
            $template = static::getStringBetweenTags($content, '\n<template ?[^>]*>', '\n<\/template>');
            $js = static::getStringBetweenTags($content, '\n<script ?[^>]*>', '\n<\/script>');

            $loadSettings = function ($php) {
                $settings = eval($php);
                return $settings;
            };

            $settings = $loadSettings($php);

            if (!isset($this->componentBeforeRender[$componentName]) && isset($settings['beforeRender'])) {
                $this->componentBeforeRender[$componentName] = $settings['beforeRender'];
            }

            $component['settings'] = $settings;
            $component['template'] = $template;
            $component['js'] = $js;

            $this->components[$componentName] = $component;
        }

        return $this->components[$componentName];
    }

    private static function getStringBetweenTags($string, $startReg, $endReg) {
        $found = preg_match("/" . $startReg . "/", $string, $match, PREG_OFFSET_CAPTURE);
        if (!$found) {
            return '';
        }

        $startPos = $match[0][1] + strlen($match[0][0]);
        $result = '';
        $count = 1;
        while (preg_match("/" . $startReg . "|" . $endReg . "/", $string, $match, PREG_OFFSET_CAPTURE, $match[0][1] + 1)) {
            $isStart = preg_match("/" . $startReg . "/", $match[0][0]);

            if ($isStart) {
                $count++;
                continue;
            }

            $count--;
            if ($count === 0) {
                $result = substr($string, $startPos, $match[0][1] - $startPos);
                break;
            }
        }

        if ($count !== 0) {
            throw new \Exception('Cannot find closing tag "' . $endReg . '"');
        }

        return $result;
    }

    public function getComponentTemplate($componentName, $default = null) {
        $comp = $this->loadComponent($componentName);
        return $comp['template'];
    }

    public function getComponentJs($componentName, $default = null) {
        $comp = $this->loadComponent($componentName);
        return $comp['js'];
    }

    /////////////////////////
    // <script> functions
    /////////////////////////

    public function getTemplateScripts($idPrefix = 'vue-template-') {
        $result = '';
        foreach ($this->components as $componentName => $component) {
            $template = $this->getComponentTemplate($componentName);
            $result .= '<script type="text/template" id="' . $idPrefix . $componentName . '">' . ($template) . '</script>';
        }
        return $result;
    }

    public function getJsScripts() {
        $result = '';
        foreach ($this->renderedComponentNames as $componentName => $c) {
            $result .= $this->getJsScript($componentName, '');
        }
        return $result;
    }

    public function getTemplateScript($componentName, $default = null, $idPrefix = 'vue-template-') {
        $template = $this->getComponentTemplate($componentName);
        return '<script type="text/template" id="' . $idPrefix . $componentName . '">' . ($template) . '</script>';
    }

    public function getJsScript($componentName, $default = null) {
        return '<script type="text/javascript">' . $this->getComponentJs($componentName, $default) . '</script>';
    }

    public function getScripts($componentName = null, $idPrefix = 'vue-template-') {
        if ($componentName) {
            $result = '';
            $result .= $this->getTemplateScript($componentName, null, $idPrefix);
            $result .= $this->getJsScript($componentName);
            return $result;
        }
        return $this->getTemplateScripts($idPrefix) . $this->getJsScripts();
    }

    public function getVueInstanceScript($el, $componentName, $data) {

        $jsData = [];
        $bindings = '';
        foreach ($data as $k => $v) {
            if (!is_callable($v)) {
                $jsData[$k] = $v;
                $bindings .= ' :' . $k . '="componentData.' . $k . '"';
            }
        }

        $html = '<script type="text/javascript">
    var VuePreApp = new Vue({
        el: "' . $el . '",
        data: function(){
            return { componentData: ' . json_encode($jsData) . ' };
        },
        template: \'<' . $componentName . $bindings . '></' . $componentName . '>\',
    });
</script>';
        return $html;
    }

    /////////////////////////
    // Scan for aliasses
    /////////////////////////

    private function scanComponentDirectoryForComponents() {
        $this->scanDirectoryForComponents($this->componentDir);
    }

    public function scanDirectoryForComponents($dir) {
        if (!$this->componentDir) {
            throw new Exception('"componentDirectory" not set');
        }
        $dir = realpath($dir);
        if (strpos($dir, $this->componentDir) !== 0) {
            throw new Exception('scanDirectoryForComponents: directory must be a sub directory from "componentDirectory"');
        }
        $files = static::recursiveGlob($dir . '/*.php');
        foreach ($files as $file) {
            $fn = basename($file);
            $alias = substr($file, 0, -strlen('.php'));
            $name = basename($alias);
            $alias = str_replace('/', '.', substr($alias, strlen($this->componentDir . '/')));
            $this->componentAlias[$name] = $alias;
        }
    }

    private static function recursiveGlob($pattern, $flags = 0) {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, static::recursiveGlob($dir . '/' . basename($pattern), $flags));
        }
        return $files;
    }

    /////////////////////////
    // Cache
    /////////////////////////

    private function createCachedTemplate($html, $options) {

        if (!isset($options['isComponent'])) {
            $options['isComponent'] = false;
        }

        if ($options['isComponent']) {
            $dom = $this->parseHtml($html);
            // Check for single rootNode
            $this->getRootNode($dom);
        }

        $dom = $this->parseHtml('<div id="_VuePreRootElement_">' . $html . '</div>');
        $rootNode = $dom->getElementById('_VuePreRootElement_');

        $template = new CacheTemplate($this);
        $template->knownComponentNames = array_keys($this->componentAlias);

        foreach ($rootNode->childNodes as $k => $childNode) {
            $template->addDomNode($childNode);
        }

        return json_encode($template->export());
    }

    private function renderCachedTemplate($file, $data, $options = []) {

        set_error_handler(array($this, 'handleError'));

        if (!isset(static::$fileCache[$file])) {
            static::$fileCache[$file] = file_get_contents($file);
        }
        $exportData = json_decode(static::$fileCache[$file]);

        $template = new CacheTemplate($this);
        $template->import($exportData);
        $html = $template->render($data, $options);

        restore_error_handler();

        return $html;
    }

    public function handleError($errno, $errstr, $errFile, $errLine) {
        return false;
    }

    /////////////////////////
    // Rendering
    /////////////////////////

    public function getSlotHtml($name = '_DEFAULTSLOT_') {
        return isset($this->slotHtml[$name]) ? $this->slotHtml[$name] : '';
    }

    public function renderHtml($template, $data = [], $options = []) {

        if (empty(trim($template))) {
            return '';
        }

        $this->errorCurrentTemplate = $template;

        $hash = md5($template . filemtime(__FILE__) . json_encode($this->componentAlias)); // If package is updated, hash should change
        $cacheFile = $this->cacheDir . '/' . $hash . '.php';

        // Create cache template
        if (!file_exists($cacheFile) || $this->disableCache) {
            $html = $this->createCachedTemplate($template, $options);
            file_put_contents($cacheFile, $html);
        }

        if (isset($options['slots'])) {
            foreach ($options['slots'] as $name => $html) {
                $this->slotHtml[$name] = $html;
            }
        }

        // Render cached template
        return $this->renderCachedTemplate($cacheFile, $data, $options);
    }

    public function renderComponent($componentName, $data = [], $options = []) {

        $data = array_merge($this->globalData, $data);

        if (!$this->componentDir) {
            throw new Exception('Trying to find component, but componentDirectory was not set');
        }

        if (!$this->cacheDir) {
            throw new Exception('Cache directory was not set');
        }

        // Load settings
        $this->loadComponent($componentName);

        // Before mount
        if (isset($this->componentBeforeRender[$componentName])) {
            $this->componentBeforeRender[$componentName]($data);
        }

        // Render template
        if ($componentName === 'div') {
            die();
        }
        $template = $this->getComponentTemplate($componentName);
        $options['isComponent'] = true;
        $html = $this->renderHtml($template, $data, $options);

        // Remember
        if (!isset($this->renderedComponentNames[$componentName])) {
            $this->renderedComponentNames[$componentName] = $componentName;
        }

        //
        return $html;
    }

    private function parseHtml($html) {
        $entityLoaderDisabled = libxml_disable_entity_loader(true);
        $internalErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        // Ensure $html is treated as UTF-8, see https://stackoverflow.com/a/8218649
        if (!$document->loadHTML('<?xml encoding="utf-8" ?>' . $html)) {
            //TODO Test failure
            throw new \Exception('Error');
        }
        /** @var LibXMLError[] $errors */
        $errors = libxml_get_errors();
        libxml_clear_errors();
        // Restore previous state
        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($entityLoaderDisabled);
        foreach ($errors as $error) {
            // var_dump($error);
            // echo '<pre>';
            // echo htmlspecialchars($html);
            // echo '</pre>';
            // exit;
            //TODO html5 tags can fail parsing
            //TODO Throw an exception
        }
        return $document;
    }

    private function getRootNode(DOMDocument $document) {
        $rootNodes = $document->documentElement->childNodes->item(0)->childNodes;
        if ($rootNodes->length > 1) {
            echo '<h2>Component template should have only one root node</h2>';
            echo '<pre>' . htmlspecialchars($this->getBodyHtml($document)) . '</pre>';
            exit;
        }
        return $rootNodes->item(0);
    }

    private function setErrorHint($line, $expression) {
        $this->errorLine = $line;
        $this->errorExpression = $expression;
    }

}
