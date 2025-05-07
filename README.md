# Not Sold Separately for WooCommerce

Limit WooCommerce products to being sold only as part of a [Mix and Match](https://woocommerce.com/products/woocommerce-mix-and-match-products/) product.

### Quickstart

This is a developmental repo. Clone this repo and run `npm install && npm run build`   
or    

|[Download latest release](https://github.com/backcourt/wc-not-sold-separately/releases/latest)|
|---|

## Usage

1. Edit a simple product and navigate to the "Inventory" tab in the product data metabox.

![product data metabox showing "not sold separately" checkbox in Inventory tab](https://user-images.githubusercontent.com/507025/138338330-b298f31d-9de5-4780-98be-5985dfe37383.png)

2. For an individual variation, you can set the status in the variation options.
![variation panel showing "not sold separately" checkbox](https://user-images.githubusercontent.com/507025/138338616-40203402-07af-4fb7-a62c-4666a6ec9054.png)

3. Save the product/variation.

When visiting the single product page, the add to cart button should now be gone:

![single product page for Woo Ninja product with no add to cart button](https://user-images.githubusercontent.com/507025/77197200-62a22700-6aaa-11ea-9cbb-23219079c56d.png)

And that particular variation should not be visible when visiting a variable product page.

However, the product can still be purchased as part of a container:

![The Woo Ninja product appears in cart as part of T-Shirt 6 Pack](https://user-images.githubusercontent.com/507025/77197688-405cd900-6aab-11ea-9312-239452036126.png)

## Compatibility Requirements
[Mix and Match](https://woocommerce.com/products/woocommerce-mix-and-match-products/?) 2.0.0

### Automatic plugin updates

Plugin updates can be enabled by installing the [Git Updater](https://git-updater.com/) plugin.

## Important

1. This is proof of concept and does not receieve priority support.
2. At this time, only Mix and Match containers are supported.
