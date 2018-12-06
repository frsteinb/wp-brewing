<?php

class WP_Brewing_Admin {

	function __construct() {
		add_filter( 'plugin_action_links_' . WP_BREWING_BASENAME, array( $this, 'settings_link' ) );
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'admin_init', array( $this, 'options_init' ) );
	}

	function settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=wp-brewing">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	function add_options_page() {
		add_options_page(
			'WP Brewing',
			'WP Brewing',
			'manage_options',
			'wp-brewing',
			array( $this, 'options_page' )
		);
	}

	function options_page() {
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2>WP Brewing Settings</h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wp_brewing_group' );
				do_settings_sections( 'wp-brewing' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	function options_init() {
		register_setting( 'wp_brewing_group', 'wp_brewing_kbh_location', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_kbh_cache', 'absint' );
		register_setting( 'wp_brewing_group', 'wp_brewing_bs_location', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_bs_cache', 'absint' );
		register_setting( 'wp_brewing_group', 'wp_brewing_category', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_bjcp_name', 'text' );

		add_settings_section(
			'wp_brewing_section',
			__( 'Default settings', 'wp-brewing' ),
			array( $this, 'print_section_info' ),
			'wp-brewing'
		);

		add_settings_field(
			'wp_brewing_kbh_location',
			__( 'KBH Database Location', 'wp-brewing' ),
			array( $this, 'kbh_location_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_kbh_cache',
			__( 'KBH Cache duration (seconds)', 'wp-brewing' ),
			array( $this, 'kbh_cache_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_bs_location',
			__( 'BS Database Location', 'wp-brewing' ),
			array( $this, 'bs_location_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_bs_cache',
			__( 'BS Cache duration (seconds)', 'wp-brewing' ),
			array( $this, 'bs_cache_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_category',
			__( 'Recipe Category', 'wp-brewing' ),
			array( $this, 'category_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_bjcp_name',
			__( 'BJCP XML file name', 'wp-brewing' ),
			array( $this, 'bjcp_name_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);


	}

	function print_section_info() {
		_e( 'The settings specify the access to the SQLite3 database file of "Kleiner Brauhelfer". The location can be a file path on the WordPress server system or a URL. In case of a file path, this file must be accessible to the web server user. In case of a URL, the cache duration is applied. A URL might be useful, if you store your KBH database on cloud storage, for example. If the BJCP Styleguide in XML form has been uploaded, you can specify its file name, so that style IDs can be rendered more meaningful.', 'wp-brewing' );
	}

	function kbh_location_option() {
		$location = get_option( 'wp_brewing_kbh_location', "/root/.kleiner-brauhelfer/kb_daten.sqlite" );
		?>
		<input type="text" size="60" id="wp_brewing_kbh_location" name="wp_brewing_kbh_location" value="<?php echo get_option( 'wp_brewing_kbh_location', "/... or https://..." ); ?>" />
		<?php
	}

	function kbh_cache_option() {
		?>
		<input type="text" id="wp_brewing_kbh_cache" name="wp_brewing_kbh_cache" value="<?php echo get_option( 'wp_brewing_kbh_cache', 10*60 ); ?>" />
		<?php
	}

	function bs_location_option() {
		$location = get_option( 'wp_brewing_bs_location', "/root/Documents/BeerSmith3/Recipes.bsmx" );
		?>
		<input type="text" size="60" id="wp_brewing_bs_location" name="wp_brewing_bs_location" value="<?php echo get_option( 'wp_brewing_bs_location', "/... or https://..." ); ?>" />
		<?php
	}

	function bs_cache_option() {
		?>
		<input type="text" id="wp_brewing_bs_cache" name="wp_brewing_bs_cache" value="<?php echo get_option( 'wp_brewing_bs_cache', 10*60 ); ?>" />
		<?php
	}

	function category_option() {
		?>
		<input type="text" id="wp_brewing_category" name="wp_brewing_category" value="<?php echo get_option( 'wp_brewing_category', 'Sude' ); ?>" />
		<?php
	}

	function bjcp_name_option() {
		$location = get_option( 'wp_brewing_bjcp_name', "xxx" );
		?>
		<input type="text" size="60" id="wp_brewing_bjcp_name" name="wp_brewing_bjcp_name" value="<?php echo get_option( 'wp_brewing_bjcp_name', "..." ); ?>" />
		<?php
	}

}

new WP_Brewing_Admin();

