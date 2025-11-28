# Article Sequence Optimizer

**Contributors:** XWP
**Tags:** performance, infinite-scroll, inp, dom-optimization
**Requires at least:** 6.3
**Tested up to:** 6.8
**Requires PHP:** 8.1
**Stable tag:** 1.0.0

Optimizes infinite scroll performance by managing DOM nodes and visibility for articles.

## Description

The Article Sequence Optimizer improves the performance of long article sequences (infinite scroll) by intelligently managing the DOM. It uses a hybrid approach to reduce Interaction to Next Paint (INP) and memory usage:

1.  **CSS Content Visibility**: For articles containing iframes (like embeds), it uses `content-visibility: hidden` to prevent heavy reloads and flickering when scrolling back.
2.  **DOM Detachment**: For text/image-only articles, it detaches the content from the DOM and stores it in a memory fragment, significantly reducing the DOM size and style recalculation costs.

## Configuration

Go to **Settings → Article Sequence Optimizer** to configure the plugin:

*   **Container Selector**: The CSS selector for the **direct parent container** of your articles (default: `#articles-area`).
*   **Article Selector**: The CSS selector for the article elements that are **direct children** of the container (default: `article`).
*   **Safelist Iframes Selector**: Selectors for iframes that should remain visible (default: `.ad`).
*   **Buffer Distance**: The distance in pixels for pre-loading articles. Use a single value like `2500px` or specify all 4 directions (default: `2500px`).
*   **Max Cache Size**: Maximum number of articles to keep in memory (default: `50`).

## Installation

1.  Upload the plugin files to the `/wp-content/plugins/articles-sequence-optimizer` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to **Settings → Article Sequence Optimizer** to configure the selectors for your theme.

## Frequently Asked Questions

### Does this work with any theme?
Yes, as long as you configure the correct **Container Selector** and **Article Selector** in the settings.

### Will this break my dynamic embeds?
The plugin is designed to handle embeds safely. It uses CSS hiding for articles with iframes/embeds instead of detaching them, which prevents ads from reloading or losing their state. You can also use the **Safelist Iframes Selector** to exclude specific elements.

## Developer Hooks

### `article_sequence_optimizer_should_load`
Filters whether the optimizer script should be loaded.

**Parameters:**
*   `$should_load` (bool): Whether to load the script. Default is `true` only on single posts of type 'post'.

**Example:**
Enable on all post types (including pages and custom post types):
```php
add_filter( 'article_sequence_optimizer_should_load', function( $should_load ) {
    return is_singular();
} );
```
