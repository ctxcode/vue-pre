

# VuePre (WIP)
VuePre is a package to prerender vue templates. This is useful for SEO and avoiding blank pages on page load. What VuePre does, is translating the Vue template to a pure PHP template (including all JS expressions) and caches it. Having the templates in pure PHP results in really great performace. 

## Note

This package is still under development and can change frequently

## Installation
```
composer require lorenzv/php-vue-template-prerender
```

## Basic usage

```php 
$vue = new \LorenzV\VuePre\VuePre();
$vue->setCacheDirectory(__DIR__ . '/cache');

// Method 1
$data = ["name" => "world"];
$html = $vue->renderHtml('<div>Hello {{ name }}!</div>', $data);

// Method 2 - Using component directory (required if you use sub-components)
$vue->setComponentDirectory(__DIR__ . '/components');
$html = $vue->renderComponent('my-page', $data);
```

## Component directory

```php
// If you set your directory like this
$vue->setComponentDirectory(__DIR__ . '/components');
// It's going to look for any .php file and register the filename as a component
// So, if you have components/pages/homepage.php
// It will use this file for the <homepage> component
```

## Component example

```php
<?php
return [
    'beforeRender' => function (&$data) {
	    $data['message'] = 'Hello';
    },
];
?>

<!-- TEMPLATE -->
<div>
	<p>{{ message }}</p>
</div>
<!-- END -->

<!-- JS -->
<script type="text/javascript">

    Vue.component('homepage', {
        template: '#vue-template-homepage',
        data: function () {
            return {
	            message: 'Hello';
            };
        },
        methods: {
        }
    });
</script>
<!-- END -->
```

## Generating \<scripts>

You can generate scripts for your component templates and your component.js files.

```php
// Based on your last render
$vue->getScripts();
$vue->getTemplateScripts(); // only template scripts
$vue->getJsScripts(); // only js scripts

// By component name
$vue->getTemplateScript('my-page');
$vue->getJsScript('my-page');

// Usefull
$vue->getRenderedComponentNames();
```

## API

```php
->setCacheDirectory(String $path)
->setComponentDirectory(String $path)
->renderHtml(String $html, Array $data)
->renderComponent(String $componentName, Array $data)

// Generating scripts
->getScripts($idPrefix = 'vue-template-');
->getTemplateScripts($idPrefix = 'vue-template-');
->getTemplateScript(String $componentName, $default = null, $idPrefix = 'vue-template-');
->getJsScripts();
->getJsScript(String $componentName, $default = null);

// Others
->getComponentAlias(String $componentName, $default = null)
->getRenderedComponentNames();
```


## JS expressions | Supported

```
# Prototype functions
.indexOf()
.length

# Values: variables, strings, numbers, booleans, null, objects, arrays, functions

# Comparisons
myVar === 'Hello'
something ? 'yes' : false

# Nested expressions
(((5 + 5) > 2) ? true : false) ? (myBool ? 'Yes' : 'Yez') : 'No'

# Objects
product.active ? product.name : product.category.name

# Methods using $vuePre->setMethods(['myFunc'=> function(){ ... }])
product.active ? myFunc(product.name) : null
```
## JS expressions | Unsupported

```
# Computed variables (see note in "Todo" section)
```

## JS expressions | Common errors

```
# Nested comparisons
ERROR: [1, myVar,3].indexOf(myVar) === 1 ? 'Found' : 'Not found'
FIX: ([1, myVar,3].indexOf(myVar) === 1) ? 'Found' : 'Not found'
```
Currently i don't have many examples. More will be added later. Feel free to make an issue if you have trouble parsing a certain expression.


## Todos

Note: Feel free to make an issue for these, so i can make them a prority. The only reason these are not implemented yet is because of low priority.

- Handle `<template>` elements
- Attributes `v-model` `:value` `:selected` `:checked` `:style`
- Binding non-binding attributes to components
- Custom error handlers
- Options: 
	- `ignoreVariableNotFound` `ignoreMethodNotFound`
	- `ignoreVariableNames` `ignoreMethodNames`
	- `ignoreSubComponents` `ignoreSubComponentNames`
- Computed values
- Look into `<slot></slot>` tags

## Contributors

The DOM iterator code was partially copied from [wmde/php-vuejs-templating](https://github.com/wmde/php-vuejs-templating)
