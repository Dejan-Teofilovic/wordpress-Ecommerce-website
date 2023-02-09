<?php

defined( 'ABSPATH' ) || exit;

/**
 * Template Widgetized
 * 
 * This template can be overridden by copying it to yourtheme/themify-builder/template-widgetized.php.
 *
 * Access original fields: $args['mod_settings']
 * @author Themify
 */

$fields_default = array(
    'mod_title_widgetized' => '',
    'sidebar_widgetized' => '',
    'custom_css_widgetized' => '',
    'background_repeat' => '',
    'animation_effect' => ''
);
$fields_args = wp_parse_args($args['mod_settings'], $fields_default);
unset($args['mod_settings']);
$fields_default=null;
$mod_name=$args['mod_name'];
$builder_id = $args['builder_id'];
$element_id = $args['module_ID'];

$container_class = apply_filters('themify_builder_module_classes', array(
    'module', 
    'module-' . $mod_name, 
    $element_id, 
    $fields_args['custom_css_widgetized'],
    $fields_args['background_repeat'], 
), $mod_name, $element_id, $fields_args);

if(!empty($fields_args['global_styles']) && Themify_Builder::$frontedit_active===false){
    $container_class[] = $fields_args['global_styles'];
}
$container_props = apply_filters('themify_builder_module_container_props', self::parse_animation_effect($fields_args,array(
    'class' => implode(' ', $container_class)
    )), $fields_args, $mod_name, $element_id);
$args=null;
if(Themify_Builder::$frontedit_active===false){
    $container_props['data-lazy']=1;
}
?>
<!-- module widgetized -->
<div <?php echo self::get_element_attributes(self::sticky_element_props($container_props,$fields_args)); ?>>
    <?php
    $container_props=$container_class=null;
    echo Themify_Builder_Component_Module::get_module_title($fields_args,'mod_title_widgetized');

    do_action('themify_builder_before_template_content_render');
    if ($fields_args['sidebar_widgetized']!== '' && function_exists('dynamic_sidebar')){
       dynamic_sidebar($fields_args['sidebar_widgetized'] );
    }
    do_action('themify_builder_after_template_content_render');
    ?>
</div>
<!-- /module widgetized -->