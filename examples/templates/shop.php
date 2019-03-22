
<!-- TEMPLATE -->
<div>
    <header>
        <span>{{ shop.name }}</span>
    </header>

    <main>
        <div class="container">
            <h2>Our products</h2>

            <product-list :products="shop.products"></product-list>

        </div>
    </main>
</div>
<!-- END -->
