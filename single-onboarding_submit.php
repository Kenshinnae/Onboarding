<?php
defined( 'ABSPATH' ) || exit;

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

$icons = array(
	'business' => '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-3h4v3"/>',
	'website'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.3 2.5 3.5 5.5 3.5 9S14.3 18.5 12 21M12 3c-2.3 2.5-3.5 5.5-3.5 9s1.2 6.5 3.5 9"/>',
	'pages'    => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
	'content'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 15-4.5-4.5L8 19"/>',
	'features' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="4"/>',
	'hosting'  => '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01M11 7h6M11 17h6"/>',
	'timeline' => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16M8 15l2 2 5-5"/>',
);

$icon = static function ( $name ) use ( $icons ) {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $icons[ $name ] ?? $icons['pages'] ) . '</svg>';
};

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
<style>
@font-face{font-family:Manrope;src:url('/wp-content/themes/twentytwentyfive/assets/fonts/manrope/Manrope-VariableFont_wght.woff2') format('woff2');font-weight:200 800;font-display:swap}
:root{--brief-ink:#101b35;--brief-muted:#68758e;--brief-blue:#255cc7;--brief-blue-dark:#173f91;--brief-pale:#edf4ff;--brief-line:#dce5f1;--brief-bg:#eef4fd}
body.single-onboarding_submit{margin:0;background:var(--brief-bg);color:var(--brief-ink);font-family:Manrope,system-ui,sans-serif}
.project-brief *{box-sizing:border-box}.project-brief{min-height:100vh;padding-bottom:64px}.brief-toolbar{height:68px;background:#fff;border-bottom:1px solid var(--brief-line);display:flex;align-items:center;justify-content:space-between;padding:0 max(24px,calc((100vw - 1120px)/2))}.brief-brand{display:flex;align-items:center;gap:11px;font-weight:750}.brief-brand__mark{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:var(--brief-blue);color:#fff;font-size:13px;box-shadow:0 7px 16px rgba(37,92,199,.2)}.brief-toolbar__actions{display:flex;gap:8px}.brief-button{appearance:none;border:1px solid var(--brief-line);border-radius:8px;padding:9px 13px;background:#fff;color:#35445c;font:700 12px Manrope,sans-serif;text-decoration:none;cursor:pointer}.brief-button--primary{background:var(--brief-blue);border-color:var(--brief-blue);color:#fff}.brief-hero{background:linear-gradient(125deg,#163b87 0%,#255cc7 70%,#3c76df 100%);color:#fff;padding:48px 24px 88px}.brief-hero__inner{width:min(1080px,100%);margin:auto;display:flex;justify-content:space-between;align-items:flex-start;gap:28px}.brief-kicker{margin:0 0 10px;font-size:11px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#cfe0ff}.brief-hero h1{margin:0 0 12px;font-size:clamp(34px,5vw,52px);line-height:1.05;letter-spacing:-.045em}.brief-hero__meta{display:flex;flex-wrap:wrap;gap:8px 22px;color:#dbe7ff;font-size:14px}.brief-hero__meta a{color:#fff;text-decoration:none}.brief-status{display:inline-flex;align-items:center;gap:8px;flex:0 0 auto;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.12);backdrop-filter:blur(8px);padding:8px 12px;border-radius:999px;font-size:12px;font-weight:750}.brief-status:before{content:"";width:7px;height:7px;border-radius:50%;background:#83e6b5;box-shadow:0 0 0 4px rgba(131,230,181,.14)}.brief-summary{width:min(1080px,calc(100% - 32px));margin:-46px auto 18px;position:relative;display:grid;grid-template-columns:repeat(3,1fr);background:#fff;border:1px solid rgba(41,75,127,.08);border-radius:16px;box-shadow:0 12px 32px rgba(28,55,96,.13)}.brief-summary__item{padding:19px 22px}.brief-summary__item+div{border-left:1px solid var(--brief-line)}.brief-summary small{display:block;color:var(--brief-muted);font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;margin-bottom:5px}.brief-summary strong{font-size:14px}.brief-layout{width:min(1080px,calc(100% - 32px));margin:auto;display:grid;grid-template-columns:1fr 1fr;gap:16px}.brief-card{background:#fff;border:1px solid rgba(60,88,130,.1);border-radius:14px;padding:22px;box-shadow:0 5px 18px rgba(28,55,96,.055);break-inside:avoid}.brief-card--wide{grid-column:1/-1}.brief-card__head{display:flex;align-items:center;gap:11px;padding-bottom:15px;margin-bottom:17px;border-bottom:1px solid var(--brief-line)}.brief-card__icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:var(--brief-pale);color:var(--brief-blue)}.brief-card__icon svg{width:20px;height:20px}.brief-card h2{margin:0;font-size:16px;letter-spacing:-.01em}.brief-card__head p{margin:2px 0 0;color:var(--brief-muted);font-size:11px}.brief-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px 24px;margin:0}.brief-field--wide{grid-column:1/-1}.brief-field dt{color:#7a879d;font-size:10px;font-weight:800;letter-spacing:.075em;text-transform:uppercase;margin-bottom:4px}.brief-field dd{margin:0;color:#26344b;font-size:13px;line-height:1.55}.brief-field--chips dd{display:flex;flex-wrap:wrap;gap:7px}.brief-chip{display:inline-flex;align-items:center;gap:5px;background:#f2f6fc;border:1px solid #dfe7f2;border-radius:999px;padding:6px 9px;color:#43526a;font-size:11px;font-weight:650}.brief-chip span{color:var(--brief-blue)}.brief-references{display:grid;gap:9px}.brief-reference{display:block;padding:12px 13px;border:1px solid var(--brief-line);border-radius:9px;text-decoration:none;color:var(--brief-ink);background:#fafcff}.brief-reference strong{display:block;color:var(--brief-blue);font-size:12px;overflow-wrap:anywhere}.brief-reference span{display:block;color:var(--brief-muted);font-size:11px;margin-top:3px}.brief-hosting-note{grid-column:1/-1;padding:12px 14px;border-radius:9px;background:#eef8ff;border:1px solid #d3e8f8;color:#315676;font-size:11px}.brief-files{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:1fr 1fr;gap:9px}.brief-file a{display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid var(--brief-line);border-radius:9px;padding:12px;color:var(--brief-ink);text-decoration:none;font-size:12px;font-weight:700}.brief-file a span{color:var(--brief-blue);font-size:10px}.brief-empty{color:var(--brief-muted);font-size:12px;margin:0}.brief-footer{width:min(1080px,calc(100% - 32px));margin:22px auto 0;text-align:center;color:#8490a4;font-size:10px}
@media(max-width:720px){.brief-toolbar{padding:0 15px}.brief-brand span:last-child{display:none}.brief-button:not(.brief-button--primary){display:none}.brief-hero{padding:35px 18px 72px}.brief-hero__inner{display:block}.brief-status{margin-top:20px}.brief-summary{grid-template-columns:1fr;margin-top:-38px}.brief-summary__item{padding:13px 16px}.brief-summary__item+div{border-left:0;border-top:1px solid var(--brief-line)}.brief-layout{grid-template-columns:1fr}.brief-card--wide{grid-column:auto}.brief-card{padding:18px}.brief-fields{grid-template-columns:1fr;gap:13px}.brief-field--wide{grid-column:auto}.brief-hosting-note{grid-column:auto}.brief-files{grid-template-columns:1fr}}
@media print{#wpadminbar,.brief-toolbar,.brief-button{display:none!important}html{margin-top:0!important}body.single-onboarding_submit{background:#fff}.project-brief{padding:0}.brief-hero{background:#fff!important;color:var(--brief-ink);padding:20px 0;border-bottom:3px solid var(--brief-blue)}.brief-hero__inner,.brief-layout,.brief-summary,.brief-footer{width:100%}.brief-kicker,.brief-hero__meta,.brief-hero__meta a{color:var(--brief-muted)}.brief-status{border:1px solid var(--brief-line);background:#fff;color:var(--brief-ink)}.brief-summary{margin:12px 0;box-shadow:none}.brief-layout{gap:10px}.brief-card{box-shadow:none;padding:15px}.brief-footer{margin-top:12px}}
</style>
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
