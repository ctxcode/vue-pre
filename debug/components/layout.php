<?php
return [
    'beforeRender' => function (&$data) {
        $data = $data['layout-data'];
    },
];
?>

<!-- TEMPLATE -->
<div>
	<header>{{ title }}</header>
    <template>{{ title }}<span> :) </span></template>
	<main>
        <slot></slot>
	</main>
	<footer>...</footer>
</div>
<!-- END -->

<!-- JS -->
<script type="text/javascript">
    Vue.component('layout', {
        props: ['layoutData'],
        template: '#vue-template-layout',
        data: function () {
            return this.layoutData;
        },
    });
</script>
<!-- END -->
