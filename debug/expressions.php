<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../vendor/autoload.php';

use \LorenzV\VuePre\ConvertJsExpression;

$data = [
    'foo' => 6,
    'product' => [
        'name' => 'Foobar',
        'active' => true,
        'price' => [
            'value' => 5,
        ],
    ],
];

$expressions = [
    'foo',
    'foo > 6',
    'foo < 6',
    'foo == 6',
    "foo == '6'",
    'foo === 6',
    'product',
    'product.active',
    '!product',
    '!product.active',
    '{ active: product.active }',
    '{ active: product.active ? true : false }',
    'product.price.value > 5',
    'product.price.value === 5',
    'product.price.value < 7',
    'product.price.value < (product.active ? 7 : 3)',
    '[1, 2] === [1, 2]',
    '[1, 2] !== [1, 2]',
    '[1, true] === [1, product.active]',
    '[1, true].indexOf(1)',
    '[1, true].indexOf(2)',
    '[1, true].indexOf(true)',
    "'abc'.indexOf('b')",
    "product.name.indexOf('a')",
    '[1, true].length()',
    "'abc'.length()",
    'product.name.length()',
];

$runPhpExpr = function ($ex, $averyrandomvarname) {
    foreach ($averyrandomvarname as $k => $v) {
        ${$k} = $v;
    }

    try {
        // ini_set('display_errors', 0);
        eval('$res = (' . $ex . ');');
        // ini_set('display_errors', 1);
        if (is_bool($res)) {
            $res = $res ? 'true' : 'false';
        }
        return $res;
    } catch (Exception $e) {
        return '#ERROR';
    }
};

$style = 'background-color:#eee; padding:3px 8px; border:1px solid #ccc; display: inline-block;';
foreach ($expressions as $ex) {
    $phpex = ConvertJsExpression::convert($ex);
    echo '<div style="margin-bottom: 5px;">';
    echo '<span style="' . $style . '">' . $ex . '</span>';
    echo ' => ';
    echo '<span style="' . $style . '">' . $phpex . '</span>';

    $result = $runPhpExpr($phpex, $data);
    echo ' => ';
    echo '<span style="' . $style . '">';
    print_r($result);
    echo '</span><br>';
    echo '</div>';
}
