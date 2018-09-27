=== WooCommerce PayPal Checkout Payment Gateway ===
Contributors: automattic, woothemes, akeda, dwainm, royho, allendav, slash1andy, woosteve, spraveenitpro, mikedmoore, fernashes, shellbeezy, danieldudzic, mikaey, fullysupportedphil, dsmithweb, corsonr, bor0, zandyring, pauldechov
Tags: ecommerce, e-commerce, commerce, woothemes, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, paypal
Requires at least: 4.4
Tested up to: 4.9.6
Stable tag: 1.6.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Accept PayPal, Credit Cards and Debit Cards on your WooCommerce store.

== Description ==

This is a PayPal Checkout Payment Gateway for WooCommerce.

PayPal Checkout allows you to securely sell your products and subscriptions online using In-Context Checkout to help you meet security requirements without causing your theme to suffer.  In-Context Checkout uses a modal window, hosted on PayPal's servers, that overlays the checkout form and provides a secure means for your customers to enter their account information.

Also, with Integrated PayPal Setup (Easy Setup), connecting to PayPal is as simple as clicking a button - no complicated API keys to cut and paste.

== Installation ==

= Minimum Requirements =

* WordPress 4.4 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "WooCommerce PayPal Checkout" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favorite FTP application. The
WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

If on the off-chance you do encounter issues with the shop/category pages after an update you simply need to flush the permalinks by going to WordPress > Settings > Permalinks and hitting 'save'. That should return things to normal.

== Frequently Asked Questions ==

= Does this plugin work with credit cards or just PayPal? =

This plugin supports payments using both credit and debit cards as well as PayPal. The new Smart Payment Buttons feature dynamically displays PayPal, Venmo (US Only), PayPal Credit, or other local payment options* in a single stack—without needing to leave the merchant's website.

*PayPal Checkout features may not be available in all countries.

= Does this support Checkout with PayPal from the cart view? =

Yes!

= Does this support both production mode and sandbox mode for testing? =

Yes it does - production and sandbox mode is driven by how you connect.  You may choose to connect in either mode, and disconnect and reconnect in the other mode whenever you want.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://docs.woocommerce.com/document/paypal-express-checkout/)

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

= Will this plugin work with my theme? =

Yes, this plugin will work with any theme, but may require some styling to make it match nicely. If you're
looking for a theme with built in WooCommerce integration we recommend [Storefront](http://www.woothemes.com/storefront/).

= Where can I request new features or report bugs? =

New feature requests and bugs reports can be made in the plugin forum.

= How to remove 'Proceed to Checkout' button from cart page? =

If PayPal Checkout is the only enabled payment gateway and you want to remove the 'Proceed to Checkout' button from the cart, you can use this snippet:

https://gist.github.com/mikejolley/ad2ecc286c9ad6cefbb7065ba6dfef48

= Where can I contribute? =

The GitHub repository for PayPal Checkout is here:

https://github.com/woocommerce/woocommerce-gateway-paypal-express-checkout

Please use this to inform us about bugs, or make contributions via PRs.

== Screenshots ==

1. Click the "Setup or link an existing PayPal account" button. If you want to test before going live, you can switch the Environment, above the button, to Sandbox.
2. API credentials will be set after linking, or you can set them manually.
3. See PayPal button settings below.
4. Checkout with PayPal directly from the Cart.
5. And without leaving the store.
6. Confirm details after clicking "Continue".
7. Choose PayPal from regular checkout page.
8. Choose PayPal from single product page.
9. Initiate checkout from mini-cart.

== Changelog ==

= 1.6.4 - 2018-09-27 =
* Fix - Billing address from Checkout form not being passed to PayPal via Smart Payment Button.
* Fix - Checkout form not being validated until after Smart Payment Button payment flow.

= 1.6.3 - 2018-08-15 =
* Fix - Fatal error caused by a fix for Smart Payment Buttons.

= 1.6.2 - 2018-08-15 =
* Fix - Tax not applied on the (Confirm your PayPal order) page at the checkout.

= 1.6.1 - 2018-07-04 =
* Fix - GDPR Fatal error exporting user data when they have PPEC subscriptions.
* Fix - PayPal Credit still being disabled by default.
* Update - Rename 'PayPal Express Checkout' to 'PayPal Checkout'.
* Fix - Missing PayPal branding in "Buy Now" Smart Payment Button.
* Fix - PHP warning when PayPal Credit not supported and no funding methods hidden.
* Fix - Smart Payment Buttons gateway not inheriting IPN and subscription handling.
* Fix - Single product Smart Payment Button failing without existing session.
* Fix - When cart is empty, JS error on cart page and mini-cart payment buttons showing.
* Add - Locale filter.

= 1.6.0 - 2018-06-27 =
* Add - Smart Payment Buttons mode as alternative to directly embedded image links for all instances of PayPal button.
* Fix - Help tip alignment for image settings.
* Update - Enable PayPal Credit by default, and restrict its support by currency.
* Update - Omit 'Express Checkout' portion of default payment method title.
* Update - Enable Express Checkout on regular checkout page by default.
* Update - Enable Express Checkout on single product page by default.

= 1.5.6 - 2018-06-06 =
* Fix    - Virtual products cause issues with billing details validation.

= 1.5.5 - 2018-05-23 =
* Update - WC 3.4 compatibility
* Update - Privacy policy notification.
* Update - Export/erasure hooks added.

= 1.5.4 - 2018-05-08 =
* Add - Hook to make billing address not required `woocommerce_paypal_express_checkout_address_not_required` (bool).
* Fix - Duplicate checkout settings when PP Credit option is enabled.
* Fix - Impossible to open API credentials after saving Settings.
* Fix - Prevent filtering if PPEC is not enabled.
* Fix - Single Product checkout: Quantity being duplicated due to multiple AJAX calls.
* Fix - When returning from PayPal, place order buttons says "proceed to payment".
* Tweak - Default billing address to be required.

= 1.5.3 - 2018-03-28 =
* Fix - wp_enqueue_media was not correctly loaded causing weird behavior with other parts of system wanting to use it.
* Fix - Typo in activation hook.

= 1.5.2 - 2018-02-20 =
* Tweak - Express checkout shouldn't display "Review your order before the payment".
* Fix - Compatibility with Subscriptions and Checkout from Single Product page.
* Fix - Make sure session object exists before use to prevent fatal error.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-paypal-express-checkout/master/changelog.txt).
