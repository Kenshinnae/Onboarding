<?php
defined( 'ABSPATH' ) || exit;

function co_get_setting( $key, $default = '' ) { $settings = get_option( 'co_settings', array() ); return $settings[$key] ?? $default; }
function co_get_logo_url() { $id = (int) co_get_setting( 'logo_id', 0 ); return $id ? wp_get_attachment_image_url( $id, 'medium' ) : ''; }

/** Keep the WordPress workspace focused on the parts used by this client system. */
add_action( 'admin_menu', function () {
	foreach ( array( 'index.php', 'edit.php', 'edit.php?post_type=page', 'edit-comments.php', 'themes.php', 'plugins.php', 'tools.php', 'options-general.php' ) as $menu ) {
		remove_menu_page( $menu );
	}
}, 999 );
add_action( 'admin_init', function () {
	global $pagenow;
	if ( 'index.php' === $pagenow && current_user_can( 'edit_posts' ) && ! wp_doing_ajax() ) {
		wp_safe_redirect( admin_url( 'admin.php?page=co-projects' ) );
		exit;
	}
} );
add_action( 'wp_before_admin_bar_render', function () {
	global $wp_admin_bar;
	$wp_admin_bar->remove_node( 'comments' );
	$wp_admin_bar->remove_node( 'new-post' );
	$wp_admin_bar->remove_node( 'new-page' );
	$wp_admin_bar->remove_node( 'customize' );
} );

function co_admin_brand_styles() {
	wp_enqueue_style( 'co-admin-brand', get_template_directory_uri() . '/assets/admin-brand.css', array(), CO_THEME_VERSION );
}
add_action( 'admin_enqueue_scripts', 'co_admin_brand_styles' );
add_action( 'login_enqueue_scripts', 'co_admin_brand_styles' );
add_filter( 'admin_footer_text', function () { return '<span class="co-footer-mark">Client Operations Workspace</span>'; } );
add_filter( 'update_footer', '__return_empty_string', 99 );
add_filter( 'login_headerurl', fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );
add_filter( 'login_message', function ( $message ) { return '<div class="co-login-intro"><span>CLIENT OPERATIONS</span><h1>Welcome back</h1><p>Sign in to manage onboarding, projects and maintenance.</p></div>' . $message; } );

function co_admin_system_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$types = array( 'onboarding_submit', 'ma_client', 'ma_project', 'ma_worklog' );
	return $screen && in_array( $screen->post_type, $types, true ) ? $screen : null;
}
add_filter( 'admin_body_class', function ( $classes ) {
	$screen = co_admin_system_screen();
	return $classes . ( $screen ? ' co-system-screen co-system-' . sanitize_html_class( $screen->post_type ) : '' );
} );
add_action( 'all_admin_notices', function () {
	$screen = co_admin_system_screen();
	if ( ! $screen || 'edit' !== $screen->base ) return;
	$content = array(
		'onboarding_submit' => array( 'CLIENT INTAKE', 'Onboarding submissions', 'Review client requirements and move accepted work into the project pipeline.' ),
		'ma_client'         => array( 'MA OPERATIONS', 'Clients & packages', 'Manage maintenance packages, balances and contract periods.' ),
		'ma_project'        => array( 'MA OPERATIONS', 'Maintenance projects', 'Keep every client workstream organised under its package.' ),
		'ma_worklog'        => array( 'MA OPERATIONS', 'Work logs', 'Review every task and hour recorded for maintenance clients.' ),
	);
	if ( empty( $content[$screen->post_type] ) ) return;
	list( $eyebrow, $title, $description ) = $content[$screen->post_type];
	echo '<section class="co-admin-hero"><span>' . esc_html( $eyebrow ) . '</span><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $description ) . '</p></section>';
} );

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=onboarding_submit', 'Onboarding Settings', 'Settings', 'manage_options', 'co-settings', 'co_settings_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'co_settings_group', 'co_settings', array( 'sanitize_callback'=>function ( $v ) { return array( 'notification_email'=>sanitize_email( $v['notification_email'] ?? '' ), 'logo_id'=>absint( $v['logo_id'] ?? 0 ), 'form_title'=>sanitize_text_field( $v['form_title'] ?? '' ), 'intro_text'=>sanitize_textarea_field( $v['intro_text'] ?? '' ) ); } ) );
} );
function co_settings_page() { $s = get_option( 'co_settings', array() ); ?>
	<div class="wrap"><h1>Onboarding Settings</h1><form method="post" action="options.php"><?php settings_fields( 'co_settings_group' ); ?><table class="form-table">
	<tr><th><label for="co-email">Notification email</label></th><td><input class="regular-text" type="email" id="co-email" name="co_settings[notification_email]" value="<?php echo esc_attr( $s['notification_email'] ?? get_option( 'admin_email' ) ); ?>"></td></tr>
	<tr><th><label for="co-logo">Logo attachment ID</label></th><td><input type="number" id="co-logo" name="co_settings[logo_id]" value="<?php echo absint( $s['logo_id'] ?? 0 ); ?>"><p class="description">Upload a logo in Media and enter its attachment ID.</p></td></tr>
	<tr><th><label for="co-title">Form title</label></th><td><input class="regular-text" id="co-title" name="co_settings[form_title]" value="<?php echo esc_attr( $s['form_title'] ?? 'Website Project Onboarding' ); ?>"></td></tr>
	<tr><th><label for="co-intro">Intro text</label></th><td><textarea class="large-text" id="co-intro" name="co_settings[intro_text]" rows="3"><?php echo esc_textarea( $s['intro_text'] ?? 'Tell us a little about your business and what you need from your new website.' ); ?></textarea></td></tr>
	</table><?php submit_button(); ?></form></div><?php }

add_filter( 'manage_onboarding_submit_posts_columns', function () { return array( 'cb'=>'<input type="checkbox">','title'=>'Company','contact'=>'Contact','email'=>'Email','website_type'=>'Website Type','date'=>'Submitted','status'=>'Status' ); } );
add_action( 'manage_onboarding_submit_posts_custom_column', function ( $column, $id ) {
	$key = '_onboarding_' . $column; $value = get_post_meta( $id, $key, true );
	if ( 'website_type' === $column && is_array( $value ) ) $value = implode( ', ', $value );
	if ( 'status' === $column ) { $statuses = array( 'new' => 'New', 'reviewing' => 'Reviewing', 'completed' => 'Completed', 'project_created' => 'Project created', 'project_deleted' => 'Project deleted' ); echo '<span class="co-status co-' . esc_attr( $value ) . '">' . esc_html( $statuses[$value] ?? ucwords( str_replace( '_', ' ', $value ?: 'new' ) ) ) . '</span>'; } else echo esc_html( $value ?: '—' );
}, 10, 2 );
add_action( 'admin_head', function () { echo '<style>.co-status{display:inline-block;padding:4px 9px;border-radius:99px;background:#e8eefc}.co-reviewing{background:#fff2c9}.co-completed{background:#d9f5e7}.co-admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.co-admin-section{background:#fff;border:1px solid #dcdcde;padding:16px}.co-admin-section dt{font-weight:600}.co-admin-section dd{margin:2px 0 12px}@media(max-width:850px){.co-admin-grid{grid-template-columns:1fr}}</style>'; } );

add_action( 'add_meta_boxes_onboarding_submit', function () {
	add_meta_box( 'co_details', 'Project Details', 'co_details_box', 'onboarding_submit', 'normal', 'high' );
	add_meta_box( 'co_status', 'Submission Status', 'co_status_box', 'onboarding_submit', 'side', 'high' );
} );
function co_value( $id, $key ) { return get_post_meta( $id, '_onboarding_' . $key, true ); }
function co_detail_rows( $id, $keys ) { echo '<dl>'; foreach ( $keys as $key=>$label ) { $v=co_value($id,$key); if ( is_array($v) ) $v=implode(', ',array_map(function($x){return is_array($x)?($x['url']??''):$x;},$v)); echo '<dt>'.esc_html($label).'</dt><dd>'.nl2br(esc_html($v?:'—')).'</dd>'; } echo '</dl>'; }
function co_details_box( $post ) {
	$groups=array('Business Information'=>array('company_name'=>'Company','contact_name'=>'Contact','email'=>'Email','phone'=>'Phone','business_description'=>'Business description','services'=>'Services','website_url'=>'Existing website'),'Website Requirements'=>array('website_type'=>'Website type','primary_action'=>'Primary action','language_count'=>'Number of languages','languages'=>'Languages','pages'=>'Pages','custom_pages'=>'Custom pages'),'Content & Assets'=>array('content_status'=>'Content status','assets'=>'Assets','references'=>'References'),'Features'=>array('features'=>'Features','features_other'=>'Other feature'),'Domain & Hosting'=>array('domain_status'=>'Domain status','domain'=>'Domain','hosting_status'=>'Hosting status','hosting_choice'=>'Hosting choice'),'Timeline & Notes'=>array('target_launch'=>'Target launch','notes'=>'Notes'));
	echo '<p><a class="button button-primary" target="_blank" href="'.esc_url(get_permalink($post)).'">View Project Brief</a></p><div class="co-admin-grid">'; foreach($groups as $title=>$keys){echo '<section class="co-admin-section"><h3>'.esc_html($title).'</h3>';co_detail_rows($post->ID,$keys);echo '</section>';} echo '</div>';
	$ids=co_value($post->ID,'uploaded_files'); if($ids){echo '<h3>Uploaded Files</h3><ul>';foreach($ids as $id)echo '<li><a target="_blank" href="'.esc_url(wp_get_attachment_url($id)).'">'.esc_html(get_the_title($id)).'</a></li>';echo '</ul>';}
	$sitemap=absint(co_value($post->ID,'sitemap_file')); if($sitemap){echo '<h3>Existing Sitemap</h3><p><a class="button" target="_blank" href="'.esc_url(wp_get_attachment_url($sitemap)).'">View / Download '.esc_html(get_the_title($sitemap)).'</a></p>';}
}
function co_status_box( $post ) { wp_nonce_field('co_status_save','co_status_nonce'); $v=co_value($post->ID,'status')?:'new'; echo '<select name="co_submission_status" style="width:100%">'; foreach(array('new'=>'New','reviewing'=>'Reviewing','completed'=>'Completed') as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($v,$key,false).'>'.esc_html($label).'</option>'; echo '</select>'; }
add_action( 'save_post_onboarding_submit', function ( $id ) { if(!isset($_POST['co_status_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['co_status_nonce'])),'co_status_save')||!current_user_can('edit_post',$id))return; $allowed=array('new','reviewing','completed');$v=sanitize_key($_POST['co_submission_status']??'new');if(in_array($v,$allowed,true))update_post_meta($id,'_onboarding_status',$v); } );
