
# VuePre (WIP)
VuePre is a package to prerender vue templates. This is useful for SEO and avoiding blank pages on page load. What VuePre does, is translating the Vue template to a pure PHP template (including all JS expressions) and caches it. Having the templates in pure PHP results in really great performace. 

## Installation
```
composer require lorenzv/php-vue-template-prerender
```

## Basic usage

```php 
$vue = new \LorenzV\VuePre\VuePre();
$vue->setCacheDirectory(__DIR__ . '/cache');
$vue->setComponentDirectory(__DIR__ . '/templates');
$vue->setComponentAlias([
    'product-list' => 'partials.product-list',
    'product' => 'partials.product',
]);
$data = [...];
$html = $vue->renderComponent('mypage', $data);
```

