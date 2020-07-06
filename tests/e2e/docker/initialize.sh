#!/bin/bash

# Remove all WP pages - Docker wait-for-build uses a page with --post_title="ready" to determine when the environment is ready. We need to remove this file which is created in docker/entrypoint.sh and re-create it at the end of this file.
wp post delete $(wp post list --post_type='page' --format=ids)

echo "Updating permalink structure"
wp rewrite structure '/%postname%/'

echo "Initializing WooCommerce E2E"
wp plugin install woocommerce --activate
wp theme install storefront --activate

echo "Activate and setup PayPal Checkout"
wp plugin activate woocommerce-gateway-paypal-express-checkout

echo "Adding basic WooCommerce settings..."
wp option set woocommerce_store_address "60 29th Street"
wp option set woocommerce_store_address_2 "#343"
wp option set woocommerce_store_city "San Francisco"
wp option set woocommerce_default_country "US:CA"
wp option set woocommerce_store_postcode "94110"
wp option set woocommerce_currency "USD"
wp option set woocommerce_product_type "both"
wp option set woocommerce_allow_tracking "no"

echo "Importing WooCommerce shop pages..."
wp wc --user=admin tool run install_pages

echo "Installing and activating the WordPress Importer plugin"
wp plugin install wordpress-importer --activate
echo "Importing the WooCommerce sample data..."
wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

wp user create customer customer@woocommercecoree2etestsuite.com --user_pass=password --role=customer
# Create the page which is used to determine if the test environment has been setup
wp post create --post_type=page --post_status=publish --post_title='Ready' --post_content='E2E-tests.'
echo "Success! E2E test environment has been setup"
