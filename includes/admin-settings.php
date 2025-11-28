<?php
/**
 * Admin Settings Page for Article Sequence Optimizer
 *
 * Implements WordPress Settings API for plugin configuration.
 *
 * @package ArticleSequenceOptimizer
 */

namespace ArticleSequenceOptimizer;

/**
 * Register the settings page in WordPress admin menu.
 *
 * @return void
 */
function register_settings_page() {
	add_options_page(
		__( 'Article Sequence Optimizer Settings', 'article-sequence-optimizer' ),
		__( 'Article Sequence Optimizer', 'article-sequence-optimizer' ),
		'manage_options',
		'article-sequence-optimizer',
		__NAMESPACE__ . '\render_settings_page'
	);
}

/**
 * Register plugin settings using WordPress Settings API.
 *
 * @return void
 */
function register_settings() {
	register_setting(
		'article_sequence_optimizer_settings',
		'article_sequence_optimizer_settings',
		[
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
		]
	);

	add_settings_section(
		'article_sequence_optimizer_main',
		__( 'Optimizer Configuration', 'article-sequence-optimizer' ),
		__NAMESPACE__ . '\render_settings_section',
		'article-sequence-optimizer'
	);

	add_settings_field(
		'container_selector',
		__( 'Container Selector', 'article-sequence-optimizer' ),
		__NAMESPACE__ . '\render_text_field',
		'article-sequence-optimizer',
		'article_sequence_optimizer_main',
		[
			'id'          => 'container_selector',
			'description' => __( 'CSS selector for the direct parent container of the posts sequence. Default: #articles-area', 'article-sequence-optimizer' ),
		]
	);

	add_settings_field(
		'article_selector',
		__( 'Article Selector', 'article-sequence-optimizer' ),
		__NAMESPACE__ . '\render_text_field',
		'article-sequence-optimizer',
		'article_sequence_optimizer_main',
		[
			'id'          => 'article_selector',
			'description' => __( 'CSS selector for the individual post wrappers that are direct children of the container. Default: article', 'article-sequence-optimizer' ),
		]
	);

	add_settings_field(
		'safelist_iframes',
		__( 'Safelist Iframes Selector', 'article-sequence-optimizer' ),
		__NAMESPACE__ . '\render_text_field',
		'article-sequence-optimizer',
		'article_sequence_optimizer_main',
		[
			'id'          => 'safelist_iframes',
			'description' => __( 'CSS selector for iframes that can be detached from the DOM and be reinitialized when the article is again in viewport (e.g. ads). Default: .ad', 'article-sequence-optimizer' ),
		]
	);

	add_settings_field(
		'buffer_distance',
		__( 'Buffer Distance', 'article-sequence-optimizer' ),
		__NAMESPACE__ . '\render_text_field',
		'article-sequence-optimizer',
		'article_sequence_optimizer_main',
		[
			'id'          => 'buffer_distance',
			'description' => __( 'Distance in pixels for pre-loading articles. Use a single value (e.g., 2500px) or specify all 4 directions (top right bottom left). Default: 2500px', 'article-sequence-optimizer' ),
		]
	);

	add_settings_field(
		'max_cache_size',
		__( 'Max Cache Size', 'article-sequence-optimizer' ),
		__NAMESPACE__ . '\render_number_field',
		'article-sequence-optimizer',
		'article_sequence_optimizer_main',
		[
			'id'          => 'max_cache_size',
			'description' => __( 'Maximum number of offloaded articles to keep in memory. Default: 50', 'article-sequence-optimizer' ),
		]
	);
}

/**
 * Sanitize all plugin settings.
 *
 * @param array $input Raw input from settings form.
 * @return array Sanitized settings.
 */
function sanitize_settings( $input ) {
	if ( ! is_array( $input ) ) {
		$input = [];
	}

	$sanitized = [];

	$text_fields = [
		'container_selector',
		'article_selector',
		'safelist_iframes',
		'buffer_distance',
	];

	foreach ( $text_fields as $field ) {
		if ( isset( $input[ $field ] ) ) {
			$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
		} else {
			$sanitized[ $field ] = '';
		}
	}

	if ( isset( $input['max_cache_size'] ) ) {
		$sanitized['max_cache_size'] = absint( $input['max_cache_size'] );
	} else {
		$sanitized['max_cache_size'] = 50;
	}

	return $sanitized;
}

/**
 * Get plugin settings with defaults.
 *
 * @return array Plugin settings.
 */
function get_settings() {
	$defaults = [
		'container_selector' => '#articles-area',
		'article_selector'   => 'article',
		'safelist_iframes'   => '.ad',
		'buffer_distance'    => '2500px',
		'max_cache_size'     => 50,
	];

	$settings = get_option( 'article_sequence_optimizer_settings', [] );

	return wp_parse_args( $settings, $defaults );
}

/**
 * Render the main settings page.
 *
 * @return void
 */
function render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Check if settings were saved.
	if ( isset( $_GET['settings-updated'] ) ) {
		add_settings_error(
			'article_sequence_optimizer_messages',
			'article_sequence_optimizer_message',
			__( 'Settings Saved', 'article-sequence-optimizer' ),
			'updated'
		);
	}

	settings_errors( 'article_sequence_optimizer_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'article_sequence_optimizer_settings' );
			do_settings_sections( 'article-sequence-optimizer' );
			submit_button( __( 'Save Settings', 'article-sequence-optimizer' ) );
			?>
		</form>
	</div>
	<?php
}

/**
 * Render the settings section description.
 *
 * @return void
 */
function render_settings_section() {
	?>
	<p>
		<?php
		esc_html_e(
			'Configure the behavior of the Article Sequence Optimizer for INP.',
			'article-sequence-optimizer'
		);
		?>
	</p>
	<?php
}

/**
 * Render a standard text field.
 *
 * @param array $args Field arguments including 'id' and 'description'.
 * @return void
 */
function render_text_field( $args ) {
	$settings = get_settings();
	$id       = $args['id'];
	$value    = $settings[ $id ];
	?>
	<input
		type="text"
		id="<?php echo esc_attr( $id ); ?>"
		name="article_sequence_optimizer_settings[<?php echo esc_attr( $id ); ?>]"
		value="<?php echo esc_attr( $value ); ?>"
		class="regular-text"
	/>
	<p class="description">
		<?php echo esc_html( $args['description'] ); ?>
	</p>
	<?php
}

/**
 * Render a number field.
 *
 * @param array $args Field arguments including 'id' and 'description'.
 * @return void
 */
function render_number_field( $args ) {
	$settings = get_settings();
	$id       = $args['id'];
	$value    = $settings[ $id ];
	?>
	<input
		type="number"
		id="<?php echo esc_attr( $id ); ?>"
		name="article_sequence_optimizer_settings[<?php echo esc_attr( $id ); ?>]"
		value="<?php echo esc_attr( $value ); ?>"
		min="1"
		step="1"
		class="small-text"
	/>
	<p class="description">
		<?php echo esc_html( $args['description'] ); ?>
	</p>
	<?php
}
