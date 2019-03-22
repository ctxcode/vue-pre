
<!-- TEMPLATE -->
<div>
    <div v-if="products.length === 0">
        No products :(
    </div>

    <div class="list">
        <product v-for="product in products" :product="product"></product>
    </div>
</div>
<!-- END -->
