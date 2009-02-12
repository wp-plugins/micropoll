<?php
/**
 * Copyright 2008 Chris Abernethy
 *
 * This file is part of MicroPoll.
 *
 * MicroPoll is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * MicroPoll is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MicroPoll.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Easily integrate web-based polls from MicroPoll into WordPress
 */
class MicroPoll
{

    /**
     * An instance of the options structure containing all options for this
     * plugin.
     *
     * @var MicroPoll_Structure_Options
     */
    var $_options = null;

    /**************************************************************************/
    /*                         Singleton Functionality                        */
    /**************************************************************************/

    /**
     * Retrieve the instance of this class, creating it if necessary.
     *
     * @return MicroPoll
     */
    function instance()
    {
        static $instance = null;
        if (null == $instance) {
            $c = __CLASS__;
            $instance = new $c;
        }
        return $instance;
    }

    /**
     * The constructor initializes the options object for this plugin.
     */
    function MicroPoll()
    {
        $this->_options = new MicroPoll_Structure_Options('micropoll_options');
    }

    /**************************************************************************/
    /*                     Plugin Environment Management                      */
    /**************************************************************************/

    /**
     * This initialization method instantiates an instance of the plugin and
     * performs the initialization sequence. This method is meant to be called
     * statically from the plugin bootstrap file.
     *
     * Example Usage:
     * <pre>
     * MicroPoll::run(__FILE__)
     * </pre>
     *
     * @param string $plugin_file The full path to the plugin bootstrap file.
     */
    function run($plugin_file)
    {
        $plugin = MicroPoll::instance();

        // Configure localization.
        load_plugin_textdomain('micropoll', false, 'locale');

        // Activation and deactivation hooks have special registration
        // functions that handle sanitization of the given filename. It
        // is recommended that these be used rather than directly adding
        // an action callback for 'activate_<filename>'.

        register_activation_hook  ($plugin_file, array(&$plugin, 'hookActivation'));
        register_deactivation_hook($plugin_file, array(&$plugin, 'hookDeactivation'));

        // Set up action callbacks.
        add_action('admin_menu'                 , array(&$plugin, 'registerOptionsPage'));
        add_action('micropoll_save_options', array(&$plugin, 'saveOptionsPage'));
        add_action('wp_footer'                  , array(&$plugin, 'footerAction'));
        add_filter('plugin_action_links'        , array(&$plugin, 'renderOptionsLink'), 10, 2);
        add_action('plugins_loaded'             , array(&$plugin, 'pluginsLoaded'));
    }

    /**
     * This is the plugin activation hook callback. It performs setup actions
     * for the plugin and should be smart enough to know when the plugin has
     * already been installed and is simply being re-activated.
     */
    function hookActivation()
    {
        // If 'version' is not yet set in the options array, this is a first
        // time install scenario. Perform the initial database and options
        // setup.
        if (null === $this->getOption('version')) {
            $this->_install();
            return;
        }

        // If the plugin version stored in the options structure is older than
        // the current plugin version, initiate the upgrade sequence.
        if (version_compare($this->getOption('version'), '1.0', '<')) {
            $this->_upgrade();
            return;
        }
    }

    /**
     * This is the plugin deactivation hook callback, it performs teardown
     * actions for the plugin.
     */
    function hookDeactivation()
    {
    }

    /**
     * This method is called when the plugin needs to be installed for the first
     * time.
     */
    function _install()
    {
        global $wpdb;

        // Create fields in the posts table to hold per-post plugin options.
        $wpdb->query(sprintf("
            ALTER TABLE %s
               ADD COLUMN `%s` tinyint(1) unsigned NOT NULL DEFAULT 0
          ", $wpdb->posts
           , $wpdb->escape('micropoll_inactive')
        ));

        // Set the default options.
        $this->setOption('version', '1.0');
        $this->setOption('slot-code', null);
        $this->_options->save();
    }

    /**
     * This method is called when the internal plugin state needs to be
     * upgraded.
     */
    function _upgrade()
    {
        // Upgrade Example
        //$old_version = $this->getOption('version');
        //if (version_compare($old_version, '3.5', '<')) {
        //    // Do upgrades for version 3.5
        //    $this->setOption('version', '3.5');
        //}
        $this->setOption('version', '1.0');
        $this->_options->save();
    }

    /**************************************************************************/
    /*                          Action Hook Callbacks                         */
    /**************************************************************************/

    /**
     * This is the admin_menu activation hook callback, it adds a sub-menu
     * navigation item for this plugin to the plugins.php page and links it to
     * the renderOptionsPage() method.
     *
     * Plugins wishing to change this default behavior should override this
     * method to create the appropriate options pages.
     */
    function registerOptionsPage()
    {
        $page = add_submenu_page(
            'plugins.php'                     // parent
          , wp_specialchars('MicroPoll')  // page_title
          , wp_specialchars('MicroPoll')  // menu_title
          , 'manage_options'                  // access_level
          , 'micropoll'                  // file
          , array(&$this, 'renderOptionsPage') // function
        );
    }

    /**
     * This action hook callback is called in the footer of a template.
     */
    function footerAction()
    {
        if(is_single() || is_page()) {
            global $post;
            if(!$post->{'micropoll_inactive'}) {
                echo stripslashes($this->getOption('icon-code'));
            }
        }
    }

    /**
     * This action hook callback is called after all plugins are loaded. It is
     * useful for registering other callbacks that cannot be executing while
     * plugins are loading, e.g., register_sidebar_widget().
     */
    function pluginsLoaded()
    {
        // Register sidebar widgets.
        wp_register_sidebar_widget(
            sanitize_title('MicroPoll'),
            'MicroPoll',
            array(&$this, 'renderSidebarWidget'),
            array('description' => 'MicroPoll widget.')
        );
    }

    /**************************************************************************/
    /*                          Filter Hook Callbacks                         */
    /**************************************************************************/

    /**
     * This is the 'plugin_action_links' hook callback, it adds a single link
     * to the options page that was registered by the registerOptionsPage()
     * method. The link is titled 'Settings', and will appear as the first link
     * in the list of plugin links.
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    function renderOptionsLink($links, $file)
    {
        static $plugin_dir = null;
        if(null === $plugin_dir) {
            $plugin_dir = plugin_basename(__FILE__);
            $plugin_dir = substr($plugin_dir, 0, stripos($plugin_dir, '/'));
        }

        if (dirname($file) == $plugin_dir) {
            $view = new MicroPoll_Structure_View('options-link.phtml');
            $view->set('link_href' , 'plugins.php?page=micropoll');
            $view->set('link_title', sprintf(__('%s Settings', 'micropoll'), 'MicroPoll'));
            $view->set('link_text' , __('Settings', 'micropoll'));
            ob_start();
            $view->render();
            array_unshift($links, ob_get_clean());
        }
        return $links;
    }

    /**
     * Save the results of a post from the options page.
     */
    function saveOptionsPage()
    {
        global $wpdb;

        if (isset($_POST['action']) && 'update' == $_POST['action']) {

            check_admin_referer('update-options');

            $this->setOption('slot-code', $_POST['micropoll_slot_code']);
            $this->_options->save();

            // Render the header message partial
            $this->_messageHelper(__('Settings have been saved.', 'micropoll'));

        }
    }

    /**************************************************************************/
    /*                           Indirect Callbacks                           */
    /**************************************************************************/

    /**
     * This method fires the custom <label>_save_options action hook and registers
     * the renderAdminFooter() method as an 'in_admin_footer' action hook before
     * rendering the actual options page.
     */
    function renderOptionsPage()
    {
        // Invoke the action hook for saving the options page.
        do_action('micropoll_save_options');

        // Register the in_admin_footer action hook. This is done here so that
        // it only gets registered for the options page for this plugin, and
        // not every plugin.
        add_action('in_admin_footer', array(&$this, 'renderAdminFooter'));

        $view = new MicroPoll_Structure_View('options-page.phtml');
        $view->set('heading'     , sprintf(__('%s Settings', 'micropoll'), 'MicroPoll'));
        $view->set('nonce_action', 'update-options');
        $view->set('plugin_label', 'micropoll');
        $view->set('slot-code'   , stripslashes($this->getOption('slot-code')));
        $view->render();
    }

    /**
     * Renders the sidebar widget code.
     *
     * @param array $args
     */
    function renderSidebarWidget($args)
    {
        extract($args);
        echo $before_widget;
        echo $before_title;
        echo "MicroPoll";
        echo $after_title;
        echo stripslashes($this->getOption('slot-code'));
        echo $after_widget;
    }

    /**
     * Action hook callback meant to be used with the 'in_admin_footer' hook.
     * This callback renders plugin author information into the admin footer.
     * Whenever possible, this should only be used on the admin page for this
     * plugin.
     */
    function renderAdminFooter()
    {
        $view = new MicroPoll_Structure_View('options-footer.phtml');
        $view->set('plugin_href'   , 'http://www.chrisabernethy.com/wordpress-plugins/micropoll/');
        $view->set('plugin_text'   , 'MicroPoll');
        $view->set('plugin_version', '1.0');
        $view->set('author_href'   , 'http://www.chrisabernethy.com/');
        $view->set('author_text'   , 'Chris Abernethy');
        $view->render();
    }

    /**************************************************************************/
    /*                            Utility Methods                             */
    /**************************************************************************/

    /**
     * Render the given message using the message.phtml partial. This is typically
     * used to render confirmation messages in the admin area.
     *
     * @param string $message The message to display.
     */
    function _messageHelper($message)
    {
        $view = new MicroPoll_Structure_View('message.phtml');
        $view->set('message', $message);
        $view->render();
    }

    /**
     * This accessor grants read access to the internal options object so that
     * the isPrivate method can check option values when it is called as a
     * static method.
     *
     * @param string $option_name
     * @return Mixed
     */
    function getOption($option_name)
    {
        return $this->_options->get($option_name);
    }

    /**
     * This accessor grants write access to the internal options object so that
     * option values can be changed.
     *
     * @param string $option_name
     * @param mixed $option_value
     */
    function setOption($option_name, $option_value)
    {
        $this->_options->set($option_name, $option_value);
    }

};

/* EOF */