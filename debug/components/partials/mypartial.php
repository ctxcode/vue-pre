<?php

return [
    'beforeRender' => function (&$data) {
        $data['showThis'] = true;
    },
];

?>

<!-- TEMPLATE -->
<div>
    <div v-html="title"></div>

    <div v-if="showThis">Show this</div>
    <div v-else>Not this</div>
</div>
<!-- END -->

<!-- JS -->
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
<!-- END -->
