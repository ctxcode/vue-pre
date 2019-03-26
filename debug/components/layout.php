<?php
return [
    'beforeRender' => function (&$data) {
        $data = $data['layout-data'];
    },
];
?>

<template>
    <div>
    	<header>{{ title }}</header>
        <template>{{ title }}<span> :) </span></template>
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
