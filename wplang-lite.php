<?php
/*
Plugin Name: WPLANG Lite
Version: 0.5-trunk
Plugin URI: http://uplift.ru/projects/
Description: Creates a separate tiny .mo file to use on a site front-end. Allows to save some amount of RAM on a shared hosting server.
Author: Sergey Biryukov
Author URI: http://sergeybiryukov.ru/
*/

$wpll_locale = defined('WPLANG') ? WPLANG : '';
$wpll_pofile = WP_LANG_DIR . "/$wpll_locale.po";	
$wpll_ms_pofile = WP_LANG_DIR . "/ms-$wpll_locale.po";	
$wpll_mofile = WP_LANG_DIR . "/{$wpll_locale}_lite.mo";

function wpll_check_permissions() {
	global $wpll_mofile;
	if ( !is_writable(dirname($wpll_mofile)) ) {
		load_plugin_textdomain('wplang-lite', false, dirname(plugin_basename(__FILE__)));
?>
<div class="error"><p><strong>[WPLANG Lite] <?php printf(__('%s is not writeable! Please change the permissions to 755 or 777.', 'wplang-lite'), LANGDIR); ?></strong></p></div>
<?php
	}
}
add_action('admin_notices', 'wpll_check_permissions');

function wpll_load_mofile($mofile, $domain) {
	global $wpll_mofile;

	if ( $domain == 'default' && !defined('WP_ADMIN') && file_exists($wpll_mofile) ) {
		if ( strpos($mofile, 'ms-') === false )
			$mofile = $wpll_mofile;
		else
			$mofile = '';
	}

	return $mofile;
}
add_filter('load_textdomain_mofile', 'wpll_load_mofile', 10, 2);

function wpll_hide_mofile_from_dropdown_in_ms($output) {
	global $locale;

	foreach ( $output as $language => $options ) {
		$output[$language] = str_replace('_lite', '', $output[$language]);
		$output[$language] = str_replace($locale, $locale . '" selected="selected', $output[$language]);
	}

	return $output;
}
add_filter('mu_dropdown_languages', 'wpll_hide_mofile_from_dropdown_in_ms');

function wpll_filter_references($reference) {
	$exclusions = array(
		'wp-admin/',
		'wp-content/plugins/',
		'wp-content/themes/',
		'wp-includes/js/tinymce/',
		'wp-includes/theme-compat/',
		'wp-includes/functions.php',
		'wp-includes/script-loader.php',
		'xmlrpc.php'
	);

	return $reference == str_replace($exclusions, '', $reference);
}

function wpll_create_mofile() {
	global $wpll_pofile, $wpll_ms_pofile, $wpll_mofile;

	include_once(ABSPATH . WPINC . '/pomo/po.php');
	load_plugin_textdomain('wplang-lite', false, dirname(plugin_basename(__FILE__)));

	$po = new PO();
	if ( !@$po->import_from_file($wpll_pofile) )
		wp_die('[WPLANG Lite] ' . sprintf(__('Could not read file %s', 'wplang-lite'), $wpll_pofile));

	if ( function_exists('is_multisite') && is_multisite() ) {
		$ms_po = new PO();
		if ( !@$ms_po->import_from_file($wpll_ms_pofile) )
			wp_die('[WPLANG Lite] ' . sprintf(__('Could not read file %s', 'wplang-lite'), $wpll_ms_pofile));

		$po->entries += $ms_po->entries;
	}

	foreach ( $po->entries as $key => $entry ) {
		if ( !empty($entry->references) ) {
			$entry->references = array_filter($entry->references, 'wpll_filter_references');
			if ( empty($entry->references) ) {
				unset($po->entries[$key]);
				continue;
			}
		}
		if ( !empty($entry->translations) ) {
			if ( $entry->singular == $entry->translations[0] ) {
				unset($po->entries[$key]);
			}
		}
	}

	$mo = new MO();
	$mo->headers = $po->headers;
	$mo->entries = $po->entries;

	if ( @$mo->export_to_file($wpll_mofile) === false )
		wp_die('[WPLANG Lite] ' . sprintf(__('Could not create file %s', 'wplang-lite'), $wpll_mofile));

	wp_die('[WPLANG Lite] ' . sprintf(__('File created successfully.', 'wplang-lite'), $wpll_mofile));
}
if ( !empty($_GET['wpll_action']) && $_GET['wpll_action'] == 'create_mofile' ) {
	add_action('plugins_loaded', 'wpll_create_mofile', 3);
}

function wpll_create_mofile_call() {
	global $wpll_locale, $wpll_pofile, $wpll_mofile;

	if ( file_exists($wpll_mofile) && file_exists($wpll_pofile) ) {
		if ( filemtime($wpll_mofile) >= filemtime($wpll_pofile) )
			return;
	} elseif ( empty($wpll_locale) || !is_writable(dirname($wpll_mofile)) ) {
		return;
	}

	echo '<script type="text/javascript" src="' . get_option('home') . '/?wpll_action=create_mofile"></script>';
}
add_action('admin_print_scripts', 'wpll_create_mofile_call');
?>