<?php
defined( 'ABSPATH' ) || exit;

/** Shared line icons used by the original Brief and the customer Project report. */
function co_report_icon( $name ) {
	$icons = array(
		'business' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/>',
		'website' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.3 2.5 3.5 5.5 3.5 9S14.3 18.5 12 21M12 3c-2.3 2.5-3.5 5.5-3.5 9s1.2 6.5 3.5 9"/>',
		'pages' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
		'content' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 15-4.5-4.5L8 19"/>',
		'features' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="4"/>',
		'hosting' => '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01M11 7h6M11 17h6"/>',
		'timeline' => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16M8 15l2 2 5-5"/>',
		'payment' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3M15 15h2"/>',
		'maintenance' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
		'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
		'arrow' => '<path d="M7 17 17 7M7 7h10v10"/>',
	);
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $icons[$name] ?? $icons['pages'] ) . '</svg>';
}

function co_report_card_header( $icon, $title, $description ) {
	echo '<header class="brief-card__head"><span class="brief-card__icon">' . co_report_icon( $icon ) . '</span><div><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $description ) . '</p></div></header>';
}

function co_report_display_field( $label, $value, $wide = false, $chips = false ) {
	if ( is_bool( $value ) ) $value = $value ? 'Yes' : 'No';
	if ( is_array( $value ) ) $value = array_values( array_filter( $value, fn( $item ) => is_scalar( $item ) && '' !== trim( (string) $item ) ) );
	if ( '' === trim( is_array( $value ) ? implode( '', $value ) : (string) $value ) ) return;
	echo '<div class="brief-field' . ( $wide ? ' brief-field--wide' : '' ) . ( $chips ? ' brief-field--chips' : '' ) . '"><dt>' . esc_html( $label ) . '</dt><dd>';
	if ( $chips ) foreach ( (array) $value as $item ) echo '<span class="brief-chip"><span>✓</span>' . esc_html( $item ) . '</span>';
	else echo nl2br( esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ) );
	echo '</dd></div>';
}
