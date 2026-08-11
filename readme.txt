=== Product Finder ===
Contributors:      TODO-add-wordpress-org-username
Tags:               woocommerce, product finder, gutenberg, block, quiz
Requires at least:  6.8
Tested up to:      6.8
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A Gutenberg-native product finder for WooCommerce block-theme stores: shoppers answer a few questions and see the best-matching products update instantly, with an honest explanation of the match.

== Description ==

Product Finder helps shoppers narrow a high-consideration WooCommerce catalogue — tents, sleeping bags, anything with more SKUs than a shopper wants to compare by hand — down to the right few products, without leaving the page.

= How it works =

1. A shopper answers a handful of simple questions (dropdowns and toggles) about what they need.
2. Matching products update live, powered by the WordPress Interactivity API — no page reload.
3. Each question is either a **hard filter** (must match, e.g. "sleeps at least 4") or a **weighted soft preference** (e.g. "prefers backpacking use"), so results are ranked, not just filtered.
4. If nothing matches every filter, Product Finder automatically relaxes constraints — in an order the merchant controls — and tells the shopper exactly what was relaxed and why, instead of just showing an empty page.
5. Each result shows its image, price, stock status, and the key specs that mattered to the match, with an "Add to cart" button right there.

= Built for the merchant, not just the shopper =

* **WooCommerce → Product Finder** admin screen: map your store's real product attributes to the finder's questions, with an attribute-completeness snapshot up front (e.g. "42 of 61 tents have packed weight set") so gaps in your catalogue data are visible before shoppers hit them.
* Customize each category's questions — label, hard filter vs. soft preference, comparison type, and weight — with answer choices auto-discovered from your own catalogue, not hand-typed.
* Basic local usage stats per category (views and how often shoppers land on zero matches) shown right on the settings screen. No third-party analytics, no PII.
* Ships with a ready-to-use five-question starter template (capacity, use type, season rating, packed weight, price) so a first category can be usable immediately, before any custom mapping.

= Works everywhere, degrades gracefully =

* Multiple Finder blocks can be placed on the same page, each scoped to its own category, without interfering with each other.
* Fully functional with JavaScript disabled: a real `<form method="get">` submits to the same page and the server renders the same results, so filtered URLs stay bookmarkable and shareable either way.
* No lead capture, ever, inside the finder itself — this plugin is about product fit, not funnels.

== Installation ==

1. Make sure WooCommerce is installed and active — Product Finder is a WooCommerce companion block and needs it to see your products and categories.
2. Upload the plugin files to the `/wp-content/plugins/product-finder` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the "Plugins" screen in WordPress.
4. Go to **WooCommerce → Product Finder** to review attribute completeness and customize the questions for a category.
5. Add the "Product Finder" block to any page or post, and choose which product category it should search.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. Product Finder reads your WooCommerce products, categories, and attributes directly — it has nothing to show without WooCommerce active.

= Does it work with my theme? =

Product Finder is a Gutenberg block built for block themes and renders its own markup, so it works with any block theme. It does not fetch or send data to any third-party service.

= What happens if no products match every answer? =

Product Finder relaxes hard filters one at a time, in the order the merchant set on the Questions screen, until at least one product qualifies — and tells the shopper which constraint was relaxed instead of just showing an empty result.

= Does this collect any personal data? =

No. Product Finder keeps only two aggregate counters per category (page views and how often shoppers land on zero matches) to help merchants spot catalogue gaps. No answers, IP addresses, or other shopper data are stored or sent anywhere.

= Can I use more than one Finder on the same page? =

Yes. Each Finder block instance is independent, even multiple instances for different categories on the same page.

== Changelog ==

= 1.0.0 =
* Initial release: Gutenberg block with instant, no-reload result matching via the Interactivity API.
* Hard-filter and weighted soft-preference questions, with automatic fallback relaxation and an explanation of what was relaxed.
* Result cards with image, price, stock status, key specs, and add-to-cart.
* Full no-JavaScript fallback via server-rendered form submission.
* Multiple independent Finder instances supported on a single page.
* WooCommerce → Product Finder admin screen: attribute mapping, per-category attribute-completeness snapshot, and a per-category question editor with auto-discovered answer choices.
* Five-question "Tents" starter template.
* Local, non-PII usage counters (views, zero-match rate) per category.
