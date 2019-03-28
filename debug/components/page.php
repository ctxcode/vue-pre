
<template>
    <layout :layout-data="layoutData">
        <!-- output && expressions -->
        <div>{{ --- }}</div>
        <div>{{ 420 }}</div>
        <div>{{ "'" }}</div>
        <div>{{ '"' }}</div>
        <div>{{ (1 > 2) ? '' : 'o' }}</div>
        <div>{{ 'four-twenty' }}</div>
        <div>{{ myVar }}</div>
        <div>{{ myObject.myProp }}</div>
        <div>{{ ([1,2,3].indexOf(2) === 1) ? 'Found' : 'Not found' }}</div>
        <div>{{ ([1, myVar,3].indexOf(myVar) === 1) ? 'Found' : 'Not found' }}</div>

        Toggle template:
        <template v-if="toggle">On</template>
        <template v-else>Off</template>

        <!-- foreach && if/else-if/else -->
        <div v-for="i in [1,2]">
            <h2>Test#{{ i }}</h2>
            <div v-if="myVar === 'Hello'">
                <div v-if="myVar !== 'Hello'">IF1</div>
                <div v-else-if="myVar === 'Hello'">
                    <div v-if="myVar !== 'Hello'">IF2</div>
                    <div v-else-if="i === 1">ELSEIF2</div>
                    <div v-else>ELSE2</div>
                </div>
            </div>
        </div>

        <button v-on:click="tog">Toggle</button>

        <div v-if="toggle">TEST TOGGLE</div>

        <component :is="dynCompo" :title="title"></component>

        <!-- Components + slots -->
        <mypartial :title="title">
            <template v-slot:header>
                <h1>Here might be a page title</h1>
            </template>
            <div>
                <span>{{ myVar }}</span><span>{{ myVar }}</span>
            </div>
            <p>Rererererererer</p>
        </mypartial>
    </layout>
</template>

<script type="text/javascript">

    Vue.component('page', {
        props: ['vuePreData'],
        template: '#vue-template-page',
        data: function () {
            return this.vuePreData;
        },
        methods: {
          tog: function(){
              this.toggle = !this.toggle;
              console.log(this.toggle);
          }
        },
    });
</script>
