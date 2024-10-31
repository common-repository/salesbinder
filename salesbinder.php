<?php

/**
 * @package SalesBinder
 * @version 1.0.2
 */

/*
Plugin Name: SalesBinder for WordPress
Plugin URI: http://wordpress.org/extend/plugins/salesbinder/
Description: 
Author: SalesBinder Development Team
Author URI: http://www.salesbinder.com/tour/api-integrations/
Version: 1.0.2
*/

include('widget.php');

new WPSalesBinder();

class WPSalesBinder {
    const POST_TYPE = 'salesbinder_item';
    
    private $parsed_query = false;
    
    public function __construct() {
        add_action('init', array($this, 'init'), 0);
        
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_print_styles', array($this, 'admin_print_styles'), 20);
            
            foreach (array('post.php', 'post-new.php') as $item)
                add_action("load-{$item}", array($this, 'load'));
            
            add_filter('manage_edit-' . WPSalesBinder::POST_TYPE. '_columns', array($this, 'manage_edit_columns'));
            add_action('manage_' . WPSalesBinder::POST_TYPE . '_posts_custom_column', array($this, 'manage_posts_custom_colum'), 10, 2);
            
            add_action('admin_head-edit.php', array($this, 'admin_head_edit'));
            
            add_action('admin_notices', array($this, 'admin_notices'));
            
            add_filter('parse_query', array($this, 'parse_query'));
        }
        add_action('wp_before_admin_bar_render', array($this, 'wp_before_admin_bar_render'));
        
        add_filter('favorite_actions', array($this, 'favorite_actions'));
        
        add_filter('cron_schedules', array($this, 'cron_schedules'), 0);
        
        register_activation_hook(__FILE__, array($this, 'register_activation_hook'));
        add_action('salesbinder_cron', array($this, 'cron'));
        register_deactivation_hook(__FILE__, array($this, 'register_deactivation_hook'));
    }
    
    public function register_activation_hook() {
        wp_schedule_event(time(), 'hourly', 'salesbinder_cron');
    }
    
    public function register_deactivation_hook() {
        wp_clear_scheduled_hook('salesbinder_cron');
    }
    
    public function init() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (empty($salesbinder_settings['slug']))
            $salesbinder_settings['slug'] = 'salesbinder';
        
        register_taxonomy('salesbinder_category', WPSalesBinder::POST_TYPE, array(
            'label' => __('Categories'),
            'labels' => array(),
            'capabilities' => array(
                'manage_terms' => 'activate_plugins',
                'edit_terms' => 'activate_plugins',
                'delete_terms' => 'activate_plugins',
                'assign_terms' => 'do_not_allow'
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'query_var' => true,
            'rewrite' => array('slug' => "{$salesbinder_settings['slug']}/categories", 'with_front' => false)
        ));
        
        register_post_type(WPSalesBinder::POST_TYPE, array(
            'label' => __('SalesBinder'),
            'labels' => array(
                'name' => __('All Items'),
                'singular_name' => __('Item'),
                'add_new' => __('Add New'),
                'add_new_item' => __('Add New Item'),
                'edit_item' => __('Edit Item'),
                'new_item' => __('New Item'),
                'view_item' => __('View Item'),
                'search_items' => __('Search Items'),
                'not_found' =>  __('No items found'),
                'not_found_in_trash' => __('No items found in Trash'),
                'parent_item_colon' => '',
            ),
            'public' => true,
            'can_export' => true,
            'capabilities' => array(
        				'edit_post' => 'do_not_allow',
        				'edit_posts' => 'activate_plugins',
        				'edit_others_posts' => 'activate_plugins',
        				'publish_posts' => 'do_not_allow',
        				'read_post' => 'activate_plugins',
        				'read_private_posts' => 'do_not_allow',
        				'delete_post' => 'activate_plugins',
      			),
            'show_ui' => true,
            'capability_type' => 'post',
            'has_archive' => false,
            'menu_icon' => plugins_url('salesbinder-16x16.png', __FILE__),
            'menu_position' => 48,
            'hierarchical' => false,
            'rewrite' => array('slug' => $salesbinder_settings['slug'], 'with_front' => false),
            'supports'=> array('title', 'thumbnail', 'editor', 'custom-fields', 'comments'),
            'show_in_nav_menus' => true
        ));
        
        register_widget('WPSalesBinderCategoriesWidget');
    }
    
    public function admin_menu() {
        global $menu,
               $submenu;
        
        add_submenu_page('edit.php?post_type=' . WPSalesBinder::POST_TYPE, 'SalesBinder for WordPress Plugin Settings', 'Settings', 'administrator', __FILE__, array($this, 'settings'));
        add_action('admin_init', array($this, 'admin_init'));
        
        $index = 0;
        $menu[48][0] = __('SalesBinder');
        foreach ($menu as $offset => $section) {
        		if (substr($section[2], 0, 9) == 'separator')
        		    $index++;
        		
        		if ($offset >= 47) {
          			$menu[47] = array('', 'read', "separator{$index}", '', 'wp-menu-separator');
          			break;
            }
        }
        
        unset($submenu['edit.php?post_type=' . WPSalesBinder::POST_TYPE][10]);
        
        ksort($menu);
    }
    
    public function cron_schedules($schedules) {
        $schedules['5minutes'] = array(
         	  'interval' => 60*5,
         	  'display' => __('Once Every 5 Minutes')
       	);
       	$schedules['30minutes'] = array(
       	 	  'interval' => 60*30,
       	 	  'display' => __('Twice Hourly')
       		);
       	return $schedules;
    }
    
    public function settings() {
        require('settings.php');
    }
    
    public function settings_api_section() {
        $html = <<<END
<p>You can generate a new API Key by going into your “Profile” once logged into SalesBinder. You’ll find a button that says “Generate New API Key”. More information about generating your API Key can be found in our <a href="http://www.salesbinder.com/kb/generating-your-api-key/" target="_blank">Knowledge Base</a>.</p>
END;
        
        echo $html;
    }
    
    public function settings_scheduling_section() {
    }
    
    public function settings_permalinks_section() {
        $html = <<<END
<p>If you like, you may enter custom structures for your SalesBinder URLs here. For example, using <code>items</code> as your base would make your item links like <code>http://example.org/items/name/</code>. If you leave these blank the default will be used.</p>
END;
        
        echo $html;
    }
    
    public function settings_currency_section() {
    }
    
    public function settings_subdomain_field() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (empty($salesbinder_settings['subdomain']))
            $salesbinder_settings['subdomain'] = '';
        
        $html = <<<END
<input id="salesbinder_subdomain_field" size="15" type="text" name="salesbinder_settings[subdomain]"value="{$salesbinder_settings['subdomain']}" />
<span class="example"> .salesbinder.com</span>
END;
        
        echo $html;
    }
    
    public function settings_api_key_field() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (empty($salesbinder_settings['api_key']))
            $salesbinder_settings['api_key'] = '';
        
        $html = <<<END
<input id="salesbinder_api_key_field" size="40" type="text" name="salesbinder_settings[api_key]" value="{$salesbinder_settings['api_key']}" />
END;
        
        echo $html;
    }
    
    public function settings_interval_field() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (empty($salesbinder_settings['interval']))
            $salesbinder_settings['interval'] = 'hourly';
        
        $options = '';
        foreach (wp_get_schedules() as $option => $schedule)
            $options .= "<option value=\"{$option}\"" . ($option == $salesbinder_settings['interval'] ? ' selected' : '') . ">{$schedule['display']}</option>";
        
        $html = <<<END
<select id="salesbinder_interval_field" name="salesbinder_settings[interval]">
    {$options}
</select>
END;
        
        echo $html;
    }
    
    public function settings_currency_symbol_field() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (!isset($salesbinder_settings['currency_symbol']))
            $salesbinder_settings['currency_symbol'] = '$';
        
$html = <<<END
<input id="salesbinder_currency_symbol_field" size="4" type="text" name="salesbinder_settings[currency_symbol]" value="{$salesbinder_settings['currency_symbol']}" />
END;
        
        echo $html;
    }
    
    public function settings_currency_alignment_field() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (empty($salesbinder_settings['currency_alignment']))
            $salesbinder_settings['currency_alignment'] = 'left';
        
        $options = '';
        foreach (array('left' => 'Left', 'right' => 'Right') as $option => $name)
            $options .= "<option value=\"{$option}\"" . ($option == $salesbinder_settings['currency_alignment'] ? ' selected' : '') . ">{$name}</option>";
        
        $html = <<<END
<select id="salesbinder_currency_alignment_field" name="salesbinder_settings[currency_alignment]">
    {$options}
</select>
END;
        
        echo $html;
    }
    
    public function settings_dec_point_field() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (empty($salesbinder_settings['dec_point']))
            $salesbinder_settings['dec_point'] = '.';
        
        $options = '';
        foreach (array('.', ',') as $option)
            $options .= "<option value=\"{$option}\"" . ($option == $salesbinder_settings['dec_point'] ? ' selected' : '') . ">{$option}</option>";
        
        $html = <<<END
<select id="salesbinder_dec_point_field" name="salesbinder_settings[dec_point]">
    {$options}
</select>
END;
        
        echo $html;
    }
    
    public function settings_thousands_sep_field() {
        $salesbinder_settings = get_option('salesbinder_settings');
                
        if (!isset($salesbinder_settings['thousands_sep']))
            $salesbinder_settings['thousands_sep'] = ',';
        
        $options = '';
        foreach (array('.', ',', ' ') as $option)
            $options .= "<option value=\"{$option}\"" . ($option == $salesbinder_settings['thousands_sep'] ? ' selected' : '') . ">{$option}</option>";
        
        $html = <<<END
<select id="salesbinder_thousands_sep_field" name="salesbinder_settings[thousands_sep]">
    {$options}
</select>
END;
        
        echo $html;
    }
    
    public function settings_slug_field() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (empty($salesbinder_settings['slug']))
            $salesbinder_settings['slug'] = '';
        
$html = <<<END
<input id="salesbinder_slug_field" size="30" type="text" name="salesbinder_settings[slug]" value="{$salesbinder_settings['slug']}" />
END;
        
        echo $html;
    }
    
    public function settings_validate($input) {
        $options = get_option('salesbinder_settings');
        
        $input['subdomain'] = trim($input['subdomain']);
        if (!empty($input['subdomain']) && !preg_match('/^[a-z0-9-_]+$/i', $input['subdomain']))
            add_settings_error('salesbinder_settings_notices', 'salesbinder_subdomain_field', __('The subdomain you entered appears to be invalid.'), 'error');
        else
            $options['subdomain'] = $input['subdomain'];
        
        $input['api_key'] = trim($input['api_key']);
        if (!empty($input['api_key']) && !preg_match('/^[a-z0-9]{40}$/i', $input['api_key']))
            add_settings_error('salesbinder_settings_notices', 'salesbinder_api_key_field', __('The API key you entered must be 40 characters long. Please verify it and try again.'), 'error');
        else
            $options['api_key'] = $input['api_key'];
        
        if (!empty($input['interval']))
            $options['interval'] = $input['interval'];
        
        $options['currency_symbol'] = $input['currency_symbol'];
        $options['currency_alignment'] = $input['currency_alignment'];
        
        if (empty($input['dec_point']))
            add_settings_error('salesbinder_settings_notices', 'salesbinder_dec_point_field', __('The decimal separator cannot be blank.'), 'error');
        else
            $options['dec_point'] = $input['dec_point'];
        
        $options['thousands_sep'] = $input['thousands_sep'];
        $options['slug'] = trim($input['slug']);
        
        if (!get_settings_errors('salesbinder_settings_notices')) {
            add_settings_error('salesbinder_settings_notices', 'salesbinder_settings', __('Your SalesBinder settings have been updated successfully.'), 'updated');
            
            wp_clear_scheduled_hook('salesbinder_cron');
            wp_schedule_event(time(), $options['interval'], 'salesbinder_cron');
        }
        
        return $options;
    }
    
    public function admin_init() {
        register_setting('salesbinder_settings', 'salesbinder_settings', array($this, 'settings_validate'));
        
        add_settings_section('api', 'API Settings', array($this, 'settings_api_section'), 'salesbinder');
        add_settings_field('subdomain', 'Subdomain', array($this, 'settings_subdomain_field'), 'salesbinder', 'api');
        add_settings_field('api_key', 'API Key', array($this, 'settings_api_key_field'), 'salesbinder', 'api');
        
        add_settings_section('scheduling', 'Schedule Settings', array($this, 'settings_scheduling_section'), 'salesbinder');
        add_settings_field('interval', 'Interval', array($this, 'settings_interval_field'), 'salesbinder', 'scheduling');
        
        add_settings_section('currency', 'Currency Settings', array($this, 'settings_currency_section'), 'salesbinder');
        add_settings_field('currency_symbol', 'Currency Symbol', array($this, 'settings_currency_symbol_field'), 'salesbinder', 'currency');
        add_settings_field('currency_alignment', 'Currency Alignment', array($this, 'settings_currency_alignment_field'), 'salesbinder', 'currency');
        add_settings_field('dec_point', 'Decimal Separator', array($this, 'settings_dec_point_field'), 'salesbinder', 'currency');
        add_settings_field('thousands_sep', 'Thousands Separator', array($this, 'settings_thousands_sep_field'), 'salesbinder', 'currency');
        
        add_settings_section('permalinks', 'Permalinks Settings', array($this, 'settings_permalinks_section'), 'salesbinder');
        add_settings_field('slug', 'Base', array($this, 'settings_slug_field'), 'salesbinder', 'permalinks');
    }
    
    public function load() {
        $screen = get_current_screen();
        if (WPSalesBinder::POST_TYPE == $screen->id && $screen->action == 'add')
        		wp_die(__('Invalid post type.'));
    }
    
    public function parse_query($query) {
        global $pagenow,
               $post_type;
        
        if ($pagenow == 'edit.php' && WPSalesBinder::POST_TYPE == $post_type && isset($_REQUEST['refresh']) && !$this->parsed_query) {
            $this->parsed_query = true;
            
            if ($this->cron())
                set_transient('salesbinder_refreshed', true);
            
            wp_redirect(add_query_arg(array(
                'refreshed' => 'true'
            ), remove_query_arg(array('trashed', 'refresh', 'refreshed', 'ids'), wp_get_referer())));
            exit();
        }
    }
    
    public function admin_notices() {
        global $current_screen;
        
        if (in_array($current_screen->id, array('dashboard', 'edit-' . WPSalesBinder::POST_TYPE))) {
            $options = get_option('salesbinder_settings');
            if (empty($options['subdomain']) || empty($options['api_key'])) {
                $message = __('<b>SalesBinder for WordPress is almost ready.</b> You must <a href="/wp-admin/edit.php?post_type=salesbinder_item&page=wp-salesbinder/salesbinder.php">enter your subdomain and API key</a> for it to work.');
                echo "<div id=\"salesbinder-warning\" class=\"updated fade\"><p>{$message}</p></div>";
            } else {
                $options = get_option('salesbinder_error');
                if ($options) {
                    $message = __('<b>SalesBinder for WordPress failed to update your items.</b> Please <a href="/wp-admin/edit.php?post_type=salesbinder_item&page=wp-salesbinder/salesbinder.php">verify your subdomain and API key</a>.');
                    echo "<div id=\"salesbinder-error\" class=\"error fade\"><p>{$message}</p></div>";
                }
            }
        }
        
        if ('edit-' . WPSalesBinder::POST_TYPE == $current_screen->id && isset($_REQUEST['refreshed'])) {
            if (get_transient('salesbinder_refreshed')) {
                $message = __('The items have been refreshed from your SalesBinder account.');
                echo "<div class=\"updated fade\"><p><b>{$message}</b></p></div>";
                
                delete_transient('salesbinder_refreshed');
            }
        } elseif (WPSalesBinder::POST_TYPE . '_page_wp-salesbinder/salesbinder' == $current_screen->id && isset($_REQUEST['settings-updated'])) {
            settings_errors('salesbinder_settings_notices');
            
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        }
    }
    
    public function wp_before_admin_bar_render() {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('new-' . WPSalesBinder::POST_TYPE);
    }
    
    public function favorite_actions($actions) {
        if (isset($actions['post-new.php?post_type=' . WPSalesBinder::POST_TYPE]))
        		unset($actions['post-new.php?post_type=' . WPSalesBinder::POST_TYPE]);
        return $actions;
    }
    
    public function admin_print_styles() {
        global $current_screen;
        
        $icon = plugins_url('salesbinder-32x32.png', __FILE__);
        
        if ('edit-' . WPSalesBinder::POST_TYPE == $current_screen->id || WPSalesBinder::POST_TYPE == $current_screen->id)
            $styles = <<<END
<style type="text/css">
    .icon32.icon32-posts-salesbinder_item { background-image: url({$icon}) !important; background-position: 0 0 !important; background-size: auto !important }
    .add-new-h2, .view-switch, body.no-js .tablenav select[name^=action], body.no-js #doaction, body.no-js #doaction2, #salesbinder_category-adder { display: none }
    .fixed .column-item_number, .fixed .column-price, .fixed .column-salesbinder_category { width: 15% }
    #salesbinder_item-refresh-submit { margin: 1px 8px 0 0 }
</style>
END;
        elseif ('edit-salesbinder_category' == $current_screen->id)
            $styles = <<<END
<style type="text/css">
    .icon32.icon32-posts-salesbinder_item { background-image: url({$icon}) !important; background-position: 0 0 !important; background-size: auto !important }
    #col-left { display: none }
    #col-right { width: 100% }
    #col-right .col-wrap { padding: 0 }
</style>
END;
        elseif (WPSalesBinder::POST_TYPE . '_page_wp-salesbinder/salesbinder' == $current_screen->id)
            $styles = <<<END
<style type="text/css">
    .icon32.icon32-posts-salesbinder_item { background-image: url({$icon}) !important; background-position: 0 0 !important; background-size: auto !important }
</style>
END;
        else
            return;
        
        echo $styles;
    }
    
    public function manage_edit_columns($columns) {
        $end = array_splice($columns, 2);
        return array_merge($columns, array(
            'item_number' => __('Item Number'),
            'price' => __('Price'),
            'salesbinder_category' => __('Categories'),
            'comments' => '<span class="vers"><div title="Comments" class="comment-grey-bubble"></div></span>'
        ), $end);
    }
    
    public function manage_posts_custom_colum($column, $post_id) {
        switch ($column) {
            case 'price':
                sb_the_price();
                
                break;
            case 'item_number':
                sb_the_item_number();
                
                break;
            case 'salesbinder_category':
                $terms = get_the_term_list($post_id, 'salesbinder_category', '', ',', '');
            		if (is_string($terms))
              			echo $terms;
                
                break;
        }
    }
    
    public function admin_head_edit() {
        global $current_screen;
        
        if ('edit-' . WPSalesBinder::POST_TYPE != $current_screen->id)
            return;
        
        $options = get_option('salesbinder_settings');
        if (empty($options['api_key']) || empty($options['subdomain']))
            return;
        
        if (!empty($_REQUEST['post_status']) && $_REQUEST['post_status'] == 'trash')
            return;
        
        $submit_button = get_submit_button(__('Refresh from SalesBinder'), 'button', 'refresh', false, array('id' => 'salesbinder_item-refresh-submit'));
        
        $javascript = <<<END
<script>
    jQuery(document).ready(function($) {
        $('<div class="alignleft actions">{$submit_button}</div>').insertAfter($('.tablenav.top .alignleft.actions:last'));
    });
</script>
END;
        echo $javascript;
    }
    
    public function cron() {
        $salesbinder_settings = get_option('salesbinder_settings');
        
        if (empty($salesbinder_settings['api_key']) || empty($salesbinder_settings['subdomain']))
            return;
        
        $api_key = $salesbinder_settings['api_key'];
        $subdomain = $salesbinder_settings['subdomain'];
        
        $post_ids = array();
        $page = 1;
        while (true) {
            $response = wp_remote_get("http://{$subdomain}.salesbinder.com/api/items.json?page={$page}", array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode("{$api_key}:x")
                )
            ));
            $page++;
            if (is_array($response) && $response['response']['code'] == 200) {
                $response = json_decode($response['body'], true);
                if (!empty($response['count']))
                    foreach ($response['Items'] as $item) {
                        if (!$item['Item']['published'])
                            continue;
                        
                        $posts = get_posts(array(
                            'post_type' => WPSalesBinder::POST_TYPE,
                            'meta_key' => 'ID',
                            'meta_value' => $item['Item']['id'],
                            'post_status' => array('trash', 'publish')
                        ));
                        
                        $term_id = null;
                        $salesbinder_categories = json_decode(get_option('salesbinder_categories'), true);
                        if (!$salesbinder_categories)
                            $salesbinder_categories = array();
                        if (!empty($salesbinder_categories[$item['Category']['id']])) {
                            $term = get_term($salesbinder_categories[$item['Category']['id']], 'salesbinder_category');
                            if ($term) {
                                wp_update_term(($term_id = $term->term_id), 'salesbinder_category', array(
//                                    'name' => $item['Category']['name'],
                                    'description' => $item['Category']['description']
                                ));
                            }
                        }
                        if (!$term_id) {
                            $term = wp_insert_term($item['Category']['name'], 'salesbinder_category', array(
                                'description' => $item['Category']['description']
                            ));
                            if (is_array($term))
                                $term_id = $salesbinder_categories[$item['Category']['id']] = $term['term_id'];
                        }
                        update_option('salesbinder_categories', json_encode($salesbinder_categories));
                        
                        $post_id = null;
                        if (!$posts) {
                            $post_id = wp_insert_post(array(
                                'post_title' => $item['Item']['name'],
                                'post_content' => $item['Item']['description'],
                                'post_status' => 'publish',
                                'post_type' => WPSalesBinder::POST_TYPE,
                                'post_date' => date('Y-m-d H:i:s', strtotime($item['Item']['created']))
                            ));
                        } else {
                            wp_update_post(array(
                                'ID' => ($post_id = $posts[0]->ID),
                                'post_title' => $item['Item']['name'],
                                'post_content' => $item['Item']['description']
                            ));
                        }
                        
                        if ($post_id) {
                            $post_ids[] = $post_id;
                            
                            update_post_meta($post_id, 'ID', $item['Item']['id']);
                            update_post_meta($post_id, 'Price', $item['Item']['price']);
                            update_post_meta($post_id, 'Quantity', $item['Item']['quantity']);
                            
                            if (!empty($item['Item']['sku'])) {
                                update_post_meta($post_id, 'SKU', $item['Item']['sku']);
                            } else {
                                delete_post_meta($post_id, 'SKU');
                            }
                            
                            if (!empty($item['Item']['serial_number'])) {
                                update_post_meta($post_id, 'Serial Number', $item['Item']['serial_number']);
                            } else {
                                delete_post_meta($post_id, 'Serial Number');
                            }
                            
                            if (!empty($item['Item']['item_number'])) {
                                update_post_meta($post_id, 'Item Number', $item['Item']['item_number']);
                            } else {
                                delete_post_meta($post_id, 'Item Number');
                            }
                            
                            delete_post_meta($post_id, 'Image');
                            if (!empty($item['Image']))
                                foreach ($item['Image'] as $image) {
                                    add_post_meta($post_id, 'Image', $image['url_original']);
                                }
                            
                            $custom_keys = get_post_custom_keys($post_id);
                            if ($custom_keys)
                                foreach ($custom_keys as $custom_key)
                                    if (!in_array($custom_key, array('ID', 'Price', 'Quantity', 'Serial Number', 'Item Number', 'Image')) && stripos($custom_key, '_') !== 0) {
                                        delete_post_meta($post_id, $custom_key);
                                    }
                            
                            if (!empty($item['ItemDetail']))
                                foreach ($item['ItemDetail'] as $item_detail) {
                                    if (!$item_detail['CustomField'])
                                        continue;
                                    
                                    add_post_meta($post_id, $item_detail['CustomField']['name'], $item_detail['value']);
                                }
                            
                            if ($term_id)
                                wp_set_post_terms($post_id, array($term_id), 'salesbinder_category');
                        }
                    }
                
                if ($page > $response['pages'])
                    break;
            } else {
                update_option('salesbinder_error', true);
                return;
            }
        }
        
        if ($posts) {
            $posts = get_posts(array(
                'post__not_in' => $post_ids,
                'post_type' => WPSalesBinder::POST_TYPE
            ));
            if ($posts)
                foreach ($posts as $post)
                    wp_delete_post($post->ID, true);
        }
        
        delete_option('salesbinder_error');
        
        return true;
    }
}

function sb_get_the_price($post_id = null) {
    global $post;
    
    if (!$post_id)
        $post_id = $post->ID;
    
    $salesbinder_settings = get_option('salesbinder_settings');
    
    if (!isset($salesbinder_settings['currency_symbol']))
        $salesbinder_settings['currency_symbol'] = '$';
    
    if (empty($salesbinder_settings['currency_alignment']))
        $salesbinder_settings['currency_alignment'] = 'left';
    
    if (empty($salesbinder_settings['dec_point']))
        $salesbinder_settings['dec_point'] = '.';
    
    if (!isset($salesbinder_settings['thousands_sep']))
        $salesbinder_settings['thousands_sep'] = ',';
    
    $price = get_post_meta($post_id , 'Price', true);
    if ($price)
        return ($salesbinder_settings['currency_alignment'] == 'left' ? $salesbinder_settings['currency_symbol'] : '') . number_format($price, 2, $salesbinder_settings['dec_point'], $salesbinder_settings['thousands_sep']) . ($salesbinder_settings['currency_alignment'] == 'right' ? $salesbinder_settings['currency_symbol'] : '');
}

function sb_the_price($post_id = null) {
    echo sb_get_the_price($post_id);
}

function sb_get_the_sku($post_id = null) {
    global $post;
    
    if (!$post_id)
        $post_id = $post->ID;
    
    return get_post_meta($post_id , 'SKU', true);
}

function sb_the_sku($post_id = null) {
    echo sb_get_the_sku($post_id);
}

function sb_get_the_quantity($post_id = null) {
    global $post;
    
    if (!$post_id)
        $post_id = $post->ID;
    
    return get_post_meta($post_id , 'Quantity', true);
}

function sb_the_quantity($post_id = null) {
    echo sb_get_the_quantity($post_id);
}

function sb_get_the_serial_number($post_id = null) {
    global $post;
    
    if (!$post_id)
        $post_id = $post->ID;
    
    return get_post_meta($post_id , 'Serial Number', true);
}

function sb_the_serial_number($post_id = null) {
    echo sb_get_the_serial_number($post_id);
}

function sb_get_the_item_number($post_id = null) {
    global $post;
    
    if (!$post_id)
        $post_id = $post->ID;
    
    return get_post_meta($post_id , 'Item Number', true);
}

function sb_the_item_number($post_id = null) {
    echo sb_get_the_item_number($post_id);
}

function sb_get_the_custom_fields($name = null, $post_id = null) {
    global $post;
    
    if (!$post_id)
        $post_id = $post->ID;
    
    $custom_fields = array();
    $custom_keys = get_post_custom_keys($post_id);
    if ($custom_keys)
        foreach ($custom_keys as $custom_key)
            if (!in_array($custom_key, array('ID', 'Price', 'Quantity', 'Serial Number', 'Item Number', 'Image')) && stripos($custom_key, '_') !== 0 && (!isset($name) || $custom_key == $name)) {
                $custom_values = get_post_meta($post_id, $custom_key);
                if ($custom_values)
                    foreach ($custom_values as $custom_value)
                        $custom_fields[] = array(
                            'name' => $custom_key,
                            'value' => $custom_value
                        );
            }
    
    return $custom_fields;
}

function sb_the_custom_fields($name = null, $post_id = null) {
    $custom_fields = sb_get_the_custom_fields($name, $post_id);
    if ($custom_fields) {
        echo '<ul>';
        foreach ($custom_fields as $custom_field)
            echo "<li>{$custom_field['name']}: {$custom_field['value']}</li>";
        echo '</ul>';
    }
}

function sb_get_the_images($post_id = null, $attr = '') {
    global $post;
    
    if (!$post_id)
        $post_id = $post->ID;
    
    $html = array();
    $images = get_post_meta($post_id, 'Image');
    if ($images) {
        $default_attr = array(
            'class' => 'salesbinder_attachment',
        );
        
        foreach ($images as $image) {
            $attr = array(
                'src' => $image,
                'alt' => trim(strip_tags($post->title))
            );
            
            $attr = wp_parse_args($attr, $default_attr);
            $attr = array_map('esc_attr', $attr);
            
            $img = rtrim('<img');
            foreach ($attr as $name => $value)
                $img .= " {$name}=\"{$value}\"";
            $img .= ' />';
            
            $html[] = $img;
        }
    }
    
    return $html;
}

function sb_the_images($post_id = null, $attr = '') {
    echo implode(sb_get_the_images($post_id, $attr));
}

function sb_get_the_image($id, $attr = '', $post_id = null) {
    $images = sb_get_the_images($post_id, $attr);
    if ($images && !empty($images[$id - 1]))
        return $images[$id - 1];
    
    return false;
}

function sb_the_image($id, $attr = '', $post_id = null) {
    $image = sb_get_the_image($id, $attr, $post_id);
    if ($image)
        echo $image;
}
