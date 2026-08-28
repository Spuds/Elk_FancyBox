<?php

/**
 * @package "FancyBox 4 ElkArte" Addon for Elkarte
 * @author Spuds
 * @copyright (c) 2011-2026 Spuds
 * @license This Source Code is subject to the terms of the Mozilla Public License
 * version 1.1 (the "License"). You can obtain a copy of the License at
 * http://mozilla.org/MPL/1.1/.
 *
 * @version 2.0.0
 *
 */

use BBC\Codes;
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;

/**
 * ilt_fb4elk()
 *
 * - Integrates Fancybox resource loading and configuration into the site.
 * - Ensures that Fancybox is enabled and avoids loading the feature in specified restricted areas.
 * - Loads required CSS and JavaScript resources for Fancybox functionality.
 * - Disables the built-in lightbox support and provides custom JavaScript for Fancybox behavior.
 * - Integrate_load_theme, Called from ThemeLoader.php
 *
 * @return void
 */
function ilt_fb4elk()
{
	global $context, $modSettings;

	// If off, return
	if (empty($modSettings['fancybox_enabled']))
	{
		return;
	}

	// If we are in an area where we never want this, return
	if (!isset($context['current_action'])
		|| in_array($context['current_action'], ['admin', 'jslocale', 'helpadmin', 'printpage', 'mentions', 'post']))
	{
		return;
	}

	// Load the required items
	Txt::load('Fancybox');
	loadCSSFile(['fancybox/jquery.fancybox.css'], ['stale' => '?v=3.5.7']);
	loadJavascriptFile(['fancybox/jquery.fancybox.min.js'], ['stale' => '?v=3.5.7']);
	loadJavascriptFile(['fancybox/jquery.fb4elk.js'], ['stale' => '?v=2.0.0']);

	// Disable ElkArte lightbox and BBC expand support
	$javascript = '
	document.addEventListener("DOMContentLoaded", function() {
		fbWaitForEvent("[data-lightboximage]", "click.elk_lb", 100, 50)
		.then(() => {$("[data-lightboximage]").off("click.elk_lb")})
		.catch((error) => {if ("console" in window) console.info("fb4elk: ", error)});
	';

	// Disable ElkArte BBC image links expander
	if (!empty($modSettings['fancybox_bbc_img']))
	{
		$javascript .= '
		fbWaitForEvent("[data-bbcexpandimage]", "click.elk_bbc", 100, 50)
		.then(() => {$("[data-bbcexpandimage]").off("click.elk_bbc")})
		.catch((error) => {if ("console" in window) console.info("fb4elk: ", error)});
		';
	}

	theme()->addInlineJavascript($javascript . '});', true);

	// And output the needed JS commands
	build_javascript();
}

/**
 * Builds the JavaScript ready function to enable fancybox
 */
function build_javascript()
{
	global $modSettings, $txt;

	// Build the JavaScript based on ACP choices
	$javascript = '
		document.addEventListener("DOMContentLoaded", function() {
			// All the attachment links get fancybox data, remove onclick events
			$("a[id^=link_]").each(function(){
				let tag = $(this);

				tag.attr("data-fancybox", "").removeAttr("onclick");

				// No rel tag yet? then add one
				if (!tag.attr("rel")) {
					if (tag.data("lightboxmessage") && tag.data("lightboxmessage") !==0)
					{
						tag.attr("rel", "gallery_" + tag.data("lightboxmessage"));
						tag.attr("data-fancybox", "gallery_" + tag.data("lightboxmessage"));
					}
					else
						tag.attr("rel", "gallery");
				}
			});

			// Find any gallery images used in signatures, remove from message slideshow
			let count=0;
			$("div.signature figure.item_image > a").each(function() {
				$(this).attr("rel", "mgallery_" + count);
				$(this).attr("data-fancybox", "mgallery_" + count);
				count++;
			});

			// Attach FB to everything we tagged with the fancybox data attr
			$("[data-fancybox]").fancybox({
				type: "image",
				image: {
				    preload: true
                },
				loop: "' . !empty($modSettings['fancybox_Loop']) . '",
				animationEffect: "' . $modSettings['fancybox_openEffect'] . '",
				animationDuration: ' . (int) $modSettings['fancybox_openSpeed'] . ',
				transitionEffect: "' . $modSettings['fancybox_navEffect'] . '",
				transitionDuration: ' . (int) $modSettings['fancybox_navSpeed'] . ',
				slideShow: {
					autoStart: ' . (!empty($modSettings['fancybox_autoPlay']) ? 'true' : 'false') . ',
					speed: ' . (int) $modSettings['fancybox_playSpeed'] . ',
				},
				lang: "en",
				i18n: {
					en: {
						CLOSE: "' . $txt['find_close'] . '",
						NEXT: "' . $txt['fancy_button_next'] . '",
						PREV: "' . $txt['fancy_button_prev'] . '",
						ERROR: "' . $txt['fancy_text_error'] . '",
						PLAY_START: "' . $txt['fancy_slideshow_start'] . '",
						PLAY_STOP: "' . $txt['fancy_slideshow_pause'] . '",
						FULL_SCREEN: "' . $txt['fancy_full_screen'] . '",
						THUMBS: "' . $txt['fancy_thumbnails']  . '",
						DOWNLOAD: "' . $txt['fancy_download']  . '",
						SHARE: "' . $txt['fancy_share']  . '",
						ZOOM: "' . $txt['fancybox_effect_zoom'] . '"
					}
				},
				ajax: {
					dataType : "html",
					headers  : { "X-fancyBox": true, "User-Agent": "' . $_SERVER['HTTP_USER_AGENT'] . '"}
				},';

	if (!empty($modSettings['fancybox_thumbnails']))
	{
		$javascript .= "
				thumbs: {
					axis: " . ($modSettings['fancybox_thumbnail_position'] === 'bottom' ? '"x"' : '"y"') . ",
					autoStart: true
				},";

		if ($modSettings['fancybox_thumbnail_position'] === 'bottom')
		{
			$javascript .= "		
			baseTpl: '' +
			'<div class=\"fancybox-container\" role=\"dialog\" tabindex=\"-1\">' +
				'<div class=\"fancybox-bg\"></div>' +
				'<div class=\"fancybox-inner fancybox-inner-x\">' +
					'<div class=\"fancybox-infobar\"><span data-fancybox-index></span>&nbsp;/&nbsp;<span data-fancybox-count></span></div>' +
					'<div class=\"fancybox-toolbar\">{{buttons}}</div>' +
					'<div class=\"fancybox-navigation\">{{arrows}}</div>' +
					'<div class=\"fancybox-stage\"></div>' +
					'<div class=\"fancybox-caption\"><div class=\"fancybox-caption__body\"></div></div>' +
				'</div>' +
			'</div>',";
		}
	}

	$javascript .= '
			});
		});';

	theme()->addInlineJavascript($javascript, true);
}

/**
 * ibc_fb4elk()
 *
 * - BBC Processing Hook, integrate_bbc_codes, modifies the behavior of BBC image tags
 * - Enhances image BBC tags to be wrapped in a FancyBox link for improved user experience.
 * - integrate_bbc_codes hook, Called from Codes.php bbc_codes_parsing
 *
 * @param array $codes Array of BBC codes used for parsing
 * @return void
 */
function ibc_fb4elk(&$codes)
{
	global $modSettings;

	if (empty($modSettings['fancybox_enabled']))
	{
		return;
	}

	// Only attach for topics when bbc is on and the option is checked
	if (empty($_REQUEST['topic']) || empty($modSettings['enableBBC']) || empty($modSettings['fancybox_bbc_img']))
	{
		return;
	}

	// Make sure the admin had not disabled img tags as well
	if (!empty($modSettings['disabledBBC']))
	{
		if (in_array('img', explode(',', $modSettings['disabledBBC']), true))
		{
			return;
		}
	}

	// Find the img bbc tags and update how they render their HTML
	foreach ($codes as &$code)
	{
		if ($code[Codes::ATTR_TAG] === 'img')
		{
			if ($code[Codes::ATTR_CONTENT] === '<img src="$1" alt="" class="bbc_img" />')
			{
				$code[Codes::ATTR_CONTENT] = '<a href="$1" class="fancybox" rel="topic"><img src="$1" alt="" class="bbc_img" /></a>';
			}
			elseif ($code[Codes::ATTR_CONTENT] === '<img src="$1" title="{title}" alt="{alt}" style="{width}{height}" class="bbc_img resized" data-bbcexpandimage="1" />')
			{
				$code[Codes::ATTR_CONTENT] = '<a href="$1" class="fancybox" rel="topic" title="{title}" alt="{alt}"><img src="$1" title="{title}" alt="{alt}" style="{width}{height}" class="bbc_img resized" /></a>';
			}
		}
	}
}

/**
 * ipdc_fb4elk()
 *
 * - Hook to process and enhance BBC images and links in the output content.
 * - Fixes nested links caused by [url][img][/img][/url] constructs.
 * - Enhances BBC images with attributes for integration with FancyBox gallery functionality.
 * - Display Hook, integrate_prepare_display_context, called from Renderer.php via DisplayRenderer.php
 *
 * @param array $output The associative array containing output content, including the 'body' and 'id' keys.
 * @return void
 */
function ipdc_fb4elk(&$output)
{
	global $modSettings;

	$regex = '~<a href="([^"]*)".*(class="bbc_link").*>(<a href="([^"]*)".*(class="fancybox" rel="topic"(?: title=".*")?)>)<img.*class="bbc_img(?: resized)?" />(</a>(</a>))~Ui';

	// Make sure we need to do anything
	if (empty($modSettings['enableBBC']) || empty($modSettings['fancybox_bbc_img']))
	{
		return;
	}

	// Fix nested links caused by [url=remote][img]http://remote[/img][/url]
	// These occur as part of parse_bbc so deal with it
	$check = preg_replace_callback($regex, 'fix_url_bbc', $output['body']);
	if ($check !== null)
	{
		$output['body'] = $check;
	}

	// Find all the bbc images with a rel="topic" in the links and inject the gallery tag so
	// the bbc images and attachments of a message are part of the same gallery
	$rel = 'gallery_' . $output['id'];
	$output['body'] = str_replace('rel="topic"', 'data-lightboxmessage="' . $output['id'] . '" data-fancybox="' . $rel . '" rel="' . $rel . '"', $output['body']);
}

/**
 * Updates links to external sites to link to full image or reverts the nested link to
 * be what it was since we add a link via the updated img BBC tag.
 *
 * @param string[] $matches from the regex with the following capture groups
 *    [0] Full match
 *    [1] Outside link href
 *    [2] Outside link class=""
 *    [3] Inside link full
 *    [4] Inside link href
 *    [5] Inside link class="fancybox" rel="topic"
 *    [6] Trailing </a></a>
 *    [7] Trailing </a>
 *
 * @return string
 */
function fix_url_bbc($matches)
{
	global $modSettings;
	static $linker;

	$output = $matches[0];
	$no_fb = str_contains($matches[5], 'title="nofb"') || str_contains($matches[5], 'title="&quot;nofb&quot;"');

	// Don't want fancybox at all on linked bbc image [url=remote][img]http://remote[/img][/url] syntax
	if (!empty($modSettings['fancybox_disable_img_in_url']) || $no_fb)
	{
		// Remove the inside link and trailing </a>
		$output = str_replace([$matches[3], $matches[6]],
			['', $matches[7]],
			$output);
	}
	// Fix the links, so they link to what they did (ie the url)
	else
	{
		// Remove the inside link
		// Swap outside link class with the inside one (fancybox)
		// Replace the double </a></a> with a single
		$output = str_replace([$matches[3], $matches[2], $matches[6]],
			['', $matches[5], $matches[7]],
			$output);
	}

	return $output;
}

/**
 * Adds a new subsection related to Fancybox configuration in the admin panel.
 * Admin Menu Hook, integrate_admin_areas, called from Menu.php via generic hook
 *
 * @param object $admin_areas The admin areas object where the subsection will be added.
 * @return void
 */
function iaa_fb4elk($admin_areas)
{
	global $txt;

	Txt::load('Fancybox');

	$new_subsection = [$txt['fancybox_title']];

	$admin_areas->insertSubsection('addons', 'addonsettings', 'fancybox', $new_subsection);
}

/**
 * Adds a new sub-action for managing Fancybox settings in the admin configuration.
 * Admin action integration, modify_modifications, called from ManageSettings.php via hook
 *
 * @param array &$sub_actions The sub-actions array to which the Fancybox sub-action is added.
 * @return void
 */
function imm_fb4elk(&$sub_actions)
{
	global $context, $txt;

	$sub_actions['fancybox'] = [
		'dir' => ADDONSDIR,
		'file' => 'Fancybox.php',
		'function' => 'fb4elk_settings',
		'permission' => 'admin_forum',
	];

	$context[$context['admin_menu_name']]['tab_data']['tabs']['fancybox']['description'] = $txt['fancybox_desc'];
}

/**
 * Initializes and manages the settings page for Fancybox configuration in the admin panel.
 * This method defines configuration options, handles settings forms, manages defaults,
 * and integrates with the admin panel. It also includes dynamic scripts for updating UI
 * based on user preferences.
 *
 * @return void
 */
function fb4elk_settings()
{
	global $txt, $context, $scripturl, $modSettings;

	Txt::load('Fancybox');

	// Let's build a settings form
	$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

	// Show / hide fancybox fields as required
	theme()->addInlineJavascript('
		function showhidefbOptions()
		{
			let fbThumb = document.getElementById(\'fancybox_thumbnails\').checked,
				fbThumb_dd = $(\'#fancybox_thumbnail_position\'),
				fbThumb_dt = $(\'#setting_fancybox_thumbnail_position\');

			let fbConvert = document.getElementById(\'fancybox_disable_img_in_url\'),
				fbBBC = document.getElementById(\'fancybox_bbc_img\');

			// Show the BBC url box only if the bbc image option is enabled
			if (fbBBC.checked === false)
			{
				fbConvert.disabled = true;
			}
			else
			{
				fbConvert.disabled = false;
			}

			// Show the thumbnail position box only if the option has been selected
			if (fbThumb === true)
			{
				// dd and the dt
				fbThumb_dd.parent().slideDown();
				fbThumb_dt.parent().slideDown();
			}
			else
			{
				fbThumb_dd.parent().slideUp();
				fbThumb_dt.parent().slideUp();
			}
		}

		showhidefbOptions();', true);

	// All the options, well at least some of them!
	$config_vars = [
		['check', 'fancybox_enabled', 'postinput' => $txt['fancybox_enabled_desc']],
		// Transition effects and speed
		['title', 'fancybox_animation'],
		['select', 'fancybox_openEffect', [
			'zoom' => $txt['fancybox_effect_zoom'],
			'zoom-in-out' => $txt['fancybox_effect_elastic'],
			'fade' => $txt['fancybox_effect_fade'],
			'none' => $txt['fancybox_effect_none']]
		],
		['int', 'fancybox_openSpeed'],
		['select', 'fancybox_navEffect', [
			'slide' => $txt['fancybox_effect_slide'],
			'circular' => $txt['fancybox_effect_circular'],
			'tube' => $txt['fancybox_effect_tube'],
			'rotate' => $txt['fancybox_effect_rotate'],
			'zoom-in-out' => $txt['fancybox_effect_elastic'],
			'fade' => $txt['fancybox_effect_fade'],
			'none' => $txt['fancybox_effect_none']]
		],
		['int', 'fancybox_navSpeed'],
		['title', 'fancybox_displayOptions'],
		['check', 'fancybox_Loop'],
		['check', 'fancybox_thumbnails', 'onchange' => 'showhidefbOptions();'],
		['select', 'fancybox_thumbnail_position', [
			'side' => $txt['fancybox_thumbnails_side'],
			'bottom' => $txt['fancybox_thumbnails_bottom']],
		],
		['title', 'fancybox_other'],
		['check', 'fancybox_autoPlay'],
		['int', 'fancybox_playSpeed'],
		['check', 'fancybox_bbc_img', 'onchange' => 'showhidefbOptions();'],
		['check', 'fancybox_disable_img_in_url', 'onchange' => 'showhidefbOptions();'],
	];

	// Load the settings to the form class
	$settingsForm->setConfigVars($config_vars);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Some defaults are good to have
		if (empty($_POST['fancybox_openSpeed']))
		{
			$_POST['fancybox_openSpeed'] = 300;
		}

		if (empty($_POST['fancybox_navSpeed']))
		{
			$_POST['fancybox_navSpeed'] = 300;
		}

		if (empty($_POST['fancybox_playSpeed']))
		{
			$_POST['fancybox_playSpeed'] = 3000;
		}

		$settingsForm->setConfigVars($config_vars);
		$settingsForm->setConfigValues($_POST);
		$settingsForm->save();

		redirectexit('action=admin;area=addonsettings;sa=fancybox');
	}

	// Continue on to the settings template
	$context['settings_title'] = $txt['fancybox_title'];
	$context['page_title'] = $context['settings_title'] = $txt['fancybox_settings'];
	$context['post_url'] = $scripturl . '?action=admin;area=addonsettings;sa=fancybox;save';

	if (!empty($modSettings['fancybox_thumbnails']))
	{
		updateSettings(['fancybox_panel_position' => 'top']);
	}

	$settingsForm->prepare();
}
