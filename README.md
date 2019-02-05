
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
## JS expressions | Supported

```
# Prototype functions
.indexOf()
.length

# Values: variables, numbers, booleans, null, functions

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

## Contributors

The DOM iterator code was partially copied from [wmde/php-vuejs-templating](https://github.com/wmde/php-vuejs-templating)

