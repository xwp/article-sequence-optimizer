<?php
/**
 * Plugin Name: Article Sequence Optimizer
 * Plugin URI: https://github.com/xwp/article-sequence-optimizer
 * Description: Optimizes infinite scroll performance by managing DOM nodes and visibility for articles.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Requires at least: 6.3
 * Author: XWP
 * Author URI: https://xwp.co/
 *
 * @package ArticleSequenceOptimizer
 */

namespace ArticleSequenceOptimizer;

const MAIN_DIR = __DIR__;
const VERSION  = '1.0.0';

// Include admin settings.
require_once MAIN_DIR . '/includes/admin-settings.php';

// Register hooks.
add_action( 'admin_menu', __NAMESPACE__ . '\register_settings_page' );
add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_script' );
add_action( 'wp_footer', __NAMESPACE__ . '\output_inline_styles' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\add_settings_link' );

/**
 * Add "Settings" link to plugin action links.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function add_settings_link( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=article-sequence-optimizer' ) ),
		esc_html__( 'Settings', 'article-sequence-optimizer' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}

/**
 * Enqueue the optimizer script.
 *
 * @return void
 */
function enqueue_script() {
	// Check if we should load the script.
	// Default: Only on single 'post' types (standard articles).
	$should_load = is_singular( 'post' );

	/**
	 * Filter to allow developers to modify the condition for loading the script.
	 *
	 * @param bool $should_load Whether to load the script.
	 */
	if ( ! apply_filters( 'article_sequence_optimizer_should_load', $should_load ) ) {
		return;
	}

	// Only enqueue if we have settings or defaults.
	$settings = get_settings();

	wp_register_script(
		'article-sequence-optimizer',
		plugin_dir_url( __FILE__ ) . 'assets/js/article-sequence-optimizer.js',
		[],
		VERSION,
		true
	);
	wp_script_add_data( 'article-sequence-optimizer', 'strategy', 'defer' );

	// Pass configuration to JS.
	$buffer_distance = $settings['buffer_distance'];
	
	// Helper: If buffer_distance is a single value, expand it to 4 directions.
	// E.g., "2500px" becomes "2500px 0px 2500px 0px" (top right bottom left).
	$parts = explode( ' ', trim($buffer_distance) );
	if ( count( $parts ) === 1 ) {
		$buffer_distance = "{$buffer_distance} 0px {$buffer_distance} 0px";
	}

	$config = [
		'containerSelector' => $settings['container_selector'],
		'articleSelector'   => $settings['article_selector'],
		'safelistIframes'   => $settings['safelist_iframes'],
		'bufferDistance'    => $buffer_distance,
		'maxCacheSize'      => (int) $settings['max_cache_size'],
	];

	wp_add_inline_script(
		'article-sequence-optimizer',
		'window.articleSequenceOptimizerConfig = ' . wp_json_encode( $config ) . ';',
		'before'
	);

	wp_enqueue_script( 'article-sequence-optimizer' );
}

/**
 * Output inline styles for optimizer classes in footer.
 *
 * @return void
 */
function output_inline_styles() {
	// Check if we should load the styles (same condition as script).
	$should_load = is_singular( 'post' );

	/**
	 * Filter to allow developers to modify the condition for loading the styles.
	 *
	 * @param bool $should_load Whether to load the styles.
	 */
	if ( ! apply_filters( 'article_sequence_optimizer_should_load', $should_load ) ) {
		return;
	}

	?>
	<style id="article-sequence-optimizer-styles">
		.inp-article-offloaded{min-height:var(--inp-article-height)}.inp-article-visibility-optimized{content-visibility:auto;contain-intrinsic-size:auto var(--inp-article-height)}
	</style>
	<?php
}
