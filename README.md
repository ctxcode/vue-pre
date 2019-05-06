
# VuePre
VuePre is a package to prerender vue templates. This is useful for SEO and avoiding blank pages on page load. What VuePre does, is translating the Vue template to a PHP template, then replaces the javascript expressions with PHP expressions and caches it. Once cached, you can render thousands of templates per second depending on your hardware.

## PROS
```
- Very fast
- No dependencies
```

## CONS
```
- Some javascript expressions are not supported (yet).
```

## Installation
```
composer require ctxkiwi/vue-pre
```

## Basic usage

```php 
$vue = new \VuePre\Engine();
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

<template>
	<div>
		<p>{{ message }}</p>
	</div>
</template>

<script>
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
```

## Real world example

```php
class View{
	public static function render($view, $data = []){
		// Normal PHP template engine
		...
		return $html;
	}
	public static function renderComponent($name, $data = []){
		$vue = new \VuePre\Engine();
		$vue->setCacheDirectory(Path::get('tmp'). '/cache');
		$vue->setComponentDirectory(Path::get('views') . '/components');

		$html = $vue->renderComponent($name, $data);
		$templates = $vue->getTemplateScripts();
		$js = $vue->getJsScripts();
		$vueInstance = $vue->getVueInstanceScript('#app', $name, $data);

		$html = '<div id="app">'.$html.'</div>'.$templates.$js.$vueInstance;

		return static::render('layouts/main.html', ['CONTENT' => $html];
	}
}

class ViewController{
	public function homepage(){
		$data = [
			// Dont put private data in here, because it's shared with javascript
			'layoutData' => [
				'authUser' => \AuthUser::getUser()->getPublicData(),
			],
			'featureProducts' => Product::where('featured', true)->limit(10)->get();
		];
		// Render <homepage> component
		echo View::renderComponent('homepage', $data);
	}
}
```

```html
<!-- views/layouts/main.html -->
<!DOCTYPE>
<html>
	<head>
		<script src="https://cdn.jsdelivr.net/npm/vue"></script>
	</head>
	<body>
		{!! $CONTENT !!}
	<body>
</html>
```

```php
<?php
// views/components/layout.php

return [
    'beforeRender' => function (&$data) {
        $data = $data['layout-data'];
    },
];
?>

<template>
	<div>
		<header>...</header>
		<main>
			<slot></slot>
		</main>
		<footer>...</footer>
	</div>
</template>

<script>
    Vue.component('layout', {
        props: ['layoutData'],
        template: '#vue-template-layout',
        data: function () {
            return this.layoutData;
        },
    });
</script>
```

```php
<?php
// views/components/homepage.php
?>

<template>
	<layout :layout-data="layoutData">
		<div class="homepage">
			<h1>Welcome</h1>
			<p>...</p>
			<h2>Featured products</h2>
			<div v-for="product in featuredProducts"><h3>{{ product.name }}</h3></div>
		</div>
	</layout>
</template>

<script>
    Vue.component('homepage', {
        props: ['vuePreData'],
        template: '#vue-template-homepage',
        data: function () {
            return this.vuePreData;
        },
    });
</script>
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

# JS Functions
typeof()

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

- Attributes `v-model` `:value` `:selected` `:checked` `:style`
- Custom error handlers
- Options: 
	- `ignoreVariableNotFound` `ignoreMethodNotFound`
	- `ignoreVariableNames` `ignoreMethodNames`
	- `ignoreSubComponents` `ignoreSubComponentNames`

## Contributors

The DOM iterator code was partially copied from [wmde/php-vuejs-templating](https://github.com/wmde/php-vuejs-templating)
