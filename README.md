
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
// Lets say you configured your instance like this
$vue->setComponentDirectory(__DIR__ . '/components');

// Then this
$html = $vue->renderComponent('pages.my-page', $data);
// Will look for: .../components/pages/my-page.html
// If that does not exist, it will look for: .../components/pages/my-page/template.html

```
Having your component name as a directory allows you to keep your code together
You can setup your folder like this:
```
- components/pages/my-page/template.html
- components/pages/my-page/component.js
- components/pages/my-page/settings.php // See "Component settings" in readme
```
And it also allows you to do this:
```php
$html = $vue->renderComponent('pages.my-page', $data);
// This returns all the templates that were used in your last render and 
// puts them in all strings like this:
// <script type="text/template" id="vue-template-{componentName}">...</script>
$templateScripts = $vue->getTemplateScripts();
// Takes all your .js files that VuePre thinks are needed based on your last render
// And puts it in a <script type="text/javascript">...</script> element
$componentScripts = $vue->getComponentScripts();
// Or both together
$scripts = $vue->getScripts();
```
## Component settings

```php
<?php
return [
	'beforeMount' => function(&$data){
		// Set some defaults
		$data['openPopup'] = false;
	}
];
```

## API

```php
->setCacheDirectory(String)
->setComponentDirectory(String)
->renderHtml(String, Array)
->renderComponent(String, Array)
->setComponentMethods(Array<String $componentName, AnonFunction>)
->setComponentBeforeMount(Array<String $componentName, AnonFunction>)
// If you dont want a componentDirectory, use setComponentTemplate
->setComponentTemplate(Array<String $componentName, String $html>) 
// Returns an array of all components that were used while rendering
->getRenderedComponents() 

# HTML Generating
->getTemplateScripts()
->getComponentScripts()
->getScripts() // Both template & component.js <script> elements
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
# Computed variables
```

## JS expressions | Common errors

```
# Nested comparisons
ERROR: [1, myVar,3].indexOf(myVar) === 1 ? 'Found' : 'Not found'
FIX: ([1, myVar,3].indexOf(myVar) === 1) ? 'Found' : 'Not found'
```
Currently i don't have many examples. More will be added later. Feel free to make an issue if you have trouble parsing a certain expression.


## Todos

- Attributes `v-model` `:value` `:selected` `:checked` `:style`
- Hooks: 
	- `BeforeRenderComponent` `AfterRenderComponent`
- Custom error handlers
- Options: 
	- `ignoreVariableNotFound` `ignoreMethodNotFound`
	- `ignoreVariableNames` `ignoreMethodNames`
- Computed values (if possible)
- Look into `<slot></slot>` tags

## Contributors

The DOM iterator code was partially copied from [wmde/php-vuejs-templating](https://github.com/wmde/php-vuejs-templating)

