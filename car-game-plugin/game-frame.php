<?php
/**
 * Standalone game page – served inside an iframe by the [car_game] shortcode.
 * No WordPress bootstrap needed; self-calculates the plugin URL.
 */

// Calculate this file's public URL (used as base path for all assets)
$scheme     = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'];
$script_dir = rtrim( dirname( $_SERVER['PHP_SELF'] ), '/' ) . '/';
$plugin_url = $scheme . '://' . $host . $script_dir;

$html = file_get_contents( __DIR__ . '/index.html' );
if ( ! $html ) {
    http_response_code( 500 );
    echo 'index.html not found';
    exit;
}

// ── Fix static asset paths ──
$html = str_replace( 'src="images/', 'src="' . $plugin_url . 'images/', $html );
$html = str_replace( "url('images/", "url('" . $plugin_url . 'images/', $html );

// ── Inject base URL JS variable (for fetch + Phaser image loading) ──
$js_inject  = '<script>' . "\n";
$js_inject .= 'window.CAR_GAME_BASE_URL = ' . json_encode( $plugin_url ) . ';' . "\n";
$js_inject .= '</script>' . "\n";
$html = preg_replace( '/<script>/', $js_inject . '<script>', $html, 1 );

// ── Fix JS: fetch('levels.json') ──
$html = str_replace(
    "fetch('levels.json')",
    "fetch(window.CAR_GAME_BASE_URL+'levels.json')",
    $html
);

// ── Fix Phaser image loading ──
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
$html = str_replace(
    "this.load.image('turquoise', 'images/turqize.png')",
    "this.load.image('turquoise', window.CAR_GAME_BASE_URL+'images/turqize.png')",
    $html
);

// ── Fix solution image path ──
$html = str_replace(
    'img.src = levelData.solutionImage;',
    'img.src = (window.CAR_GAME_BASE_URL||"")+levelData.solutionImage;',
    $html
);

// ── Inject postMessage height reporter (for iframe auto-resize in WP) ──
$resize_js  = '<script>' . "\n";
$resize_js .= 'function reportHeight(){' . "\n";
$resize_js .= '  var h=document.body.scrollHeight;' . "\n";
$resize_js .= '  window.parent.postMessage({type:"car-game-height",height:h},"*");' . "\n";
$resize_js .= '}' . "\n";
$resize_js .= 'window.addEventListener("load",reportHeight);' . "\n";
$resize_js .= 'window.addEventListener("resize",reportHeight);' . "\n";
$resize_js .= 'new MutationObserver(reportHeight).observe(document.body,{childList:true,subtree:true,attributes:true});' . "\n";
$resize_js .= '</script>' . "\n";
$html = str_replace( '</body>', $resize_js . '</body>', $html );

header( 'Content-Type: text/html; charset=utf-8' );
echo $html;
