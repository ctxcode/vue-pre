<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../vendor/autoload.php';

$data = [
    'shop' => [
        'name' => 'My Shop',
        'products' => [
            ['name' => 'Bread'],
            ['name' => 'Horse'],
            ['name' => 'Stingray'],
            ['name' => 'Ball of paper'],
            ['name' => 'Ceiling'],
            ['name' => 'Wheels'],
        ],
    ],
];

$vue = new \LorenzV\VuePre\VuePre();
$vue->disableCache = true;
$vue->setCacheDirectory(__DIR__ . '/cache');
$vue->setComponentDirectory(__DIR__ . '/templates');
// $vue->setComponentAlias([
//     'product-list' => 'partials.product-list',
//     'product' => 'partials.product',
// ]);

$html = $vue->renderComponent('shop', $data);
?>
<!DOCTYPE html>
<html>
    <head>
        <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
        <link rel="stylesheet" href="./css/shop.css">
    </head>
    <body>
        <div id="app">
            <?php echo $html; ?>
        </div>
    </body>
</html>