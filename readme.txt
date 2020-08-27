=== WooCommerce PayPal Checkout Payment Gateway ===
Contributors: woocommerce, automattic, woothemes, akeda, dwainm, royho, allendav, slash1andy, woosteve, spraveenitpro, mikedmoore, fernashes, shellbeezy, danieldudzic, mikaey, fullysupportedphil, dsmithweb, corsonr, bor0, zandyring, pauldechov, robobot3000
Tags: ecommerce, e-commerce, commerce, woothemes, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, paypal
Requires at least: 4.4
Tested up to: 5.4
Requires PHP: 5.5
Stable tag: 2.0.3
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

= 2.0.3 - 2020-07-01 =
* Fix - Records the proper refunded_amount to _woo_pp_txnData in the database PR#764
* Fix - Redirect customers back to the original page they left on after closing PayPal modal PR#765
* Fix - Preserve horizontal layout style setting when using standalone buttons PR#774
* Fix - Smart payment buttons compatibility with older browsers PR#778
* Tweak - Update the Require Phone Number field description PR#772
* Dev - Make the SDK script args filterable PR#763

= 2.0.2 - 2020-05-28 =
* Fix - Javascript errors during checkout when the Payment Action is set to Authorize. PR#754
* Fix - Style the Smart Payment Buttons according to the chosen button size setting. PR#753
* Tweak - Change the "or" separator used on the cart page to be consistent with other payment gateways (uppercase and 100% opacity). PR#755

= 2.0.1 - 2020-05-26 =
* Fix - PayPal buttons not loading on the page, accompanied with the javascript/console error: "paypal.getFundingSources (or paypal.Buttons) is not a function". PR#740

= 2.0.0 - 2020-05-25 =
* New - Upgrade to the latest PayPal Checkout Javascript SDK. PR#668
* Add - New setting found under Button Styles for choosing a Smart Payment Button label. PR#666
* Add - Support for more locales. PR#658
* Fix - Display Smart Payment Buttons on Product pages built from a shortcode. PR#665
* Fix - Send the product SKU to PayPal so it's displayed in the order/transaction details and reports on PayPal. PR#664
* Fix - Show an error when saving incomplete/missing API credentials. PR#712
* Fix - Remove PHP warnings in later versions of PHP when a PayPal Session doesn't exist. PR#727
* Fix - Error when processing refunds (Already Refunded. No Amount to Refund). PR#710
* Fix - Required state field errors on the "Confirm your PayPal Order" page when returning from PayPal. PR#725
* Fix - Display WC Add To Cart validation errors on the product page when clicking the PayPal Smart Payment Buttons. PR#707
* Update - Smart Payment Buttons are enabled by default and settings to toggle these on/off have been removed and replaced with a filter. PR#660
* Update - Deprecate unused/incomplete function `WC_Gateway_PPEC_Client::update_billing_agreement()`. PR#602
* Update - Move inline javascript found in `settings-ppec.php` to `ppec-settings.js`. PR#676
* Update - Move Support and Documentation links from the plugin actions to plugin meta section on the Plugin activation/deactivation page. PR#735
* Update - WooCommerce 4.1 and WordPress 5.4 compatibility. PR#732

= 1.6.21 - 2020-04-14 =
* Fix - Ensure Puerto Rico and supported Locales are eligible for PayPal Credit. PR#693
* Fix - Support purchasing subscriptions with $0 initial payment - free trials, synced etc. PR#698
* Fix - Only make the billing fields optional during an active PayPal Checkout session. PR#697
* Fix - Uncaught JS errors on product page when viewing and out-of-stock product. PR#704
* Fix - Loading API certificates and improves managing certificate settings. PR#696
* Fix - Displaying PayPal Smart Payment buttons on pages with single product shortcode. PR#665
* Fix - Do not add discounts to total item amount and cause line item amount offset. PR#677
* Fix - Redirect to Confirm your PayPal Order page for subscriptions initial purchases using PayPal Smart Buttons. PR#702
* Fix - Display missing checkout notice when email format is incorrect. PR#708
* Add - Filter product form validity via a new `wc_ppec_validate_product_form` event. PR#695
* Add - Translation tables for states of more countries. PR#659
* Update - WooCommerce 4.0 compatibility

= 1.6.20 - 2020-02-18 =
* Fix - Upgrade the plugin on plugins loaded rather than on plugin init. PR#682

= 1.6.19 - 2020-02-06 =
* Fix - Check if order exists before adding order actions. PR #653
* Fix - Global attributes stripped before sent to PayPal if unicode characters. PR#470
* Fix - Handle subscription payment change. PR#640
* Fix - Fixes error "Trying to get property of non-object" found during onboarding wizard. PR#654
* Fix - Hide smart payment buttons on mini cart when cart is empty. PR#450
* Fix - Only display smart buttons on product page if product is in stock. PR#662
* Fix - Do not display smart buttons for external products and grouped products. PR#663
* Update - Display a WooCommerce pre 3.0 admin notice warning. In an upcoming release PayPal Checkout will drop support for WC 2.6 and below. PR#671

= 1.6.18 - 2019-12-05 =
* Fix - Send fees to PayPal as line items
* Fix - Fix error 10426 when coupons are used
* Fix - Call to a member function has_session() on null
* Add - Notice about legacy payment buttons deprecation
* Fix - Use order currency when renewing subscription instead of store currency
* Update - WooCommerce 3.8 compatibility
* Update - WordPress 5.3 compatibility

= 1.6.17 - 2019-08-08 =
* Update - WooCommerce 3.7 compatibility
* Add - Filter to require display of billing agreement during checkout
* Add - Add CURRENCYCODE to capture_payment
* Add - Add filter for buttons on products
* Fix - Skip wasteful render on initial Checkout page load
* Fix - Appearance tweaks on Checkout screen

= 1.6.16 - 2019-07-18 =
* Fix - Don't require address for renewal of virtual subscriptions
* Fix - Avoid broken confirmation screen edge case after 10486 redirect

= 1.6.15 - 2019-06-19 =
* Fix - Prevent PHP errors when no billing details are present in PP response
* Fix - Require billing address for virtual products when enabled
* Add - Hook when a payment error occurs

= 1.6.14 - 2019-05-08 =
* Fix - Failing checkout when no addons are used

= 1.6.12 - 2019-05-08 =
* Fix - Better handling of virtual subscriptions when billing address is not required
* Fix - Prevent errors showing when purchasing a virtual product with WP_DEBUG enabled

= 1.6.11 - 2019-04-17 =
* Fix/Performance - Prevent db option updates during bootstrap on each page load
* Tweak = WC 3.6 compatibiliy.

= 1.6.10 - 2019-03-05 =
* Fix - Use only product attributes when adding to cart

= 1.6.9 - 2019-02-03 =
* Fix - Avoid SPB render error by tweaking 'allowed' funding methods' empty value

= 1.6.8 - 2019-01-25 =
* Fix - Guard against themes applying filter with too few params

= 1.6.7 - 2019-01-25 =
* Fix - Error 10413 when using coupons
* Fix: All variation details when using buttons on product pages are kept
* Fix: Always render the PayPal buttons in the mini cart

= 1.6.6 - 2019-01-09 =
* Fix - Discount items were not being included
* Add - Filter for order details to accept decimal quantities of products
* Fix - Unable to buy variation from product page
* Fix - Can use PayPal from product page without inputting required fields
* Add - Display PayPal fees under the totals on the order admin page
* Add - Prefill name, phone, and email info in PayPal Guest Checkout from checkout screen

= 1.6.5 - 2018-10-31 =
* Fix - Truncate the line item descriptions to avoid exceeding PayPal character limits.
* Update - WC 3.5 compatibility.
* Fix - checkout.js script loading when not needed.
* Fix - Missing shipping total and address when starting from checkout page.

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

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-paypal-express-checkout/main/changelog.txt).
