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
	global $context, $modSettings;

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

	// BBC image links enabled, then remove the elk bbc image clicker
	if (!empty($modSettings['fancybox_bbc_img']))
		addInlineJavascript('$(document).ready(function() {$("img").off("click.elk_bbc");});');

	// Gallery thumbnails as well?
	if (!empty($modSettings['fancybox_thumbnails']))
	{
		// Load up FB helpers
		loadCSSFile('fancybox/jquery.fancybox-thumbs.css', array('stale' => '?v=2.1.5'));
		loadJavascriptFile('fancybox/helpers/jquery.fancybox-thumbs.js', array('stale' => '?v=2.1.5'));
	}

	// And output the neeeded JS commands
	build_javascript();

	// Build a lookup for postimage
	if (!empty($modSettings['fancybox_convert_photo_share']) && !empty($modSettings['fancybox_bbc_img']) && !in_array($context['current_action'], array('profile')))
	{
		// CORS lib
		loadJavascriptFile(array('fancybox/jquery.ajax-cross-origin.min.js'), array('stale' => '?v=2.1.5'));
		build_lookup();
	}
}

/**
 * Builds the javascript ready function to enable fancybox
 */
function build_javascript()
{
	global $modSettings, $txt;

	// Build the Javascript based on ACP choices
	$javascript = '
		$(document).ready(function() {
			// All the attachment links get a fancybox class, remove onclick events
			$("a[id^=link_]").each(function(){
				var tag = $(this);

				tag.addClass("fancybox").removeAttr("onclick");

				// No rel tag yet? then add one
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
				padding: ' . (empty($modSettings['fancybox_Padding']) ? 0 : (int) $modSettings['fancybox_Padding']) . ',
				arrows: true,
				closeBtn: true,
				loop: "' .  !empty($modSettings['fancybox_Loop']) . '",
				openEffect: "' . $modSettings['fancybox_openEffect'] . '",
				openSpeed: ' . (int) $modSettings['fancybox_openSpeed'] . ',
				closeEffect: "' . $modSettings['fancybox_closeEffect'] . '",
				closeSpeed: ' . (int) $modSettings['fancybox_closeSpeed'] . ',
				nextEffect: "' . $modSettings['fancybox_navEffect'] . '",
				nextSpeed: ' . (int) $modSettings['fancybox_navSpeed'] . ',
				prevEffect: "' . $modSettings['fancybox_navEffect'] . '",
				prevSpeed: ' . (int) $modSettings['fancybox_navSpeed'] . ',
				autoPlay: ' . (!empty($modSettings['fancybox_autoPlay']) ? 'true' : 'false') . ',
				playSpeed: ' . (int) $modSettings['fancybox_playSpeed'] . ($modSettings['fancybox_panel_position'] !== 'simple' ? ',
				tpl: {
					error: \'<p class="errorbox">' . $txt['fancy_text_error'] . '</p>\',
					closeBtn: \'<div title="' . $txt['find_close'] . '" class="fancybox-item fancybox-close"></div>\',
					next: \'<a title="' . $txt['fancy_button_next'] . '" class="fancybox-item fancybox-next"><span></span></a>\',
					prev: \'<a title="' . $txt['fancy_button_prev'] . '" class="fancybox-item fancybox-prev"><span></span></a>\'
				},' : ',') . '
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
					ajax: {
						dataType : \'html\',
						headers  : { \'X-fancyBox\': true, \'User-Agent\': "' . $_SERVER['HTTP_USER_AGENT'] . '"}
					},
				}
			});
		});';

	addInlineJavascript($javascript, true);
}

/**
 * The only way I see to reliably get the full size image link
 * from postimage is to request it.  This makes the ajax request
 * to find all postim links on a page and update them.
 */
function build_lookup()
{
	global $boardurl;

	$javascript = '
	$(\'a[href*="postim"]\').each(function (i, item) {
		var $item = $(item),
			href = $item.attr("href"),
			regex = new RegExp("<img src=\'(.*?\\.(png|gif|jp(e)?g|bmp))\'", "gi");
		$.ajax({
			type: "GET",
			crossOrigin: true,
			url: href,
			proxy: "' . $boardurl . '/fb4elk_proxy.php", // Use a local proxy due to limits on the use of Google Apps Script
			dataType: "json",
			cache: true,
			headers: {"User-Agent": "' . $_SERVER['HTTP_USER_AGENT'] . '"}
		}).done(function (response) {
			// There is an img in the response
			if (response.indexOf(\'<img\') !== -1) {
				match = regex.exec(response);
				if (match !== null) {
					// Swap out the old link with the correct one
					$item.attr("href", match[1]);
				}
			}
		}).fail(function (xhr, status, error) {
			// console.log(xhr, status, error);
			// Nothing much to do
		});
	})';

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
			elseif ($code['content'] == '<img src="$1" alt="{alt}" style="{width}{height}" class="bbc_img resized" />')
				$code['content'] = '<a href="$1" class="fancybox" title="{alt}" rel="topic"><img src="$1" alt="{alt}" style="' . $style . '" class="bbc_img resized fancybox" /></a>';
		}
	}
}

/**
 * ipdc_fb4elk()
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

	$regex = '~<a href="(.*)".*(class="bbc_link").*>(<a href="(.*)".*(class="fancybox" rel="topic")>)<img.*class="bbc_img" />(</a>(</a>))~U';

	// Make sure we need to do anything
	if (empty($modSettings['enableBBC']) || empty($modSettings['fancybox_bbc_img']))
		return;

	// Fix nested links caused by [url=remote][img]http://remote[/img][/url]
	// These occur as part of parse_bbc so deal with it
	$output['body'] = preg_replace_callback($regex, 'fix_url_bbc', $output['body']);

	// Find all the bbc images with a rel="topic" in the links and inject the gallery tag so
	// the bbc images and attachments of a message are part of the same gallery
	$output['body'] = str_replace('rel="topic"', 'rel="gallery_msg_' . $output['id'] . '_footer"', $output['body']);
}

/**
 * Updates links to external sites to link to full image or reverts the nested link to
 * be what it was since we add a link via the updated img BBC tag.
 *
 * @param mixed[] $matches from the regex with the following capture groups
 *	[0] full match
 *	[1] outside link href
 *	[2] outside link class=""
 *	[3] inside link full
 *	[4] inside link href
 *	[5] inside link class="fancybox" rel="topic"
 *	[6] trailing </a></a>
 *	[7] trailing </a>
 */
function fix_url_bbc($matches)
{
	global $modSettings;
	static $linker;

	$output = $matches[0];

	// Don't want fancybox at all on linked bbc image [url=remote][img]http://remote[/img][/url] syntax
	if (!empty($modSettings['fancybox_disable_img_in_url']))
	{
		// Remove the inside link and trailing </a>
		$output = str_replace($matches[3], '', $output);
		$output = str_replace($matches[6], $matches[7], $output);
	}
	// Fix the links so they link to what they did (ie the url)
	else
	{
		// Remove the inside link
		$output = str_replace($matches[3], '', $output);

		// Swap outside link class with the inside one (fancybox)
		$output = str_replace($matches[2], $matches[5], $output);

		// Replace the double </a></a> with a single
		$output = str_replace($matches[6], $matches[7], $output);

		// Support remote image sites?
		if (!empty($modSettings['fancybox_convert_photo_share']))
		{
			if (empty($linker))
				$linker = new getRemoteLink();

			$newlink = $linker->process_URL($matches);
			if ($newlink)
				$output = str_replace($matches[1], $newlink, $output);
		}
	}

	return $output;
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
	require_once(SUBSDIR . '/SettingsForm.class.php');

	// Instantiate the form
	$fbSettings = new Settings_Form();

	// Show / hide fancybox fields as required
	addInlineJavascript('
		function showhidefbOptions()
		{
			var fbThumb = document.getElementById(\'fancybox_thumbnails\').checked,
				fbThumb_dd = $(\'#fancybox_panel_position\'),
				fbThumb_dt = $(\'#setting_fancybox_panel_position\');

			var fbConvert = document.getElementById(\'fancybox_disable_img_in_url\'),
				fbShare = document.getElementById(\'fancybox_convert_photo_share\'),
				fbBBC = document.getElementById(\'fancybox_bbc_img\');

			// Show the BBC url box only if bbc image option is enabled
			if (fbBBC.checked === false)
			{
				fbConvert.disabled = true;
				fbShare.disabled = true;
			}
			else
			{
				fbConvert.disabled = false;
				fbShare.disabled = false;
			}

			// Show the convert image host sites if we are converting url-ed bbc image
			if (fbConvert.checked === false && fbBBC.checked === true)
				fbShare.disabled = false;
			else
				fbShare.disabled = true;

			// Show the thumbnail position box only if the option has been selected
			if (fbThumb === true)
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
		array('title', 'fancybox_displayOptions'),
			array('int', 'fancybox_Padding'),
			array('check', 'fancybox_Loop'),
			array('check', 'fancybox_thumbnails', 'onchange' => 'showhidefbOptions();'),
			array('select', 'fancybox_panel_position', array(
				'top' => $txt['fancybox_panel_top'],
				'bottom' => $txt['fancybox_panel_bottom'],
				'simple' => $txt['fancybox_panel_simple'])
			),
		array('title', 'fancybox_other'),
			array('check', 'fancybox_autoPlay'),
			array('int', 'fancybox_playSpeed'),
			array('check', 'fancybox_bbc_img', 'onchange' => 'showhidefbOptions();'),
			array('check', 'fancybox_disable_img_in_url', 'onchange' => 'showhidefbOptions();'),
			array('check', 'fancybox_convert_photo_share'),
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
		if (empty($_POST['fancybox_Padding']))
			$_POST['fancybox_Padding'] = 0;

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

/**
 * Class to update external site links so the thumbnail expands to the full image
 */
class getRemoteLink
{
	protected $provider = array();
	protected $providers = array();
	protected $out = false;

	/**
	 * Determins if a link is from a host provider we support
	 *
	 * @return boolean
	 */
	private function _valid()
	{
		foreach ($this->providers as $host)
		{
			$host = trim($host);
			if (stripos($this->provider, $host) !== false)
			{
				$this->provider = $host;
				return true;
			}
		}

		return false;
	}

	/**
	 * Loads the active image host providers
	 *
	 * @global string[] $modSettings
	 */
	private function _active_providers()
	{
		$this->providers = array('imageshack', 'photobucket', 'radikal', 'fotosik', 'postim', 'flic', 'smugmug');
	}

	/**
	 * Parses a URL in to a its parts so we have the host provider
	 */
	private function _parseURL()
	{
		$this->url_parts = parse_url($this->url[1]);

		if ($this->url_parts !== false)
			$this->provider = $this->url_parts['host'];
		else
			$this->provider = false;
	}

	/**
	 * Main controlling function to update links
	 *
	 * @param string[] $url
	 */
	public function process_URL($url)
	{
		$this->url = $url;
		$this->out = false;

		// Sites we support
		$this->_active_providers();

		// Parse out a image host name for use
		$this->_parseURL();

		// A host we support and is active
		if ($this->provider && $this->_valid())
		{
			// The the host function
			$call = '_' . $this->provider;
			$this->$call();
		}

		return $this->out;
	}

	/**
	 * Imageshack changed how they works and I'm not sure this is the way to go for all links
	 * but the old .th thing looks to be gone.
	 *
	 * thumbnail may look like http://imagizer.imageshack.us/v2/150x100q90/713/coreg.jpg
	 * the link then is http://imageshack.us/download/713/coreg.jpg
	 */
	private function _imageshack()
	{
		$link_parts = parse_url($this->url[4]);

		if ($link_parts !== false && isset($link_parts['path'], $link_parts['host']))
		{
			// break the path, we want the last two keys
			$link = explode('/', $link_parts['path']);
			$count = count($link);

			// We have the last two, so lets build us a link
			if ($count > 2)
				$this->out = 'http://' . str_replace('imagizer.', '', $link_parts['host']) . '/download/' . $link[$count-2] . '/' . $link[$count-1];
		}
	}

	/**
	 * photobucket ... The link is to the album so just swap it, not sure there is a thumb one
	 */
	private function _photobucket()
	{
		$this->out = $this->url[4];
	}

	/**
	 * The thumb link has a trailing t, remove that for a link to the full size
	 */
	private function _radikal()
	{
		if (preg_match('~(.*?)/([^/]*?)t\.(png|gif|jp(e)?g|bmp)$~isu', $this->url[4], $out))
			$this->out = $out[1] . '/' . $out[2] . '.' . $out[3];
	}

	/**
	 * [URL=http://www.fotosik.pl/pokaz_obrazek/cbf1f3a5b721a176.html]
	 * [IMG]http://images70.fotosik.pl/318/cbf1f3a5b721a176m.jpg[/IMG]
	 * [/URL]
	 * Trailing m or med that gets trimmed for the full image link
	 */
	private function _fotosik()
	{
		if (preg_match('~(.*?)\.(?:m\.|)(png|gif|jp(e)?g|bmp)$~isu', $this->url[4], $out))
		{
			if (substr($out[1], -1) == 'm')
				$out[1] = substr($out[1], 0, strlen($out[1]) - 1);
			if (substr($out[1], -3) == 'med')
				$out[1] = substr($out[1], 0, strlen($out[1]) - 3);
			$this->out = $out[1] . '.' . $out[2];
		}
	}

	/**
	 * postimage
	 * This service requries an external lookup to find the full size image
	 * Currently done with JS lookups after page load, but this is here
	 * should you want to do this server side.
	 */
	private function _postim()
	{
		require_once(SUBSDIR . '/Package.subs.php');

		// So postimage cache is correct
		//ini_set('user_agent', $_SERVER['HTTP_USER_AGENT']);
		//$page = fetch_web_data($this->url[1]);

		// Found the page, then find the link
		//if ($page !== false && preg_match('~<img src=\'(.*?\.(png|gif|jp(e)?g|bmp))\'~is', $page, $link))
		//	$this->out = $link[1];
	}

	/**
	 * flic.kr can have a variety of smalls, here we look for _t _s or _q and swap it with _b
	 */
	private function _flic()
	{
		if (preg_match('~(.*?)(?:_[t|s|q]\.)(png|gif|jp(e)?g|bmp)$~isu', $this->url[4], $out))
			$this->out = $out[1] . '_b.' . $out[2];
	}

	/**
	 * Smugmug ... missing a recent example to test
	 */
	private function _smugmug()
	{
		if (preg_match('~(.*?)(?:\/S\/)(.*?)(?:-S)\.(png|gif|jp(e)?g|bmp)$~isu', $this->url[4], $out))
			$this->out = $out[1] . '/O/' . $out[2] . '.' . $out[3];
	}
}