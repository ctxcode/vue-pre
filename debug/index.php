<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../vendor/autoload.php';

$data = [
    'title' => '<h2>Hi</h2>',
    'toggle' => true,
    'aclass' => 'laclass',
    'messages' => explode(' ', 'Hello there my old chum'),
];

$benchSeconds = 2;
$end = time() + $benchSeconds;
$compileTimes = 0;

$vue = new \LorenzV\VuePre\VuePre();
$vue->devMode = true;
$vue->setCacheDirectory(__DIR__ . '/cache');
$vue->setComponentDirectory(__DIR__ . '/templates');

// while (time() < $end) {
//     $html = $vue->renderComponent('page', $data);
//     $compileTimes++;
// }
// echo 'Compiled ' . ($compileTimes / $benchSeconds) . ' times per second';
// exit;

$html = $vue->renderComponent('page', $data);
?>

<div id="app">
    <?php echo $html; ?>
</div>

