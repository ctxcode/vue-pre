
<!-- TEMPLATE -->
<div>
	<header>...</header>
	<main>
		<component :is="component" :data="data"></component>
	</main>
	<footer>...</footer>
</div>
<!-- END -->

<!-- JS -->
<script type="text/javascript">
    Vue.component('layout', {
        props: ['vuePreData'],
        template: '#vue-template-layout',
        data: function () {
            return this.vuePreData;
        },
    });
</script>
<!-- END -->
