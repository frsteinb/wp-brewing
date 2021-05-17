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
		register_setting( 'wp_brewing_group', 'wp_brewing_bf_location', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_bf_cache', 'absint' );
		register_setting( 'wp_brewing_group', 'wp_brewing_bs_location', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_bs_cache', 'absint' );
		register_setting( 'wp_brewing_group', 'wp_brewing_category', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_bjcp_name', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_plaato_keg_tokens', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_name_firma', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_ansprechpartner', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_strasse_nr', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_telefon', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_email', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_plz_ort', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_hza', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_hza_anschrift', 'text' );
		register_setting( 'wp_brewing_group', 'wp_brewing_2075_steuerlagernummer', 'text' );

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
			'wp_brewing_bf_location',
			__( 'Brewfather JSON Dump Location', 'wp-brewing' ),
			array( $this, 'bf_location_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_bf_cache',
			__( 'Brewfather Cache duration (seconds)', 'wp-brewing' ),
			array( $this, 'bf_cache_option' ),
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

		add_settings_field(
			'wp_brewing_plaato_keg_tokens',
			__( 'Plaato Keg Auth Tokens', 'wp-brewing' ),
			array( $this, 'plaato_keg_tokens_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_name_firma',
			__( '2075 Name/Firma', 'wp-brewing' ),
			array( $this, 'steuer_2075_name_firma_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_ansprechpartner',
			__( '2075 Ansprechpartner', 'wp-brewing' ),
			array( $this, 'steuer_2075_ansprechpartner_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_strasse_nr',
			__( '2075 Straße & Nr.', 'wp-brewing' ),
			array( $this, 'steuer_2075_strasse_nr_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_telefon',
			__( '2075 Telefon', 'wp-brewing' ),
			array( $this, 'steuer_2075_telefon_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_email',
			__( '2075 Email', 'wp-brewing' ),
			array( $this, 'steuer_2075_email_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_plz_ort',
			__( '2075 PLZ & Ort', 'wp-brewing' ),
			array( $this, 'steuer_2075_plz_ort_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_hza',
			__( '2075 Hauptzollamt', 'wp-brewing' ),
			array( $this, 'steuer_2075_hza_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_hza_anschrift',
			__( '2075 HZA Anschrift', 'wp-brewing' ),
			array( $this, 'steuer_2075_hza_anschrift_option' ),
			'wp-brewing',
			'wp_brewing_section'
		);

		add_settings_field(
			'wp_brewing_2075_steuerlagernummer',
			__( '2075 Steuerlagernummer', 'wp-brewing' ),
			array( $this, 'steuer_2075_steuerlagernummer_option' ),
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

	function bf_location_option() {
		$location = get_option( 'wp_brewing_bf_location', "/tmp/Brewfather_EXPORT_ALL.json" );
		?>
		<input type="text" size="60" id="wp_brewing_bf_location" name="wp_brewing_bf_location" value="<?php echo get_option( 'wp_brewing_bf_location', "/... or https://..." ); ?>" />
		<?php
	}

	function bf_cache_option() {
		?>
		<input type="text" id="wp_brewing_bf_cache" name="wp_brewing_bf_cache" value="<?php echo get_option( 'wp_brewing_bf_cache', 10*60 ); ?>" />
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

	function plaato_keg_tokens_option() {
		?>
		<input type="text" size="60" id="wp_brewing_plaato_keg_tokens" name="wp_brewing_plaato_keg_tokens" value="<?php echo get_option( 'wp_brewing_plaato_keg_tokens', "name1:token1 name2:token2 ..." ); ?>" />
		<?php
	}

	function steuer_2075_name_firma_option() {
		?>
		<input type="text" id="wp_brewing_2075_name_firma" name="wp_brewing_2075_name_firma" value="<?php echo get_option( 'wp_brewing_2075_name_firma', 'Hobbybrauer, Persönlicher Name' ); ?>" />
		<?php
	}

	function steuer_2075_ansprechpartner_option() {
		?>
		<input type="text" id="wp_brewing_2075_ansprechpartner" name="wp_brewing_2075_ansprechpartner" value="<?php echo get_option( 'wp_brewing_2075_ansprechpartner', 'Persönlicher Name' ); ?>" />
		<?php
	}

	function steuer_2075_strasse_nr_option() {
		?>
		<input type="text" id="wp_brewing_2075_strasse_nr" name="wp_brewing_2075_strasse_nr" value="<?php echo get_option( 'wp_brewing_2075_strasse_nr', 'Straße Nr' ); ?>" />
		<?php
	}

	function steuer_2075_telefon_option() {
		?>
		<input type="text" id="wp_brewing_2075_telefon" name="wp_brewing_2075_telefon" value="<?php echo get_option( 'wp_brewing_2075_telefon', '' ); ?>" />
		<?php
	}

	function steuer_2075_email_option() {
		?>
		<input type="text" id="wp_brewing_2075_email" name="wp_brewing_2075_email" value="<?php echo get_option( 'wp_brewing_2075_email', '' ); ?>" />
		<?php
	}

	function steuer_2075_plz_ort_option() {
		?>
		<input type="text" id="wp_brewing_2075_plz_ort" name="wp_brewing_2075_plz_ort" value="<?php echo get_option( 'wp_brewing_2075_plz_ort', 'PLZ Ort' ); ?>" />
		<?php
	}

	function steuer_2075_hza_option() {
		?>
		<input type="text" id="wp_brewing_2075_hza" name="wp_brewing_2075_hza" value="<?php echo get_option( 'wp_brewing_2075_hza', 'Name' ); ?>" />
		<?php
	}

	function steuer_2075_hza_anschrift_option() {
		?>
		<input type="text" id="wp_brewing_2075_hza_anschrift" name="wp_brewing_2075_hza_anschrift" value="<?php echo get_option( 'wp_brewing_2075_hza_anschrift', 'Anschrift' ); ?>" />
		<?php
	}

	function steuer_2075_steuerlagernummer_option() {
		?>
		<input type="text" id="wp_brewing_2075_steuerlagernummer" name="wp_brewing_2075_steuerlagernummer" value="<?php echo get_option( 'wp_brewing_2075_steuerlagernummer', '' ); ?>" />
		<?php
	}
    
}

new WP_Brewing_Admin();

