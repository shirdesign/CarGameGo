<?php
/**
 * Plugin Name: Car Game Go – משחק הצמתים
 * Description: משחק תנועה אינטראקטיבי. הוסיפו [car_game] בכל עמוד.
 * Version:     2.0
 * Author:      המפצחות
 * Text Domain: car-game
 */

defined( 'ABSPATH' ) || exit;

// ─── Shortcode: [car_game] ────────────────────────────────────────────────────

add_shortcode( 'car_game', 'car_game_shortcode' );

function car_game_shortcode() {
	$plugin_url  = plugin_dir_url( __FILE__ );
	$plugin_path = plugin_dir_path( __FILE__ );

	// ── Read source HTML ──
	$html = file_get_contents( $plugin_path . 'index.html' );
	if ( ! $html ) {
		return '<p style="color:red">Car Game: לא נמצא index.html בתיקיית הפלאגין.</p>';
	}

	// ── Strip outer document structure (keep only what goes inside <body>) ──
	$html = preg_replace( '/^.*?<body[^>]*>/s', '', $html );
	$html = preg_replace( '/<\/body>.*$/s', '', $html );

	// ── Remove file-protocol warning (never relevant inside WordPress) ──
	$html = preg_replace( '/<div id="file-protocol-msg">.*?<\/div>/s', '', $html );
	$html = preg_replace( '/if\s*\(window\.location\.protocol\s*===\s*[\'"]file:[\'"][^}]+\}/s', '', $html );

	// ── Neutralise game CSS rules that leak into the WP page ──
	// The game styles body/html as a flex centering wrapper; in WP that breaks the whole page.
	$reset_css  = '<style id="car-game-wp-compat">' . "\n";
	$reset_css .= 'body { display: block !important; background-color: revert !important;';
	$reset_css .= ' padding: revert !important; min-height: revert !important;';
	$reset_css .= ' flex-direction: unset !important; justify-content: unset !important;';
	$reset_css .= ' align-items: unset !important; touch-action: unset !important; }' . "\n";
	$reset_css .= 'html { overflow-x: revert !important; }' . "\n";
	$reset_css .= '</style>' . "\n";
	// Inject the reset immediately after the closing </style> of the game CSS block
	$html = preg_replace( '/<\/style>/', '</style>' . $reset_css, $html, 1 );

	// ── Fix static asset paths (HTML attributes) ──
	$html = str_replace( 'src="images/', 'src="' . esc_url( $plugin_url ) . 'images/', $html );

	// ── Fix CSS url() references for background images ──
	$html = str_replace( "url('images/", "url('" . esc_url( $plugin_url ) . 'images/', $html );

	// ── Inject JS variables before the first <script> tag ──
	$save_url   = esc_url( admin_url( 'admin-ajax.php' ) ) . '?action=car_game_save_levels';
	$js_inject  = '<script>' . "\n";
	$js_inject .= 'window.CAR_GAME_BASE_URL = ' . wp_json_encode( $plugin_url ) . ';' . "\n";
	$js_inject .= 'window.CAR_GAME_SAVE_URL = ' . wp_json_encode( $save_url ) . ';' . "\n";
	$js_inject .= '</script>' . "\n";
	$html = preg_replace( '/<script>/', $js_inject . '<script>', $html, 1 );

	// ── Fix JS: fetch('levels.json') ──
	$html = str_replace(
		"fetch('levels.json')",
		"fetch(window.CAR_GAME_BASE_URL+'levels.json')",
		$html
	);

	// ── Fix Phaser image loading (uses relative paths internally) ──
	$html = str_replace(
		"this.load.image(src.split('/').pop(), src)",
		"this.load.image(src.split('/').pop(), window.CAR_GAME_BASE_URL+src)",
		$html
	);
	$html = str_replace(
		"'images/background-junction.png', 'images/background-junction.png'",
		"'background-junction.png', window.CAR_GAME_BASE_URL+'images/background-junction.png'",
		$html
	);
	foreach ( [ 'red', 'blue', 'yellow', 'grey', 'orange', 'green' ] as $color ) {
		$html = str_replace(
			"this.load.image('{$color}',",
			"this.load.image('{$color}', window.CAR_GAME_BASE_URL+",
			$html
		);
	}
	// turquoise uses a typo filename
	$html = str_replace(
		"this.load.image('turquoise', 'images/turqize.png')",
		"this.load.image('turquoise', window.CAR_GAME_BASE_URL+'images/turqize.png')",
		$html
	);

	// ── Fix solution image path (shown after each level) ──
	$html = str_replace(
		'img.src = levelData.solutionImage;',
		'img.src = (window.CAR_GAME_BASE_URL||"")+levelData.solutionImage;',
		$html
	);

	return $html;
}

// ─── AJAX: save levels (admins only) ────────────────────────────────────────

add_action( 'wp_ajax_car_game_save_levels', 'car_game_save_levels' );

function car_game_save_levels() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( [ 'ok' => false, 'error' => 'Unauthorized' ], 403 );
	}

	$raw  = file_get_contents( 'php://input' );
	$data = json_decode( $raw, true );

	if ( ! is_array( $data ) ) {
		wp_send_json( [ 'ok' => false, 'error' => 'Invalid JSON or not an array' ], 400 );
	}

	$path = plugin_dir_path( __FILE__ ) . 'levels.json';
	$ok   = file_put_contents( $path, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

	if ( $ok === false ) {
		wp_send_json( [ 'ok' => false, 'error' => 'Could not write levels.json' ], 500 );
	}

	wp_send_json( [ 'ok' => true ] );
}

// ─── Admin page ──────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'car_game_admin_menu' );

function car_game_admin_menu() {
	add_menu_page(
		'Car Game – עריכת שלבים',
		'Car Game',
		'manage_options',
		'car-game-admin',
		'car_game_admin_page',
		'dashicons-games',
		30
	);
}

function car_game_admin_page() {
	$plugin_url  = plugin_dir_url( __FILE__ );
	$plugin_path = plugin_dir_path( __FILE__ );

	$html = file_get_contents( $plugin_path . 'admin.html' );
	if ( ! $html ) {
		echo '<p>לא נמצא admin.html</p>';
		return;
	}

	// Strip document structure
	$html = preg_replace( '/^.*?<body[^>]*>/s', '', $html );
	$html = preg_replace( '/<\/body>.*$/s', '', $html );

	// Remove conflicting <head> scripts/styles (already loaded by WP admin)
	$html = preg_replace( '/<link[^>]*>/i', '', $html );

	// Fix image paths
	$html = str_replace( 'src="images/', 'src="' . esc_url( $plugin_url ) . 'images/', $html );
	$html = str_replace( "url('images/", "url('" . esc_url( $plugin_url ) . 'images/', $html );

	// Inject JS variables for admin
	$save_url  = esc_url( admin_url( 'admin-ajax.php' ) ) . '?action=car_game_save_levels';
	$js_inject = '<script>' . "\n";
	$js_inject .= 'window.CAR_GAME_BASE_URL  = ' . wp_json_encode( $plugin_url ) . ';' . "\n";
	$js_inject .= 'window.CAR_GAME_SAVE_URL  = ' . wp_json_encode( $save_url ) . ';' . "\n";
	$js_inject .= 'window.CAR_GAME_SAVE_NONCE = ' . wp_json_encode( wp_create_nonce( 'car_game_save' ) ) . ';' . "\n";
	$js_inject .= '</script>' . "\n";
	$html = preg_replace( '/<script>/', $js_inject . '<script>', $html, 1 );

	// Replace admin save endpoints
	$html = str_replace(
		"fetch('levels.json')",
		"fetch(window.CAR_GAME_BASE_URL+'levels.json')",
		$html
	);
	$html = str_replace(
		"fetch('/save-levels',",
		"fetch(window.CAR_GAME_SAVE_URL,",
		$html
	);
	$html = str_replace(
		"fetch('save-levels.php',",
		"fetch(window.CAR_GAME_SAVE_URL,",
		$html
	);

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
