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
		|| in_array(strtolower($context['current_action']), ['admin', 'jslocale', 'helpadmin', 'printpage', 'mentions', 'post',
			'search', 'calendar', 'memberlist', 'help', 'who', 'stats', 'login', 'reminder', 'register', 'contact',
			'moderate', 'xmlhttp', 'xmlpreview', 'quotefast', 'jsmodify', 'pm', 'forum']))
	{
		return;
	}

	// Load the required items
	Txt::load('Fancybox');
	loadCSSFile(['fancybox/jquery.fancybox.css'], ['stale' => '?v=3.5.7']);
	loadJavascriptFile(['fancybox/jquery.fancybox.min.js'], ['stale' => '?v=3.5.7']);

	// Output the necessary JS commands to initialize Fancybox and disable core lightbox/expander
	build_javascript();
}

/**
 * Builds the JavaScript ready function to enable fancybox
 */
function build_javascript()
{
	global $modSettings, $txt;

	$disable_img_in_url = !empty($modSettings['fancybox_disable_img_in_url']) ? 'true' : 'false';

	// Build the JavaScript based on ACP choices
	$javascript = '
		document.addEventListener("DOMContentLoaded", function() {
			// All the attachment links get fancybox data, remove onclick events and prevent core lightbox
			$("a[id^=link_], a[data-lightboximage]").each(function(){
				let tag = $(this);

				tag.attr("data-fancybox", "").removeAttr("onclick");

				// No rel tag yet? then add one
				if (!tag.attr("rel")) {
					if (tag.data("lightboxmessage") && tag.data("lightboxmessage") !== 0)
					{
						tag.attr("rel", "gallery_" + tag.data("lightboxmessage"));
						tag.attr("data-fancybox", "gallery_" + tag.data("lightboxmessage"));
					}
					else
					{
						tag.attr("rel", "gallery");
						tag.attr("data-fancybox", "gallery");
					}
				}

				// Remove data-lightboximage so deferred theme.js $(function) will not bind click.elk_lb
				tag.removeAttr("data-lightboximage").off("click.elk_lb");
			});

			// Disable ElkArte core lightbox by removing data-lightboximage attribute from any remaining elements
			$("[data-lightboximage]").removeAttr("data-lightboximage").off("click.elk_lb");

			// Find any gallery images used in signatures, remove from message slideshow
			let count = 0;
			$("div.signature figure.item_image > a").each(function() {
				$(this).attr("rel", "mgallery_" + count);
				$(this).attr("data-fancybox", "mgallery_" + count);
				count++;
			});';

	if (!empty($modSettings['fancybox_bbc_img']))
	{
		$javascript .= '
			
			// Disable ElkArte core BBC inline expander by removing data-bbcexpandimage attribute
			$("[data-bbcexpandimage]").removeAttr("data-bbcexpandimage").off("click.elk_bbc");

			// Process BBC images unobtrusively on the client side
			$("img.bbc_img").each(function() {
				let $img = $(this),
					title = $img.attr("title") || "",
					alt = $img.attr("alt") || "",
					isNoFb = title === "nofb" || title.indexOf("nofb") !== -1 || alt === "nofb" || $img.attr("data-nofb"),
					$parentA = $img.closest("a");

				// Skip if inside an attachment link, gallery item link, or marked with nofb
				if ($parentA.is("[data-lightboximage], [id^=link_], [data-fancybox^=mgallery_]") || isNoFb) {
					return;
				}

				// Determine gallery name based on enclosing message/post
				let galleryName = "gallery";
				let $msg = $img.closest("[data-msgid], section[id^=msg_], div[id^=msg_]");
				if ($msg.length) {
					let msgId = $msg.data("msgid") || ($msg.attr("id") || "").replace(/^msg_/, "");
					if (msgId && msgId !== "0") {
						galleryName = "gallery_" + msgId;
					}
				}

				if ($img.closest(".signature, [id$=_signature]").length) {
					galleryName = "gallery_sig_" + count;
					count++;
				}

				if ($parentA.length) {
					// Image is inside a link [url=...][img]...[/img][/url]
					if (!' . $disable_img_in_url . ') {
						$parentA.attr("data-fancybox", galleryName);
						if (!$parentA.attr("rel")) {
							$parentA.attr("rel", galleryName);
						}
					}
				} else {
					// Standalone image: wrap in anchor for Fancybox
					let caption = title || alt;
					let $wrap = $("<a></a>")
						.attr("href", $img.attr("src"))
						.attr("data-fancybox", galleryName)
						.attr("rel", galleryName)
						.addClass("fancybox");

					if (caption) {
						$wrap.attr("data-caption", caption);
					}

					$img.wrap($wrap);
				}
			});';
	}

	$javascript .= '
			
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
		});

		$(function() {
			$("[data-lightboximage], a[id^=link_], [data-fancybox]").removeAttr("data-lightboximage").off("click.elk_lb");
			' . (!empty($modSettings['fancybox_bbc_img']) ? '$("[data-bbcexpandimage], img.bbc_img").removeAttr("data-bbcexpandimage").off("click.elk_bbc");' : '') . '
		});';

	theme()->addInlineJavascript($javascript, true);
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
