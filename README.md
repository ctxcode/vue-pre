
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
// It's going to look for any .html file and register the filename as a component
// So, if you have components/pages/homepage.html
// It will set that html as the template for <homepage>

// You can also use directories as your component name if you put a template.html in it
// e.g. components/pages/homepage/template.html
```
Having your component name as a directory allows you to keep your code together
You can setup your folder like this:
```
- components/pages/my-page/template.html
- components/pages/my-page/component.js // Optional
- components/pages/my-page/component.php // Optional, see "Component settings" in readme
```

## Component settings

If you have a Vue component like this

```javascript
Vue.component('product', {
	props: ['product']
	data: {
		name: this.product.name,
		price: this.product.price,
		showPrice: false,
	},
	methods: {
		...
	}
});
```
And you use name & price in your template, then you need to do the same in PHP.

```php
<?php
// components/shop/product/component.php
return [
	'beforeRender' => function(&$data){
		$data['name'] = $data['product']['name'];
		$data['price'] = $data['product']['price'];
		$data['showPrice'] = false;
	}
];
```
You could of course base your template on the $props data. But this results in ugly template code.

## Generating \<scripts>

You can generate scripts for your component templates and your component.js files.

```php
// Based on your last render
$vue->getScripts(); // templates (+ component.js files if available)
$vue->getTemplateScripts(); // only templates
$vue->getComponentScripts(); // ony component.js files

// By component name
$vue->getTemplateScript('my-page');
$vue->getComponentScript('my-page');

// Without <script>
$vue->getTemplate('my-page');
$vue->getComponentJs('my-page');

// Usefull
$vue->getRenderedComponentNames();
```

## API

```php
->setCacheDirectory(String $path)
->setComponentDirectory(String $path)
->renderHtml(String $html, Array $data)
->renderComponent(String $componentName, Array $data)

// Set component settings manually
->setComponentMethods(Array<String $componentName, AnonFunction>)
->setComponentBeforeRender(Array<String $componentName, AnonFunction>)
->setComponentTemplate(Array<String $componentName, String $html>) 
->setComponentAlias(Array<String $componentName, String $alias>)

// Get component info
->getComponentAlias(String $componentName, $default = null)
->getComponentNameViaAlias(String $alias, $default = null)
->getTemplate(String $componentName, $default = null);
->getComponentJs(String $componentName, $default = null);

// Generating scripts
->getScripts();
->getTemplateScripts();
->getComponentScripts();
->getTemplateScript(String $componentName, $default = null);
->getComponentScript(String $componentName, $default = null);

// Others
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
