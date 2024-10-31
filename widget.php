<?php

class WPSalesBinderCategoriesWidget extends WP_Widget {
    public function __construct() {
        parent::__construct('salesbinder_categories', __('SalesBinder Categories'), array(
            'classname' => 'widget_categories',
            'description' => __('A list or dropdown of SalesBinder categories')
        ));
    }
    
    public function widget($args, $instance) {
        extract($args);
        
        $title = apply_filters('widget_title', empty($instance['title']) ? __('SalesBinder Categories') : $instance['title'], $instance, $this->id_base);
        $c = ! empty($instance['count']) ? '1' : '0';
        $d = ! empty($instance['dropdown']) ? '1' : '0';
        
        echo $before_widget;
        if ($title)
            echo "{$before_title}{$title}{$after_title}";
        
        $cat_args = array(
            'taxonomy' => 'salesbinder_category',
            'name' => 'salesbinder_cat',
            'orderby' => 'name',
            'show_count' => $c
        );
    
        if ($d) {
            $cat_args['show_option_none'] = __('Select Category');
            
            $cat_args = apply_filters('widget_salesbinder_categories_dropdown_args', $cat_args);
            
            $options = '';
            if (!empty($cat_args['show_option_none'])) {
                $options .= "<option value=\"-1\">{$cat_args['show_option_none']}</option>";
            }
            
            $cats = get_categories($cat_args);
            foreach ($cats as $cat) {
                global $wp_query;
                
                $link = get_term_link($cat, $cat->taxonomy);
                $count = $c ? " ({$cat->count})" : '';
                $selected = !empty($wp_query->query_vars['salesbinder_category']) && $wp_query->query_vars['salesbinder_category'] == $cat->slug ? ' selected' : '';
                
                $options .= "<option value=\"{$link}\"{$selected}>{$cat->name}{$count}</option>";
            }
            
            $home_url = home_url();
            $html = <<<END
<select name="salesbinder_cat" id="salesbinder_cat">
    {$options}
</select>
<script>
    var salesbinder_dropdown = document.getElementById('salesbinder_cat');
  	function onCatChange() {
    		if (salesbinder_dropdown.options[salesbinder_dropdown.selectedIndex].value != -1) {
      			location.href = salesbinder_dropdown.options[salesbinder_dropdown.selectedIndex].value;
    		}
  	}
  	salesbinder_dropdown.onchange = onCatChange;
</script>
END;
            echo $html;
        } else {
            echo '<ul>';
            
            $cat_args['title_li'] = '';
            wp_list_categories(apply_filters('widget_categories_args', $cat_args));
            
            echo '</ul>';
        }
        
        echo $after_widget;
    }
    
    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['count'] = !empty($new_instance['count']) ? 1 : 0;
        $instance['dropdown'] = !empty($new_instance['dropdown']) ? 1 : 0;
        
        return $instance;
    }
    
    public function form($instance) {
    		//Defaults
    		$instance = wp_parse_args((array)$instance, array('title' => ''));
    		$title = esc_attr($instance['title']);
    		$count = isset($instance['count']) ? (bool)$instance['count'] :false;
    		$dropdown = isset($instance['dropdown']) ? (bool)$instance['dropdown'] : false;
    		
    		$d_checked = checked($dropdown, true, false);
    		$c_checked = checked($count, true, false);
    		
        $html = <<<END
<p><label for="{$this->get_field_id('title')}">Title:</label>
<input class="widefat" id="{$this->get_field_id('title')}" name="{$this->get_field_name('title')}" type="text" value="{$title}" /></p>

<p><input type="checkbox" class="checkbox" id="{$this->get_field_id('dropdown')}" name="{$this->get_field_name('dropdown')}"{$d_checked} />
<label for="{$this->get_field_id('dropdown')}">Display as dropdown</label><br />

<input type="checkbox" class="checkbox" id="{$this->get_field_id('count')}" name="{$this->get_field_name('count')}"{$c_checked} />
<label for="{$this->get_field_id('count')}">Show post counts</label></p>
END;
        
        echo $html;
    }
}
