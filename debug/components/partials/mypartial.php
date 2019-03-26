<?php

return [
    'beforeRender' => function (&$data) {
        $data['showThis'] = true;
    },
];

?>

<template>
    <div>
        <div v-html="title"></div>

        <slot name="header"></slot>

        <div>-------------</div>

        <slot></slot>

        <div>-------------</div>

        <slot name="header"></slot>

        <div v-if="showThis">Show this</div>
        <div v-else>Not this</div>
    </div>
</template>

<script type="text/javascript">

    Vue.component('mypartial', {
        props: ['title'],
        template: '#vue-template-mypartial',
        data: function () {
            return {
                showThis: true,
            }
        },
        methods: {
          tog: function(){
              this.toggle = !this.toggle;
              console.log(this.toggle);
          }
        }
    });
</script>
