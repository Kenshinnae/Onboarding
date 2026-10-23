<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/inc/report-design.php';

$submission_id = get_queried_object_id();

if ( ! $submission_id || 'onboarding_submit' !== get_post_type( $submission_id ) ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	exit;
}

$get_value = static function ( $key ) use ( $submission_id ) {
	return get_post_meta( $submission_id, '_onboarding_' . $key, true );
};

$icon = 'co_report_icon';

$display_text = static function ( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'Yes' : 'No';
	}
	return trim( (string) $value );
};

$render_field = static function ( $label, $value, $wide = false ) use ( $display_text ) {
	$text = $display_text( $value );
	if ( '' === $text ) {
		return;
	}
	echo '<div class="brief-field' . ( $wide ? ' brief-field--wide' : '' ) . '">';
	echo '<dt>' . esc_html( $label ) . '</dt><dd>' . nl2br( esc_html( $text ) ) . '</dd></div>';
};

$render_chips = static function ( $label, $values, $wide = true ) {
	$values = array_values( array_filter( is_array( $values ) ? $values : array( $values ) ) );
	if ( ! $values ) {
		return;
	}
	echo '<div class="brief-field brief-field--chips' . ( $wide ? ' brief-field--wide' : '' ) . '"><dt>' . esc_html( $label ) . '</dt><dd>';
	foreach ( $values as $value ) {
		echo '<span class="brief-chip"><span>✓</span>' . esc_html( (string) $value ) . '</span>';
	}
	echo '</dd></div>';
};

$company = $get_value( 'company_name' );
$contact = $get_value( 'contact_name' );
$email   = $get_value( 'email' );
$phone   = $get_value( 'phone' );
$status  = $get_value( 'status' ) ?: 'new';
$status_labels = array( 'new' => 'New submission', 'reviewing' => 'Reviewing', 'completed' => 'Completed' );
$target_launch = $get_value( 'target_launch' );
$launch_display = $target_launch ? wp_date( 'j F Y', strtotime( $target_launch ) ) : 'No specific date';

get_header();
?>
<style><?php include CO_THEME_DIR . '/assets/brief-report.css'; include CO_THEME_DIR . '/assets/workspace-ui.css'; ?></style>
<main class="project-brief">
	<header class="brief-toolbar no-print">
		<div class="brief-brand"><span class="brief-brand__mark">CO</span><span>Website Project Onboarding</span></div>
		<div class="brief-toolbar__actions">
			<?php if ( current_user_can( 'edit_post', $submission_id ) ) : ?><a class="brief-button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=onboarding_submit' ) ); ?>">← All submissions</a><?php endif; ?>
			<button class="brief-button" type="button" onclick="navigator.clipboard.writeText(window.location.href);this.textContent='Link copied ✓';">Copy link</button>
			<button class="brief-button" type="button" onclick="window.print()">Print brief</button>
			<?php if ( current_user_can( 'edit_post', $submission_id ) ) : ?><a class="brief-button brief-button--primary" href="<?php echo esc_url( get_edit_post_link( $submission_id, 'raw' ) ); ?>">Edit submission</a><?php endif; ?>
		</div>
	</header>

	<section class="brief-hero">
		<div class="brief-hero__inner">
			<div><p class="brief-kicker">Website project brief</p><h1><?php echo esc_html( $company ); ?></h1><div class="brief-hero__meta"><span><?php echo esc_html( $contact ); ?></span><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php if ( $phone ) : ?><span><?php echo esc_html( $phone ); ?></span><?php endif; ?></div></div>
			<span class="brief-status"><?php echo esc_html( $status_labels[ $status ] ?? ucfirst( $status ) ); ?></span>
		</div>
	</section>

	<section class="brief-summary">
		<div class="brief-summary__item"><small>Submitted</small><strong><?php echo esc_html( get_the_date( 'j F Y', $submission_id ) ); ?></strong></div>
		<div class="brief-summary__item"><small>Target launch</small><strong><?php echo esc_html( $launch_display ); ?></strong></div>
		<div class="brief-summary__item"><small>Primary action</small><strong><?php echo esc_html( $get_value( 'primary_action' ) ?: 'Not specified' ); ?></strong></div>
	</section>

	<div class="brief-layout">
		<section class="brief-card brief-card--wide"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'business' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><div><h2>Business overview</h2><p>Who the client is and what they offer</p></div></header><dl class="brief-fields"><?php $render_field( 'Company', $company ); $render_field( 'Contact', $contact ); $render_field( 'Email', $email ); $render_field( 'Phone', $phone ); $render_field( 'What the business does', $get_value( 'business_description' ), true ); $render_field( 'Products and services', $get_value( 'services' ), true ); $render_field( 'Existing website', $get_value( 'website_url' ), true ); ?></dl></section>

		<section class="brief-card"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'website' ); // phpcs:ignore ?></span><div><h2>Website goals</h2><p>Purpose, outcome and language scope</p></div></header><dl class="brief-fields"><?php $render_chips( 'Website purpose', $get_value( 'website_type' ) ); $render_field( 'Other purpose', $get_value( 'website_type_other' ), true ); $render_field( 'Primary action', $get_value( 'primary_action' ), true ); $render_field( 'Number of languages', $get_value( 'language_count' ) ); $render_chips( 'Languages', $get_value( 'languages' ) ); $render_field( 'Custom action', $get_value( 'primary_action_other' ), true ); ?></dl></section>

		<section class="brief-card"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'pages' ); // phpcs:ignore ?></span><div><h2>Website pages</h2><p>Planned site structure</p></div></header><dl class="brief-fields"><?php $render_chips( 'Selected pages', $get_value( 'pages' ) ); $render_chips( 'Custom pages', $get_value( 'custom_pages' ) ); $sitemap_id = absint( $get_value( 'sitemap_file' ) ); if ( $sitemap_id ) : ?><div class="brief-field brief-field--wide"><dt>Existing sitemap</dt><dd><a class="brief-reference" href="<?php echo esc_url( wp_get_attachment_url( $sitemap_id ) ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( get_the_title( $sitemap_id ) ); ?></strong><span>View or download sitemap ↗</span></a></dd></div><?php endif; ?></dl></section>

		<section class="brief-card"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'content' ); // phpcs:ignore ?></span><div><h2>Content &amp; assets</h2><p>Available brand materials</p></div></header><dl class="brief-fields"><?php $render_field( 'Content status', $get_value( 'content_status' ), true ); $render_chips( 'Available assets', $get_value( 'assets' ) ); $render_field( 'Other assets', $get_value( 'assets_other' ), true ); ?></dl></section>

		<section class="brief-card"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'features' ); // phpcs:ignore ?></span><div><h2>Features</h2><p>Required website functionality</p></div></header><dl class="brief-fields"><?php $render_chips( 'Selected features', $get_value( 'features' ) ); $render_field( 'Other feature', $get_value( 'features_other' ), true ); ?></dl></section>

		<section class="brief-card"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'hosting' ); // phpcs:ignore ?></span><div><h2>Domain &amp; hosting</h2><p>Technical setup requirements</p></div></header><dl class="brief-fields"><?php $render_field( 'Domain status', $get_value( 'domain_status' ) ); $render_field( 'Domain', $get_value( 'domain' ) ); $render_field( 'Hosting status', $get_value( 'hosting_status' ) ); $render_field( 'Hosting choice', $get_value( 'hosting_choice' ) ); ?><div class="brief-hosting-note">First-year hosting is included. Renewal starts from 1,500 THB/year for sites using up to 5GB.</div></dl></section>

		<section class="brief-card"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'timeline' ); // phpcs:ignore ?></span><div><h2>Timeline &amp; notes</h2><p>Delivery expectations and context</p></div></header><dl class="brief-fields"><?php $render_field( 'Target launch', $launch_display, true ); $render_field( 'Additional information', $get_value( 'notes' ), true ); ?></dl></section>

		<?php $references = $get_value( 'references' ); if ( $references ) : ?><section class="brief-card"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'website' ); // phpcs:ignore ?></span><div><h2>Website references</h2><p>Visual and experience inspiration</p></div></header><div class="brief-references"><?php foreach ( $references as $reference ) : ?><a class="brief-reference" href="<?php echo esc_url( $reference['url'] ?? '' ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( $reference['url'] ?? '' ); ?></strong><?php if ( ! empty( $reference['notes'] ) ) : ?><span><?php echo esc_html( $reference['notes'] ); ?></span><?php endif; ?></a><?php endforeach; ?></div></section><?php endif; ?>

		<section class="brief-card brief-card--wide"><header class="brief-card__head"><span class="brief-card__icon"><?php echo $icon( 'content' ); // phpcs:ignore ?></span><div><h2>Uploaded files</h2><p>Brand assets supplied with this submission</p></div></header><?php $files = $get_value( 'uploaded_files' ); if ( $files ) : ?><ul class="brief-files"><?php foreach ( $files as $file_id ) : ?><li class="brief-file"><a href="<?php echo esc_url( wp_get_attachment_url( $file_id ) ); ?>" target="_blank"><strong><?php echo esc_html( get_the_title( $file_id ) ); ?></strong><span>VIEW / DOWNLOAD ↗</span></a></li><?php endforeach; ?></ul><?php else : ?><p class="brief-empty">No files were uploaded with this submission.</p><?php endif; ?></section>
	</div>
	<footer class="brief-footer">Submission #<?php echo absint( $submission_id ); ?> · Generated from Website Project Onboarding</footer>
</main>
<?php get_footer();
