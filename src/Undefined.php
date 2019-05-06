<?php

namespace VuePre;

class Undefined {

    public $cacheTemplate = null;

    public function __construct($tpl) {
        $this->cacheTemplate = $tpl;
    }

    public function __toString() {
        return '';
        CacheTemplate::templateError($this->cacheTemplate->engine->errorCurrentTemplate, $this->cacheTemplate->errorLineNr, 'Cannot convert undefined variable to string');
    }

}