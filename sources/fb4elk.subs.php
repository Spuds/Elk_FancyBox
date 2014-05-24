<?php

/**
 * @package "FancyBox 4 ElkArte" Addon for Elkarte
 * @author Spuds
 * @copyright (c) 2011-2014 Spuds
 * @license This Source Code is subject to the terms of the Mozilla Public License
 * version 1.1 (the "License"). You can obtain a copy of the License at
 * http://mozilla.org/MPL/1.1/.
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ilt_fb4elk()
 *
 * - Integrate_load_theme, Called from load.php
 * - Used to add items to the loaded theme (css / js)
 */
function ilt_fb4elk()
{
	global $context, $txt, $modSettings;

	// If its off, just return
	if (empty($modSettings['fancybox_enabled']))
		return;

	// If we are in an area where we never want this, return
	if (in_array($context['current_action'], array('admin', 'helpadmin', 'printpage')))
		return;

	// Load in the must have items
	loadLanguage('fb4elk');
	loadCSSFile(array('fancybox/jquery.fancybox.css', 'fancybox/jquery.fancybox-buttons.css'), array('stale' => '?v=2.1.5'));
	loadJavascriptFile(array('fancybox/jquery.fancybox.min.js', 'fancybox/helpers/jquery.fancybox-buttons.js'), array('stale' => '?v=2.1.5'));

	// Gallery thumbnails as well?
	if (!empty($modSettings['fancybox_thumbnails']))
	{
		// Lets remove the elk bbc image clicker
		addInlineJavascript('$(document).ready(function() {$("img").off("click.elk_bbc");});');

		// Load up FB
		loadCSSFile('fancybox/jquery.fancybox-thumbs.css', array('stale' => '?v=2.1.5'));
		loadJavascriptFile('fancybox/helpers/jquery.fancybox-thumbs.js', array('stale' => '?v=2.1.5'));
	}

	// Build the Javascript based on ACP choices
	$javascript = '
		$(document).ready(function() {
			// All the attachment links get a fancybox class, remove onclick events
			$("a[id^=link_]").each(function(){
				var tag = $(this);

				tag.addClass("fancybox").removeAttr("onclick");

				// no rel tag yet? then add one
				if (!tag.attr("rel"))
					tag.attr("rel", "gallery")
			});


			// Find all the attachment / bbc divs on the page
			$("div[id$=_footer]").each(function() {
				// Fancybox Galleries are created from elements who have the same "rel" tag
				var id = $(this).attr("id");
				$("#" + id + " a[rel=gallery]").attr("rel", "gallery_" + id);
			});

			// Attach FB to everything we tagged with the fancybox class
			$(".fancybox").fancybox({
				type: "image",
				padding: 10,
				arrows: true,
				loop: false,
				openEffect: "' . $modSettings['fancybox_openEffect'] . '",
				openSpeed: ' . (int) $modSettings['fancybox_openSpeed'] . ',
				closeEffect: "' . $modSettings['fancybox_closeEffect'] . '",
				closeSpeed: ' . (int) $modSettings['fancybox_closeSpeed'] . ',
				nextEffect: "' . $modSettings['fancybox_navEffect'] . '",
				nextSpeed: ' . (int) $modSettings['fancybox_navSpeed'] . ',
				prevEffect: "' . $modSettings['fancybox_navEffect'] . '",
				prevSpeed: ' . (int) $modSettings['fancybox_navSpeed'] . ',
				autoPlay: ' . (!empty($modSettings['fancybox_autoPlay']) ? 'true' : 'false') . ',
				playSpeed: ' . (int) $modSettings['fancybox_playSpeed'] . ',
				tpl: {
					error: \'<p class="errorbox">' . $txt['fancy_text_error'] . '</p>\',
					closeBtn: \'<div title="' . $txt['find_close'] . '" class="fancybox-item fancybox-close"></div>\',
					next: \'<a title="' . $txt['fancy_button_next'] . '" class="fancybox-item fancybox-next"><span></span></a>\',
					prev: \'<a title="' . $txt['fancy_button_prev'] . '" class="fancybox-item fancybox-prev"><span></span></a>\'
				},
				helpers: {
					buttons: {
						tpl: \'\'+
						\'<div id="fancybox-buttons"> \' +
							\'<ul> \' +
							\'	<li> \' +
							\'		<a class="btnPrev" title="' . $txt['fancy_button_prev'] . '" href="javascript:;"></a \' +
							\'	</li> \' +
							\'	<li> \' +
							\'		<a class="btnPlay" title="' . $txt['fancy_slideshow_start'] . '" href="javascript:;"></a> \' +
							\'	</li> \' +
							\'	<li> \' +
							\'		<a class="btnNext" title="' . $txt['fancy_button_next'] . '" href="javascript:;"></a> \' +
							\'	</li> \' +
							\'	<li> \' +
							\'		<a class="btnToggle" title="' . $txt['fancy_toggle_size'] . '" href="javascript:;"></a> \' +
							\'	</li> \' +
							\'	<li> \' +
							\'		<a class="btnClose" title="' . $txt['find_close'] . '" href="javascript:jQuery.fancybox.close();"></a> \' +
							\'	</li> \' +
							\'</ul> \' +
						\'</div>\',
						position : "' . $modSettings['fancybox_panel_position'] . '"
					},';

	if (!empty($modSettings['fancybox_thumbnails']))
		$javascript .= '
					thumbs: {
						width: 40,
						height: 40,
						position: "bottom"
					},
					overlay: {
						showEarly: true,
						closeClick : true,
					},';

	$javascript .= '
				}
			});
		});';

	addInlineJavascript($javascript, true);
}

/**
 * ibc_fb4elk()
 *
 * - Subs hook, integrate_bbc_codes hook, Called from Subs.php
 * - Used when attaching Fancybox to bbc images
 * - Replaces the standard bbc image link with one containing the fancybox class
 *
 * @param mixed[] $codes array of codes as defined for parse_bbc
 */
function ibc_fb4elk(&$codes)
{
	global $modSettings, $user_info;

	// Only attach for topics, when bbc is on and the option is checked
	if (empty($_REQUEST['topic']) || empty($modSettings['enableBBC']) || empty($modSettings['fancybox_bbc_img']))
		return;

	// Make sure the admin had not disabled img tags as well
	if (!empty($modSettings['disabledBBC']))
	{
		foreach (explode(',', $modSettings['disabledBBC']) as $tag)
			if ($tag === 'img')
				return;
	}

	// Find the img bbc tags and update how they render their HTML
	foreach ($codes as &$code)
	{
		if ($code['tag'] === 'img')
		{
			if ($user_info['is_guest'])
				$style = '';
			else
				$style = 'width:{width};height:{height}';

			if ($code['content'] == '<img src="$1" alt="" class="bbc_img" />')
				$code['content'] = '<a href="$1" class="fancybox" rel="topic"><img src="$1" alt="" class="bbc_img" /></a>';
			elseif ($code['content'] == '<img src="$1" alt="{alt}" style="width:{width};height:{height}" class="bbc_img resized" />')
				$code['content'] = '<a href="$1" class="fancybox" title="{alt}" rel="topic"><img src="$1" alt="{alt}" style="' . $style . '" class="bbc_img resized fancybox" /></a>';
		}
	}
}

/**
 * iaa_fb4elk()
 *
 * - Display Hook, integrate_prepare_display_context, called from Display.controller
 * - Used to interact with the message array before its sent to the template
 *
 * @param mixed[] $output
 * @param mixed[] $message
 */
function ipdc_fb4elk(&$output, &$message)
{
	global $modSettings;

	// Make sure we need to do anything
	if (empty($modSettings['enableBBC']) || empty($modSettings['fancybox_bbc_img']))
		return;

	// Find all the bbc images with a rel="topic" in the links and inject the gallery tag so
	// the bbc images and attachments of a message are part of the same gallery
	$output['body'] = str_replace('rel="topic"', 'rel="gallery_msg_' . $output['id'] . '_footer"', $output['body']);
}


/**
 * iaa_fb4elk()
 *
 * - Admin Hook, integrate_admin_areas, called from Admin.php
 * - Used to add/modify admin menu areas
 *
 * @param mixed[] $admin_areas
 */
function iaa_fb4elk(&$admin_areas)
{
	global $txt;

	loadlanguage('fb4elk');
	$admin_areas['config']['areas']['addonsettings']['subsections']['fancybox'] = array($txt['fancybox_title']);
}

/**
 * imm_fb4elk()
 *
 * - Admin Hook, integrate_sa_modify_modifications, called from AddonSettings.controller.php
 * - Used to add subactions to the addon area
 *
 * @param mixed[] $sub_actions
 */
function imm_fb4elk(&$sub_actions)
{
	$sub_actions['fancybox'] = array(
		'dir' => SUBSDIR,
		'file' => 'fb4elk.subs.php',
		'function' => 'fb4elk_settings',
		'permission' => 'admin_forum',
	);
}

/**
 * fb4elk_settings()
 *
 * - Defines our settings array and uses our settings class to manage the data
 */
function fb4elk_settings()
{
	global $txt, $context, $scripturl, $modSettings;

	loadlanguage('fb4elk');
	$context[$context['admin_menu_name']]['tab_data']['tabs']['fancybox']['description'] = $txt['fancybox_desc'];

	// Lets build a settings form
	require_once(SUBSDIR . '/Settings.class.php');

	// Instantiate the form
	$fbSettings = new Settings_Form();

	// Show / hide fancybox fields as required
	addInlineJavascript('
		function showhidefbOptions()
		{
			var fbThumb = document.getElementById(\'fancybox_thumbnails\').checked,
				fbThumb_dd = $(\'#fancybox_panel_position\'),
				fbThumb_dt = $(\'#setting_fancybox_panel_position\');

			// Show the thumbnail position box only if the option has been selected
			if (fbThumb === false)
			{
				// dd and the dt
				fbThumb_dd.parent().slideUp();
				fbThumb_dt.parent().slideUp();
			}
			else
			{
				fbThumb_dd.parent().slideDown();
				fbThumb_dt.parent().slideDown();
			}
		}
		showhidefbOptions();', true);

	// All the options, well at least some of them!
	$config_vars = array(
		array('check', 'fancybox_enabled', 'postinput' => $txt['fancybox_enabled_desc']),
		// Transition effects and speed
		array('title', 'fancybox_animation'),
			array('select', 'fancybox_openEffect', array(
				'elastic' => $txt['fancybox_effect_elastic'],
				'fade' => $txt['fancybox_effect_fade'],
				'none' => $txt['fancybox_effect_none'])
			),
			array('int', 'fancybox_openSpeed'),
			array('select', 'fancybox_closeEffect', array(
				'elastic' => $txt['fancybox_effect_elastic'],
				'fade' => $txt['fancybox_effect_fade'],
				'none' => $txt['fancybox_effect_none'])
			),
			array('int', 'fancybox_closeSpeed'),
			array('select', 'fancybox_navEffect', array(
				'elastic' => $txt['fancybox_effect_elastic'],
				'fade' => $txt['fancybox_effect_fade'],
				'none' => $txt['fancybox_effect_none'])
			),
			array('int', 'fancybox_navSpeed'),
		'',
			array('check', 'fancybox_thumbnails', 'onchange' => 'showhidefbOptions();'),
			array('select', 'fancybox_panel_position', array(
				'top' => $txt['fancybox_panel_top'],
				'bottom' => $txt['fancybox_panel_bottom'])
			),
		array('title', 'fancybox_other'),
			array('check', 'fancybox_autoPlay'),
			array('int', 'fancybox_playSpeed'),
			array('check', 'fancybox_bbc_img'),
	);

	// Load the settings to the form class
	$fbSettings->settings($config_vars);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Some defaults are good to have
		if (empty($_POST['fancybox_openSpeed']))
			$_POST['fancybox_openSpeed'] = 300;
		if (empty($_POST['fancybox_closeSpeed']))
			$_POST['fancybox_closeSpeed'] = 300;
		if (empty($_POST['fancybox_navSpeed']))
			$_POST['fancybox_navSpeed'] = 300;
		if (empty($_POST['fancybox_playSpeed']))
			$_POST['fancybox_playSpeed'] = 3000;

		Settings_Form::save_db($config_vars);
		redirectexit('action=admin;area=addonsettings;sa=fancybox');
	}

	// Continue on to the settings template
	$context['settings_title'] = $txt['fancybox_title'];
	$context['page_title'] = $context['settings_title'] = $txt['fancybox_settings'];
	$context['post_url'] = $scripturl . '?action=admin;area=addonsettings;sa=fancybox;save';

	if (!empty($modSettings['fancybox_thumbnails']))
		updateSettings(array('fancybox_panel_position' => 'top'));

	Settings_Form::prepare_db($config_vars);
}