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
//
// Renders the game inside an iframe pointing to game-frame.php.
// Isolation prevents CSS/JS conflicts with the WordPress theme.

add_shortcode( 'car_game', 'car_game_shortcode' );

function car_game_shortcode() {
	$frame_url = plugin_dir_url( __FILE__ ) . 'game-frame.php';

	// Responsive wrapper: full width, height auto-sized by the iframe via postMessage.
	$out  = '<div id="car-game-embed" style="width:100%;line-height:0;">';
	$out .= '<iframe id="car-game-iframe"';
	$out .= ' src="' . esc_url( $frame_url ) . '"';
	$out .= ' style="width:100%;height:750px;border:none;display:block;"';
	$out .= ' scrolling="no" allowfullscreen';
	$out .= '></iframe>';
	$out .= '</div>';

	// Resize iframe to match the game's actual height (game sends postMessage on resize)
	$out .= '<script>';
	$out .= 'window.addEventListener("message",function(e){';
	$out .= 'if(e.data&&e.data.type==="car-game-height"){';
	$out .= 'var f=document.getElementById("car-game-iframe");';
	$out .= 'if(f)f.style.height=e.data.height+"px";';
	$out .= '}});';
	$out .= '</script>';

	return $out;
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
