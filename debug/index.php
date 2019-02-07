<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../vendor/autoload.php';

$data = [
    'title' => '<h2>Hi</h2>',
    'toggle' => true,
    'aclass' => 'laclass',
    'messages' => explode(' ', 'Hello there my old chum'),
    'myVar' => 'Hello',
    'myObject' => (object) [
        'myProp' => 'World',
    ],
];

$vue = new \LorenzV\VuePre\VuePre();
$vue->disableCache = true;
$vue->setCacheDirectory(__DIR__ . '/cache');
$vue->setComponentDirectory(__DIR__ . '/components');

$vue->setComponentAlias([
    'mypartial' => 'partials.mypartial',
]);

// $benchSeconds = 2;
// $end = time() + $benchSeconds;
// $compileTimes = 0;
// while (time() < $end) {
//     $html = $vue->renderComponent('page', $data);
//     $compileTimes++;
// }
// echo 'Compiled ' . ($compileTimes / $benchSeconds) . ' times per second';
// exit;

$html = $vue->renderComponent('page', $data);
// $html = $vue->renderHtml('<div>{{ title }}</div>', $data);
?>

<div id="app">
    <?php echo $html; ?>
</div>

