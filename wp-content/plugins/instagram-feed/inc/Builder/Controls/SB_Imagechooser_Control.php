<?php
/**
 * Customizer Builder
 * Image Chooser Field Control
 *
 * @since 6.0
 */
namespace InstagramFeed\Builder\Controls;

if(!defined('ABSPATH'))	exit;

class SB_Imagechooser_Control extends SB_Controls_Base{

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
		return 'imagechooser';
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
		<div class="sb-control-imagechooser-ctn sbi-fb-fs">
			<div class="sbi-fb-fs">
				<input type="text" class="sb-control-imagechooser-input sbi-fb-fs" :class="checkNotEmpty(<?php echo $controlEditingTypeModel ?>[control.id]) ? 'sb-control-imagechooser-padding' : ''" v-model="<?php echo $controlEditingTypeModel ?>[control.id]" :placeholder="control.placeholder ? control.placeholder : <?php echo $controlEditingTypeModel ?>[control.id]" disabled>
				<div class="sb-control-imagechooser-clear sbi-fb-tltp-parent" v-if="checkNotEmpty(<?php echo $controlEditingTypeModel ?>[control.id])">
					<div class="sb-control-imagechooser-clear-icon" @click.prevent.default="changeSettingValue(control.id, '')"></div>
					<div class="sbi-fb-tltp-elem"><span>{{genericText.clear.replace(/ /g,"&nbsp;")}}</span></div>
				</div>
			</div>
			<div class="sb-control-imagechooser-btn" @click.prevent.default="imageChooser( control.id )">
				<div v-html="svgIcons['imageChooser']"></div>
				<span v-html="checkNotEmpty(<?php echo $controlEditingTypeModel ?>[control.id]) ? genericText.change : genericText.addImage.replace(/ /g,'&nbsp;')"></span>
			</div>
		</div>
		<?php
	}

}