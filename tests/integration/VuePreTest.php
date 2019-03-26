<?php

use PHPUnit\Framework\TestCase;

class VuePreTest extends TestCase {

    public $data = [];

    public function __construct() {
        $this->data = [
            'myVar' => 'Hello',
            'myObject' => (object) [
                'myProp' => 'World',
            ],
        ];
    }

    private function engine() {
        $vue = new \VuePre\Engine();
        $vue->devMode = true;
        $vue->setCacheDirectory(__DIR__ . '/cache');
        $vue->setComponentDirectory(__DIR__ . '/templates');
    }

    public function mustacheTags() {
        $result = $this->engine()->renderComponent('all', $this->data);
        assertThat($result, is(equalTo('<div></div>')));
    }
}