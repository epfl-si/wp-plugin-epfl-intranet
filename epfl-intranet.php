<?php
/*
 * Plugin Name: EPFL Intranet
 * Description: Use EPFL Accred to allow website access only to specific group(s) or just force to be authenticated
 * Version:     1.0.0
 * Author:      EPFL SI
 */

namespace EPFL\Intranet;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Access denied.' );
}

if (! class_exists("EPFL\\SettingsBase") ) {
    require_once(dirname(__FILE__) . "/inc/settings.php");
}

/**
 *  Plugin function to translate text
 */
function ___($text)
{
    return __($text, "epfl-intranet");
}

// load .mo file for translation
function epfl_intranet_load_plugin_textdomain()
{
    load_plugin_textdomain( 'epfl-intranet', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'EPFL\Intranet\epfl_intranet_load_plugin_textdomain' );

class Controller
{
    static $instance = false;
    var $settings = null;
    var $is_debug_enabled = false;

    function debug ($msg)
    {
        if ($this->is_debug_enabled) {
            error_log($msg);
        }
    }

    public function __construct ()
    {
        $this->settings = new Settings();
    }

    public static function getInstance ()
    {
        if ( !self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    function hook()
    {
        $this->settings->hook();

    }


    /**
     * Add/remove .htaccess content
     */
    static function update_htaccess($insertion, $at_beginning=false)
    {

        /* In the past, we were using get_home_path() func to have path to .htaccess file. BUT, with WordPress symlinking
        functionality, get_home_path() returns path to WordPress images files = /wp/
        So, to fix this, we access .htaccess file using WP_CONTENT_DIR which is defined in wp-config.php file. We just
        have to remove 'wp-content' '*/
        $filename = str_replace("wp-content", ".htaccess", WP_CONTENT_DIR);

        $marker = 'EPFL-Intranet';

        return insert_with_markers($filename, $marker, $insertion);
    }


 

    /**
    * Check if all dependencies are present
    *
    * @return Bool true if OK
    *         String error message
    */
    static function check_prerequisites()
    {
        $accred_min_version = 0.11;
        $accred_plugin_relative_path = 'accred/EPFL-Accred.php';
        $accred_plugin_full_path = dirname(__FILE__). '/../'. $accred_plugin_relative_path;

        /* Accred Plugin missing */
        if(!is_plugin_active($accred_plugin_relative_path))
        {
            throw new \Exception(___("Cannot activate plugin! EPFL-Accred plugin is not installed/activated\n"));
        }

        return true;
    }

}

class Settings extends \EPFL\SettingsBase
{
    const SLUG = "epfl_intranet";
    var $is_debug_enabled = false;

    function hook()
    {
        parent::hook();
        $this->add_options_page(
	        ___('Intranet settings'),    // $page_title
            ___('EPFL Intranet'),        // $menu_title
            'manage_options');           // $capability

        add_action('admin_init', array($this, 'setup_options_page'));

        add_action( 'admin_notices', array($this, 'show_plugin_status' ));

        /* If visiting website */
        if(!is_admin())
        {
            $this->debug("Require protect-site.php");
            require_once(dirname(__FILE__) . "/inc/protect-site.php");
        }

        if ( defined( 'WP_CLI' ) && WP_CLI )
        {
            \WP_CLI::add_command('epfl intranet status', [get_called_class(), 'wp_cli_status' ]);
            \WP_CLI::add_command('epfl intranet update-protection', [get_called_class(), 'wp_cli_update_protection' ]);
        }

    }

    function show_plugin_status()
    {

        $this->debug("-> show_plugin_status");
        /* Website is private by default if plugin is activated */

        /* If visiting admin console  */
        if(is_admin())
        {
            $restricted_to_groups = $this->get('subscriber_group', 'epfl_accred');

            /* Only authentication needed*/
            if($restricted_to_groups == "*")
            {
                $restrict_message = ___("Website access needs Tequila/Gaspar authentication");
            }
            else /* Authentication AND authorization needed*/
            {
                $restrict_message = sprintf(___("Website access is restricted to following group(s): %s"),
                                        $restricted_to_groups);
            }

            /* Adding link to configuration page */
            $restrict_message .= ' - <a href="'.admin_url().'options-general.php?page=epfl_intranet">'. ___("Configuration page").'</a>';


            echo '<div class="notice notice-info">'.
                    '<img src="' . plugins_url( 'img/lock.svg', __FILE__ ) . '" style="height:32px; width:32px; float:left; margin:3px 15px 3px 0px;">'.
                    '<p><b>EPFL Intranet - </b> '.$restrict_message.'</p>'.
                    '</div>';
        }
    }



    /***************************************************************************************/
    /************************ Override some methods to fit our needs ***********************/

    /**
     * @return The current setting for $key
     */
    public function get ($key, $force_slug=null)
    {
        $optname = $this->option_name($key, $force_slug);
        if ( $this->is_network_version() ) {
            return get_site_option( $optname );
        } else {
            return get_option( $optname );
        }
    }

    /**
     * @return Update the current setting for $key
     */
    public function update ($key, $value, $force_slug=null)
    {
        $optname = $this->option_name($key, $force_slug);
        if ( $this->is_network_version() ) {
            return update_site_option( $optname );
        } else {
            return update_option( $optname , $value);
        }
    }


    function option_name ($key, $force_slug=null)
    {
        $slug = ($force_slug!==null) ? $force_slug : $this::SLUG;

        if ($this->is_network_version()) {
            return "plugin:" . $slug . ":network:" . $key;
        } else {
            return "plugin:" . $slug . ":" . $key;
        }
    }


    /************************ Override some methods to fit our needs ***********************/
    /***************************************************************************************/


    /***************************************************************************************/
    /*********************************** WP CLI Commands ***********************************/


    /**
     * Update site protection
     *
     * ## OPTIONS
	 *
     * [--restrict-to-groups=<groups>]
	 * : Group (or list of groups, separated by comma) to restrict website access to. If no group given, only authentication will be asked
     */
    function wp_cli_update_protection($args, $assoc_args)
    {
        $this->change_protection_config((array_key_exists('restrict-to-groups', $assoc_args)) ? $assoc_args['restrict-to-groups'] : "");
    }


    /**
     * Returns protection status
     */
    function wp_cli_status()
    {
        $msg = "Protection is enabled";

        $restricted_to_groups = $this->get('restrict_to_groups');

        if($restricted_to_groups != "")
        {
            $msg .= " and restricted to group(s) ".$restricted_to_groups;
        }

        \WP_CLI::success($msg);
    }




    /*********************************** WP CLI Commands ***********************************/
    /***************************************************************************************/



    /**
     * Change site protection status
     *
     * @param String $restrict_to_groups -> List of groups to restrict access to (separated by ,)
     */
    function change_protection_config($restrict_to_groups)
    {
        // Checking if entered groups are correct
        if($this->validate_restrict_to_groups($restrict_to_groups) == $restrict_to_groups)
        {

            $this->update('restrict_to_groups', $restrict_to_groups);

            $msg = "Site protection successfully updated";
            if($restrict_to_groups != "") $msg .= " for group(s) ".$restrict_to_groups;

            \WP_CLI::success($msg);
        }
        else
        {
            \WP_CLI::error("Incorrect group(s) provided", true);
        }

    }



    /**
    * Validate entered group list for which to restrict access
    */
    function validate_restrict_to_groups($restrict_to_groups)
    {
        $this->debug("Intranet activated");

        $restrict_to_groups = implode(",", array_map('trim', explode(",", $restrict_to_groups) ) );

        /* All group have access, Accred plugin will handle this*/
        $epfl_accred_group = (empty(trim($restrict_to_groups))) ? '*': $restrict_to_groups;

        $this->debug("Access restricted to: ". var_export($epfl_accred_group, true));

        /* We update subscribers group for EPFL Accred plugin to allow everyone */
        $this->update('subscriber_group', $epfl_accred_group, 'epfl_accred');

        return $restrict_to_groups;

    }




    /**
     * Prepare the admin menu with settings and their values
     */
    function setup_options_page()
    {

        $this->add_settings_section('section_about', ___('About'));
        $this->add_settings_section('section_settings', ___('Settings'));


        /* Group list for restriction */
        $this->register_setting('restrict_to_groups', array(
                'type'    => 'text',
                'sanitize_callback' => array($this, 'validate_restrict_to_groups')));

        $this->add_settings_field(
                'section_settings',
                'restrict_to_groups',
                ___("Restrict access to group(s)"),
                array(
                    'type'        => 'text',
                    'help' => ___('If field is left empty, only an authentication will be requested.<br>Several groups can be entered, just separated with a comma.')
                )
            );

    }

    function render_section_about()
    {
        echo "<p>\n";
        echo ___('Needs <a href="https://github.com/epfl-sti/wordpress.plugin.accred/tree/vpsi">EPFL-Accred</a> (VPSI version) plugin
to work correctly. <br>Allows to restrict website access by forcing user to authenticate using
<a href="https://github.com/epfl-sti/wordpress.plugin.tequila">Tequila</a>. <br>You can either only request and authentication,
either force to be member of one of the defined groups (https://groups.epfl.ch).<br>
If plugin is activated, website is protected and <b>media files are also protected</b>.');
        echo "</p>\n";
    }


    function render_section_settings ()
    {
        // Nothing — The fields in this section speak for themselves
    }


    function debug ($msg)
    {
        if ($this->is_debug_enabled) {
            error_log("EPFL-Intranet: ".$msg);
        }
    }


}


Controller::getInstance()->hook();

// Add endpoint to WordPress REST API -> /wp-json/epfl-intranet/v1/groups
add_action( 'rest_api_init', function() {
    register_rest_route( 'epfl-intranet/v1', 'groups', array(
        'method'   => 'WP_REST_Server::READABLE',
        'callback' => __NAMESPACE__ . '\\get_epfl_intranet_groups',
    ) );
} );

function get_epfl_intranet_groups() {
    $restrict_to_groups = get_option('plugin:epfl_intranet:restrict_to_groups');
    $groups = explode(',', $restrict_to_groups);
    $data = [];
    foreach ($groups as $group) {
        array_push($data, array('group_name' => $group));
    }
    return $data;
}
