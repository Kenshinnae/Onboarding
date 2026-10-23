<?php
defined( 'ABSPATH' ) || exit;

function co_crm_customer_brief_cards( $submission, $assets ) {
	$linked = 'onboarding_submit' === get_post_type( $submission ) && 'trash' !== get_post_status( $submission );
	$groups = array(
		array( 'website', 'Website goals', 'Purpose, outcomes and language scope', array( 'website_type' => 'Website purpose', 'website_type_other' => 'Other purpose', 'primary_action' => 'Primary action', 'primary_action_other' => 'Custom action', 'language_count' => 'Number of languages', 'languages' => 'Languages' ) ),
		array( 'pages', 'Website pages', 'Planned site structure and additional pages', array( 'pages' => 'Selected pages', 'custom_pages' => 'Custom pages' ) ),
		array( 'content', 'Content & assets', 'Brand materials supplied for this project', array( 'content_status' => 'Content status', 'assets' => 'Available assets', 'assets_other' => 'Other assets' ) ),
		array( 'features', 'Features', 'Required website functionality', array( 'features' => 'Selected features', 'features_other' => 'Other features' ) ),
		array( 'hosting', 'Domain & hosting', 'Technical setup requirements', array( 'domain_status' => 'Domain status', 'domain' => 'Domain', 'hosting_status' => 'Hosting status', 'hosting_choice' => 'Hosting choice' ) ),
		array( 'timeline', 'Timeline & notes', 'Delivery expectations shared in the brief', array( 'target_launch' => 'Target launch', 'no_date' => 'No specific launch date', 'notes' => 'Additional information' ) ),
	);
	echo '<div class="customer-brief-sections" id="customer-brief">';
	if ( $linked ) {
		foreach ( $groups as $group ) {
			$values = array(); foreach ( $group[3] as $key => $label ) { $value = get_post_meta( $submission, '_onboarding_' . $key, true ); if ( '' !== $value && array() !== $value ) $values[$key] = $value; }
			if ( ! $values ) continue;
			echo '<section class="brief-card">'; co_report_card_header( $group[0], $group[1], $group[2] ); echo '<dl class="brief-fields">';
			foreach ( $values as $key => $value ) {
				if ( 'target_launch' === $key ) $value = co_sales_date( $value );
				if ( 'no_date' === $key ) $value = $value ? 'Yes' : 'No';
				co_report_display_field( $group[3][$key], $value, true, is_array( $value ) );
			}
			echo '</dl></section>';
		}
		$references = (array) get_post_meta( $submission, '_onboarding_references', true );
		if ( $references ) {
			echo '<section class="brief-card">'; co_report_card_header( 'website', 'Website references', 'Visual and experience inspiration' ); echo '<div class="brief-references">';
			foreach ( $references as $reference ) if ( is_array( $reference ) && ! empty( $reference['url'] ) ) echo '<a class="brief-reference" target="_blank" rel="noopener noreferrer" href="' . esc_url( $reference['url'] ) . '"><strong>' . esc_html( $reference['url'] ) . '</strong><span>' . nl2br( esc_html( $reference['notes'] ?? '' ) ) . '</span></a>';
			echo '</div></section>';
		}
	}
	echo '<section class="brief-card brief-card--wide">'; co_report_card_header( 'file', 'Project files', 'Files supplied during onboarding and shared by your project team' );
	echo '<div class="customer-assets">';
	foreach ( $assets as $asset => $source ) {
		$url = wp_get_attachment_url( $asset ); if ( ! $url ) continue;
		echo '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( $url ) . '"><span class="customer-file-icon">' . co_report_icon( 'file' ) . '</span><span><strong>' . esc_html( get_the_title( $asset ) ) . '</strong><small>View / download file</small></span>' . co_report_icon( 'arrow' ) . '</a>';
	}
	if ( ! $assets ) echo '<p class="customer-empty">No files have been shared with this report yet.</p>';
	echo '</div></section></div>';
}

function co_crm_customer_report_view( $id, $data, $token ) {
	$project = get_post( $id ); $finance = $data['finance']; $stage = co_crm_project_stage( $id, false );
	$submission = absint( co_crm_meta( $id, 'onboarding_id' ) );
	$onboarding = 'onboarding_submit' === get_post_type( $submission ) && 'trash' !== get_post_status( $submission );
	$get = fn( $key ) => $onboarding ? get_post_meta( $submission, '_onboarding_' . $key, true ) : '';
	$company = co_crm_meta( $id, 'company' ) ?: $get( 'company_name' ); $contact = co_crm_meta( $id, 'contact_name' ) ?: $get( 'contact_name' ); $email = co_crm_meta( $id, 'contact_email' ) ?: $get( 'email' ); $phone = $get( 'phone' );
	?>
	<main class="project-brief project-customer-report">
	<header class="brief-toolbar"><div class="brief-brand"><span class="brief-brand__mark">CO</span><span>Client project workspace</span></div><span class="customer-private-label"><?php echo co_report_icon( 'maintenance' ); ?>Private project report</span></header>
	<section class="brief-hero"><div class="brief-hero__inner"><div><p class="brief-kicker">CLIENT PROJECT REPORT</p><h1><?php echo esc_html( $project->post_title ); ?></h1><div class="brief-hero__meta"><?php if ( $company !== $project->post_title ) : ?><span><?php echo esc_html( $company ); ?></span><?php endif; ?><?php if ( $contact ) : ?><span><?php echo esc_html( $contact ); ?></span><?php endif; ?><?php if ( $email ) : ?><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a><?php endif; ?><?php if ( $phone ) : ?><span><?php echo esc_html( $phone ); ?></span><?php endif; ?></div></div><span class="brief-status"><?php echo esc_html( co_crm_stages()[$stage] ); ?></span></div></section>
	<section class="brief-summary customer-finance-summary" aria-label="Project financial summary"><?php foreach ( array( 'Project value' => $finance['value'], 'Net after withholding' => $finance['net'], 'Payments received' => $finance['received'], 'Outstanding balance' => $finance['outstanding'] ) as $label => $value ) : ?><div class="brief-summary__item"><small><?php echo esc_html( $label ); ?></small><strong>฿<?php echo esc_html( co_sales_money( $value ) ); ?></strong></div><?php endforeach; ?></section>
	<nav class="customer-report-nav" aria-label="Report sections"><?php foreach ( array( 'customer-progress' => array( 'business', 'Overview' ), 'customer-brief' => array( 'content', 'Brief & files' ), 'customer-documents' => array( 'pages', 'Documents' ), 'customer-ma' => array( 'maintenance', 'Maintenance' ) ) as $anchor => $item ) : ?><a href="#<?php echo esc_attr( $anchor ); ?>"><?php echo co_report_icon( $item[0] ); ?><?php echo esc_html( $item[1] ); ?></a><?php endforeach; ?></nav>
	<div class="brief-layout">
	<section class="brief-card brief-card--wide" id="customer-progress"><?php co_report_card_header( 'business', 'Business overview', 'Who the client is and what the project is about' ); ?><dl class="brief-fields"><?php co_report_display_field( 'Company', $company ?: '—' ); co_report_display_field( 'Contact', $contact ?: '—' ); co_report_display_field( 'Email', $email ?: '—' ); co_report_display_field( 'Phone', $phone ?: '—' ); co_report_display_field( 'What the business does', $get( 'business_description' ), true ); co_report_display_field( 'Products and services', $get( 'services' ), true ); co_report_display_field( 'Existing website', $get( 'website_url' ) ?: $get( 'existing_website' ), true ); co_report_display_field( 'Start date', co_sales_date( co_crm_meta( $id, 'start_date' ) ) ); co_report_display_field( 'Project deadline', co_sales_date( co_crm_meta( $id, 'deadline' ) ) ); ?></dl><div class="customer-project-progress"><div class="customer-subheading"><span class="customer-inline-icon"><?php echo co_report_icon( 'timeline' ); ?></span><h3>Project progress</h3></div><ol class="customer-project-stages"><?php foreach ( co_crm_stages() as $key => $label ) : ?><li <?php echo $key === $stage ? 'class="current" aria-current="step"' : ''; ?>><?php echo esc_html( $label ); ?></li><?php endforeach; ?></ol><?php if ( co_crm_meta( $id, 'timeline_url' ) ) : ?><a class="customer-button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( co_crm_meta( $id, 'timeline_url' ) ); ?>"><?php echo co_report_icon( 'timeline' ); ?>Open Google Sheet timeline<?php echo co_report_icon( 'arrow' ); ?></a><?php endif; ?></div></section>
	<?php co_crm_customer_brief_cards( $submission, co_crm_customer_assets( $id ) ); ?>
	<section class="brief-card brief-card--wide" id="customer-documents">
		<?php co_report_card_header( 'pages', 'Documents & billing', 'Agreed payment milestones and their related invoices and receipts' ); ?>
		<div class="customer-document-counts"><?php foreach ( $data['counts'] as $type => $count ) : ?><span><?php echo esc_html( co_sales_types()[$type] ); ?><b><?php echo $count; ?></b></span><?php endforeach; ?></div>
		<div class="customer-subheading"><h3>Payment milestones</h3><span>Based on the agreed quotation</span></div>
		<?php co_crm_customer_installments( $finance['quote'], $data['documents'], $token ); ?>
	</section>
	<section class="brief-card brief-card--wide" id="customer-ma"><?php co_report_card_header( 'maintenance', 'Maintenance & support', 'Your service period, available hours and maintenance activity' ); ?><?php co_crm_customer_ma( $data ); ?></section>
	</div><footer class="brief-footer">Last updated <?php echo esc_html( wp_date( 'd/m/Y H:i' ) ); ?> · Please keep this project report link private.</footer></main>
<?php }
