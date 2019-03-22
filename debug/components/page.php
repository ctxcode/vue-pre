
<?php
return [
    'beforeRender' => function (&$data) {
    },
];
?>

<!-- TEMPLATE -->
<div>

    <!-- output && expressions -->
    <div>{{ 420 }}</div>
    <div>{{ 'four-twenty' }}</div>
    <div>{{ myVar }}</div>
    <div>{{ myObject.myProp }}</div>
    <div>{{ ([1,2,3].indexOf(2) === 1) ? 'Found' : 'Not found' }}</div>
    <div>{{ ([1, myVar,3].indexOf(myVar) === 1) ? 'Found' : 'Not found' }}</div>

    <!-- foreach && if/else-if/else -->
    <div v-for="i in [1,2]">
        <h2>Test#{{ i }}</h2>
        <div v-if="myVar === 'Hello'">
            <div v-if="myVar !== 'Hello'">IF1</div>
            <div v-else-if="myVar === 'Hello'">
                <div v-if="myVar !== 'Hello'">IF2</div>
                <div v-else-if="myVar !== 'Hello'">ELSEIF2</div>
                <div v-else>ELSE2</div>
            </div>
        </div>
    </div>

    <button v-on:click="tog">Toggle</button>

    <div v-if="toggle">TEST TOGGLE</div>

    <!-- Components -->
    <mypartial :title="title"></mypartial>

</div>
<!-- END -->

<!-- JS -->
<script type="text/javascript">

    Vue.component('page', {
        props: ['pageData'],
        template: '#vue-template-page',
        data: function () {
            return this.pageData;
        },
        methods: {
          tog: function(){
              this.toggle = !this.toggle;
              console.log(this.toggle);
          }
        }
    });
</script>
<!-- END -->
