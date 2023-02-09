<?php
/**
 * Customizer Builder
 * Toggle Control
 *
 * @since 4.0
 */
namespace InstagramFeed\Builder\Controls;

if(!defined('ABSPATH'))	exit;

class SB_Toggle_Control extends SB_Controls_Base{

	/**
	 * Get control type.
	 *
	 * Getting the Control Type
	 *
	 * @since 4.0
	 * @access public
	 *
	 * @return string
	*/
	public function get_type(){
		return 'toggle';
	}

	/**
	 * Output Control
	 *
	 *
	 * @since 4.0
	 * @access public
	 *
	 * @return HTML
	*/
	public function get_control_output($controlEditingTypeModel){
		?>
		<div class="sb-control-toggle-ctn sbi-fb-fs">
			<div class="sb-control-toggle-elm sbi-fb-fs sb-tr-2" data-active="true">
				<div class="sb-control-toggle-deco sb-tr-1"></div>
				<div class="sb-control-toggle-icon" v-if="control.toggle.icon" v-html="svgIcons[control.toggle.icon]"></div>
				<div class="sb-control-label">{{control.toggle.label}}</div>
			</div>
		</div>
		<?php
	}

}