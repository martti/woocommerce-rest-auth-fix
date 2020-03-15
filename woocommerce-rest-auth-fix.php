<?php
/**
 *
 * @link              https://github.com/martti/woocommerce-rest-auth-fix
 * @since             1.0.0
 * @package           WooCommerce_RestAuthFix
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce rest auth fix
 * Plugin URI:        https://github.com/martti/woocommerce-rest-auth-fix
 * Description:       Fixes REDIRECT_HTTP_AUTHORIZATION
 * Version:           1.0.0
 * Author:            Martti Hyppänen
 * Author URI:        https://github.com/martti
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-rest-auth-fix
 * Domain Path:       /languages
 *
 * WC requires at least: 3.9
 * WC tested up to: 4.0
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action('admin_menu', 'wcraf_setup_menu');
add_filter('determine_current_user', 'wcraf_fix_authentication', 1);

function wcraf_setup_menu() {
  add_options_page('Woocommerce rest auth fix', 'Woocommerce rest auth fix', 'administrator', 'woocommerce-rest-auth-fix', 'wcraf_page');
  // add_menu_page('Woocommerce rest auth fix', 'Woocommerce rest auth fix', 'manage_options', 'woocommerce-rest-auth-fix', 'wcraf_home' );
}

function wcraf_is_subdirectory_install() {
  if (strlen(site_url()) > strlen(home_url())) {
      return true;
  }
  return false;
}

function wcraf_getABSPATH() {
  $path = ABSPATH;
  if (wcraf_is_subdirectory_install()) {
      $siteUrl = site_url();
      $homeUrl = home_url();
      $diff = str_replace($homeUrl, "", $siteUrl);
      $diff = trim($diff, "/");
      $pos = strrpos($path, $diff);
      if ($pos !== false) {
          $path = substr_replace($path, "", $pos, strlen($diff));
          $path = trim($path, "/");
          $path = "/" . $path . "/";
      }
  }

  return $path;
}

function wcraf_update_htaccess() {

  $htaccess_file = wcraf_getABSPATH() . ".htaccess";
  $htaccess = file_get_contents($htaccess_file);
  $htaccess = preg_replace("/#\s?BEGIN\s?woocommerce-rest-auth-fix.*?#\s?END\s?woocommerce-rest-auth-fix/s", "", $htaccess);
  $htaccess = preg_replace("/\n+/", "\n", $htaccess);
  $rules = "# BEGIN woocommerce-rest-auth-fix\n";
  $rules .= "<IfModule mod_rewrite.c>\n";
  $rules .= "RewriteEngine On\n";
  $rules .= "RewriteRule ^index\.php$ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]\n";
  $rules .= "</IfModule>\n";
  $rules .= "# END woocommerce-rest-auth-fix\n";
  //insert rules before WordPress part.
  $wptag = "# BEGIN WordPress";
  if (strpos($htaccess, $wptag) !== false) {
    $htaccess = str_replace($wptag, $rules . $wptag, $htaccess);
  } else {
    $htaccess = $htaccess . $rules;
  }
  file_put_contents($htaccess_file, $htaccess);
}

function wcraf_page() {

  $htaccess_file = wcraf_getABSPATH() . ".htaccess";
  $htaccess = file_get_contents($htaccess_file);

  echo '<div class="wrap">';
  echo '<h1>Woocommerce rest auth fix</h1>';

  $check = 0;
  if (strpos($htaccess, "# BEGIN woocommerce-rest-auth-fix") !== FALSE) $check = 1;

  if (isset($_POST['submit']) && $check === 0) {
    # echo "submit";
    wcraf_update_htaccess();
  }

  $htaccess_file = wcraf_getABSPATH() . ".htaccess";
  $htaccess = file_get_contents($htaccess_file);

  $check = 0;
  if (strpos($htaccess, "# BEGIN woocommerce-rest-auth-fix") !== FALSE) $check = 1;

  if ($check === 0) {
    echo '<div class="card notice-wrapper notice-error">';
    echo '<h2>.htaccess HTTP_AUTHORIZATION puuttuu</h2>';
    echo '</div>';
  } else {
    echo '<div class="card notice-wrapper">';
    echo '<h2>.htaccess HTTP_AUTHORIZATION ok</h2>';
    echo '</div>';
  }

  echo '<pre>'. esc_textarea($htaccess) .'</pre>';

  echo '<form id="wp-woocommerce-rest-auth-fix-form" action="' . admin_url('options-general.php?page=woocommerce-rest-auth-fix') . '" method="post" autocomplete="off">';

  echo '<p id="wphe-buttons">';
  if ($check === 0) {
    echo submit_button('Päivitä .htaccess');
  }
  // echo '<a id="wcraf_update_htaccess" href="#" class="button button-primary">Päivitä .htaccess</a>';
  echo '</p>';

  echo '</form>';
  echo '</div>'; // wrap
}

function wcraf_fix_authentication() {
  if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) &&
      !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) &&
      empty($_SERVER['PHP_AUTH_USER']) &&
      empty($_SERVER['PHP_AUTH_PW'])) {

    list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)));

	}
}
