<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../vendor/autoload.php';

$data = [
    'layoutData' => [
        'title' => 'Yawza',
    ],
    'title' => '<h2>Hi</h2>',
    'toggle' => true,
    'aclass' => 'laclass',
    'messages' => explode(' ', 'Hello there my old chum'),
    'myVar' => 'Hello',
    'myObject' => (object) [
        'myProp' => 'World',
    ],
    'dynCompo' => 'mypartial',
    'myclass' => 'red',
    'style' => 'color:green',
];

$vue = new \VuePre\Engine();
$vue->disableCache = true;
$vue->setCacheDirectory(__DIR__ . '/cache');
$vue->setComponentDirectory(__DIR__ . '/components');

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
$templates = $vue->getTemplateScripts();
$js = $vue->getJsScripts();
$vueInstance = $vue->getVueInstanceScript('#app', 'page', $data);

// $html = $vue->renderHtml('<div>{{ title }}</div>', $data);
?>

<style>
.red{ color: red; }
.blue{ color: blue; }
.orange{ color: orange; }
</style>

<script src="https://cdn.jsdelivr.net/npm/vue"></script>

<div id="app">
    <?php echo $html; ?>
</div>

<?php echo $templates; ?>
<?php echo $js; ?>
<?php echo $vueInstance; ?>

