<?php

/**
 *
 * @link              tooeasy.fr
 * @since             1.0.0
 * @package           Ip_Auth
 *
 * @wordpress-plugin
 * Plugin Name:       IP Auth
 * Description:       Limite la connexion d'un utilisateur à une liste d'adresses IP
 * Version:           1.0.0
 * Author:            TooEasy
 * Author URI:        tooeasy.fr
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ip auth
 * Domain Path:       /languages
 */


/**
 * Validate IP Address During Authentication - For A Given User 
 */
add_filter('authenticate', function ($user) {

	$allowed_user_ip        = esc_html((get_user_meta($user->ID, 'list_ip')[0]));
	$allowed_user_ip_table = explode(",", $allowed_user_ip);
	$allowed_user_ip_table  = array_map('trim', $allowed_user_ip_table);

	// Current user's IP address
	$current_user_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

	// Nothing to do for valid IP address
	if (in_array($current_user_ip, $allowed_user_ip_table) || !($allowed_user_ip))
		return $user;

	// Add an 'Invalid IP address' error
	if (is_wp_error($user))
		$user->add(
			'invalid_ip',
			sprintf(
				'<strong>%s</strong>: %s',
				esc_html__('Erreur', 'mydomain'),
				esc_html__('L\'adresse IP n\'est pas valide.', 'mydomain')
			)
		);
	// Create a new 'Invalid IP address' error
	else
		$user = new WP_Error(
			'invalid_ip',
			sprintf(
				'<strong>%s</strong>: %s',
				esc_html__('Erreur', 'mydomain'),
				esc_html__('L\'adresse IP n\'est pas valide.', 'mydomain')
			)
		);

	return $user;
}, 100);


/**
 * Add fields to user profile screen, add new user screen
 */
if (!function_exists('AddInputIPInUserProfile')) {
	//  This action for 'Add New User' screen
	add_action('user_new_form', 'AddInputIPInUserProfile');
	//  This actions for 'User Profile' screen
	add_action('show_user_profile', 'AddInputIPInUserProfile');
	add_action('edit_user_profile', 'AddInputIPInUserProfile');
	function AddInputIPInUserProfile($user)
	{
		if (!current_user_can('administrator', $user_id))
			return false;
?>
		<h3>Gestion des listes d'IP</h3>
		<table class="form-table">
			<tr>
				<th><label for="list_ip">Liste des adresses IP autorisées (séparées par ",")</label></th>
				<td>
					<input type="text" class="regular-text" name="list_ip" value="<?php echo esc_html(get_user_meta($user->ID, 'list_ip')[0]); ?>" id="list_ip" /><br />
				</td>
			</tr>
		</table>
<?php }
}

/**
 *  Save ip field to user profile page, add new profile page etc
 */

//  This action for 'Add New User' screen
add_action('user_register', 'cp_save_new_profile_fields');
//  This actions for 'User Profile' screen
add_action('personal_options_update', 'saveNewIpInEditPage');
add_action('edit_user_profile_update', 'saveNewIpInEditPage');
// Error
add_filter('user_profile_update_errors', 'returnAnErrorIfIpIsNotValid');


if (!function_exists('returnAnErrorIfIpIsNotValid')) {
	/**
	 * Display an error if ip in the input are not valid
	 */
	function returnAnErrorIfIpIsNotValid($errors)
	{
		$list_ip = sanitize_text_field($_POST['list_ip']);
		if ( empty( $list_ip ) || "" === $list_ip ) {
			return $errors;
		}

		$list_ip_table = explode(',', $list_ip);
		$list_ip_table = array_map('sanitize_text_field', $list_ip_table);
		$list_ip_table  = array_map('trim', $list_ip_table);

		foreach ($list_ip_table as $ip) {
			if (!(filter_var(trim($ip), FILTER_VALIDATE_IP)) && $list_ip != "") {
				$errors->add('wrongIP', '<strong>Erreur</strong>: Les adresses IP ne sont pas valides');
			}
		}
		return $errors;
	}
}



if (!function_exists('saveNewIpInEditPage')) {
	function saveNewIpInEditPage($user_id)
	{
		$list_ip = sanitize_text_field($_POST['list_ip']);
		$list_ip_table = explode(',', $list_ip);
		$list_ip_table = array_map('sanitize_text_field', $list_ip_table);
		$list_ip_table  = array_map('trim', $list_ip_table);

		$error = false;
		foreach ($list_ip_table as $ip) {
			if (!(filter_var(trim($ip), FILTER_VALIDATE_IP))  && $list_ip != "") {
				$error = true;
			}
		}

		if (!($error)) {
			if ($list_ip == "") {
				delete_user_meta($user_id, 'list_ip');
			} else {
				update_user_meta($user_id, 'list_ip', $list_ip);
			}
		}
	}
}

if (!function_exists('SaveNewIpInNewUserPage')) {

	function SaveNewIpInNewUserPage($user_id)
	{
		$list_ip = sanitize_text_field($_POST['list_ip']);
		if ($list_ip != "") {
			add_user_meta(
				$user_id,
				'list_ip',
				$list_ip
			);
		}
	}
}

/**
 * add column to user table
 */

if (!function_exists('addIpColumnToUserTable')) {
	function addIpColumnToUserTable($column)
	{
		$column['list_ip'] = 'Liste des IP';
		return $column;
	}
	add_filter('manage_users_columns', 'addIpColumnToUserTable');



	if (!function_exists('AddDataToIpColumnOnUserTable')) {
		function AddDataToIpColumnOnUserTable($val, $column_name, $user_id)
		{
			switch ($column_name) {
				case 'list_ip':
					if (!(esc_html(get_user_meta($user_id, 'list_ip')[0]))) {
						return "Pas d'IP";
					}
					return esc_html(get_user_meta($user_id, 'list_ip')[0]);
				default:
			}
			return $val;
		}
		add_filter('manage_users_custom_column', 'AddDataToIpColumnOnUserTable', 10, 3);
	}
}
