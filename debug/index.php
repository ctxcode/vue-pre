<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../vendor/autoload.php';

$data = [
    'title' => 'Hi',
    'toggle' => true,
    'aclass' => 'laclass',
];

$benchSeconds = 2;
$end = time() + $benchSeconds;
$compileTimes = 0;

$vue = new \LorenzV\PhpVueTemplatePrerender\VuePre();
$vue->devMode = true;
$vue->setCacheDirectory(__DIR__ . '/cache');
$vue->setComponentDirectory(__DIR__ . '/templates');

// while (time() < $end) {
//     $html = $vue->renderComponent('page', $data);
//     // $html = phpTemplate(__DIR__ . '/test.php', $data);
//     // $htmllen += strlen($html);
//     $compileTimes++;
// }
// echo 'Compiled ' . ($compileTimes / $benchSeconds) . ' times per second';
// exit;

$html = $vue->renderComponent('page', $data);
?>

<div id="app">
    <?php echo $html; ?>
</div>

