<?php

/**
 * @package "FancyBox 4 ElkArte" Addon for Elkarte
 * @author Spuds
 * @copyright (c) 2011-2022 Spuds
 * @license This Source Code is subject to the terms of the Mozilla Public License
 * version 1.1 (the "License"). You can obtain a copy of the License at
 * http://mozilla.org/MPL/1.1/.
 *
 * @version 1.0.9
 *
 */

use BBC\Codes;

/**
 * ilt_fb4elk()
 *
 * - Integrate_load_theme, Called from load.php
 * - Used to add items to the loaded theme (css / js)
 */
function ilt_fb4elk()
{
	global $context, $modSettings;

	// If off, just return
	if (empty($modSettings['fancybox_enabled']))
	{
		return;
	}

	// If we are in an area where we never want this, return
	if (in_array($context['current_action'], array('admin', 'helpadmin', 'printpage')))
	{
		return;
	}

	// Load the required items
	loadLanguage('fb4elk');
	loadCSSFile(array('fancybox/jquery.fancybox.css'), array('stale' => '?v=3.5.7'));
	loadJavascriptFile(array('fancybox/jquery.fancybox.min.js'), array('stale' => '?v=3.5.7'));
	loadJavascriptFile(array('fancybox/jquery.fb4elk.js'), array('stale' => '?v=1.0.9'));

	// Disable the built-in lightbox support in ElkArte 1.1
	if (defined('FORUM_VERSION') && substr(FORUM_VERSION, 8, 3) === '1.1')
	{
		addInlineJavascript('
			fbWaitForEvent("[data-lightboximage]", "click.elk_lb", 200, 5)
			.then(() => {$("[data-lightboximage]").off("click.elk_lb")})
			.catch((error) => {console.info("fb4elk: ", error)});
		', true);
	}

	// BBC image links enabled, then remove the elk bbc image clicker (really a 1.0 thang)
	if (!empty($modSettings['fancybox_bbc_img']))
	{
		addInlineJavascript('
			fbWaitForEvent("img", "click.elk_bbc", 200, 5)
			.then(() => {$("img").off("click.elk_bbc")})
			.catch((error) => {console.info("fb4elk: ", error)});
		', true);
	}

	// And output the needed JS commands
	build_javascript();

	// Build a lookup for postimage
	if (!empty($modSettings['fancybox_convert_photo_share'])
		&& !empty($modSettings['fancybox_convert_postimage_share'])
		&& !empty($modSettings['fancybox_bbc_img'])
		&& !in_array($context['current_action'], array('profile', 'moderate', 'login')))
	{
		// CORS lib
		loadJavascriptFile(array('fancybox/jquery.ajax-cross-origin.min.js'), array('stale' => '?v=1.3'));
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
			// All the attachment links get fancybox data, remove onclick events
			$("a[id^=link_]").each(function(){
				let tag = $(this);

				tag.attr("data-fancybox", "").removeAttr("onclick");

				// No rel tag yet? then add one
				if (!tag.attr("rel")) {
					if (tag.data("lightboxmessage"))
					{
						tag.attr("rel", "gallery_" + tag.data("lightboxmessage"));
						tag.attr("data-fancybox", "gallery_" + tag.data("lightboxmessage"));
					}
					else
						tag.attr("rel", "gallery");
				}
			});

			// Find all the attachment / bbc divs on the page
			$("div[id$=_footer]").each(function() {
				// Fancybox Galleries are created from elements who have the same "rel" tag
				let id = $(this).attr("id");
				
				$("#" + id + " a[rel=gallery]").attr("rel", "gallery_" + id);
			});

			// Attach FB to everything we tagged with the fancybox data attr
			$("[data-fancybox]").fancybox({
				type: "image",
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
		$javascript .= '
				thumbs: {
					axis: "x",
					autoStart: true
				},';
	}

	$javascript .= '
				ajax: {
					dataType : \'html\',
					headers  : { \'X-fancyBox\': true, \'User-Agent\': "' . $_SERVER['HTTP_USER_AGENT'] . '"}
				},
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
		let $item = $(item),
			href = $item.attr("href");
			
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
				let image2 = $(response).find("#code_2").html(),
					image1 = $(response).find("img:first").attr("src");

				// Swap out the old link with the correct one, link code takes precedence
				if (image1 === image2 || image2)
					$item.attr("href", image2);
				else if (image1)
					$item.attr("href", image1);
			}
		}).fail(function (xhr, status, error) {
			console.log(xhr, status, error);
			// Nothing much to do
		});
	})';

	addInlineJavascript($javascript, true);
}

/**
 * ibc_fb4elk()
 *
 * - integrate_bbc_codes hook, Called from Codes.php bbc_codes_parsing
 * - Used when attaching Fancybox to bbc images
 * - Replaces the standard bbc image link with one containing the fancybox class
 *
 * @param array $codes array of codes as defined for parse_bbc
 */
function ibc_fb4elk(&$codes)
{
	global $modSettings;

	// Only attach for topics, when bbc is on and the option is checked
	if (empty($_REQUEST['topic']) || empty($modSettings['enableBBC']) || empty($modSettings['fancybox_bbc_img']))
	{
		return;
	}

	// Make sure the admin had not disabled img tags as well
	if (!empty($modSettings['disabledBBC']))
	{
		foreach (explode(',', $modSettings['disabledBBC']) as $tag)
		{
			if ($tag === 'img')
			{
				return;
			}
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
			elseif ($code[Codes::ATTR_CONTENT] === '<img src="$1" title="{title}" alt="{alt}" style="{width}{height}" class="bbc_img resized" />')
			{
				$code[Codes::ATTR_CONTENT] = '<a href="$1" class="fancybox" rel="topic" title="{title}" alt="{alt}"><img src="$1" title="{title}" alt="{alt}" style="{width}{height}" class="bbc_img resized" /></a>';
			}
		}
	}
}

/**
 * iaa_fb4elk()
 *
 * - Display Hook, integrate_prepare_display_context, called from Display.controller
 * - Used to interact with the message array before its sent to the template
 *
 * @param array $output
 * @param array $message
 */
function ipdc_fb4elk(&$output, &$message)
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
	$output['body'] = str_replace('<br />', "\n", $output['body']);
	$check = preg_replace_callback($regex, 'fix_url_bbc', $output['body']);
	if ($check !== null)
	{
		$output['body'] = $check;
	}
	$output['body'] = str_replace( "\n", '<br />', $output['body']);


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
 *    [0] full match
 *    [1] outside link href
 *    [2] outside link class=""
 *    [3] inside link full
 *    [4] inside link href
 *    [5] inside link class="fancybox" rel="topic"
 *    [6] trailing </a></a>
 *    [7] trailing </a>
 *
 * @return string
 */
function fix_url_bbc($matches)
{
	global $modSettings;
	static $linker;

	$output = $matches[0];
	$no_fb = strpos($matches[5], 'title="nofb"') !== false || strpos($matches[5], 'title="&quot;nofb&quot;"') !== false;

	// Don't want fancybox at all on linked bbc image [url=remote][img]http://remote[/img][/url] syntax
	if (!empty($modSettings['fancybox_disable_img_in_url']) || $no_fb)
	{
		// Remove the inside link and trailing </a>
		$output = str_replace(array($matches[3], $matches[6]),
			array('', $matches[7]),
			$output);
	}
	// Fix the links, so they link to what they did (ie the url)
	else
	{
		// Remove the inside link
		// Swap outside link class with the inside one (fancybox)
		// Replace the double </a></a> with a single
		$output = str_replace(array($matches[3], $matches[2], $matches[6]),
			array('', $matches[5], $matches[7]),
			$output);

		// Support remote image sites?
		if (!empty($modSettings['fancybox_convert_photo_share']))
		{
			if (empty($linker))
			{
				$linker = new getRemoteLink();
			}

			$newlink = $linker->process_URL($matches);
			if ($newlink)
			{
				$output = str_replace($matches[1], $newlink, $output);
			}
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
 * @param array $admin_areas
 */
function iaa_fb4elk(&$admin_areas)
{
	global $txt;

	loadLanguage('fb4elk');
	$admin_areas['config']['areas']['addonsettings']['subsections']['fancybox'] = array($txt['fancybox_title']);
}

/**
 * imm_fb4elk()
 *
 * - Admin Hook, integrate_sa_modify_modifications, called from AddonSettings.controller.php
 * - Used to add subactions to the addon area
 *
 * @param array $sub_actions
 */
function imm_fb4elk(&$sub_actions)
{
	global $context, $txt;

	$sub_actions['fancybox'] = array(
		'dir' => SUBSDIR,
		'file' => 'fb4elk.subs.php',
		'function' => 'fb4elk_settings',
		'permission' => 'admin_forum',
	);

	$context[$context['admin_menu_name']]['tab_data']['tabs']['fancybox']['description'] = $txt['fancybox_desc'];
}

/**
 * fb4elk_settings()
 *
 * - Defines our settings array and uses our settings class to manage the data
 */
function fb4elk_settings()
{
	global $txt, $context, $scripturl, $modSettings;

	loadLanguage('fb4elk');

	// Lets build a settings form
	$fbSettings = new Settings_Form(Settings_Form::DB_ADAPTER);

	// Show / hide fancybox fields as required
	addInlineJavascript('
		function showhidefbOptions()
		{
			let fbThumb = document.getElementById(\'fancybox_thumbnails\').checked,
				fbThumb_dd = $(\'#fancybox_panel_position\'),
				fbThumb_dt = $(\'#setting_fancybox_panel_position\');

			let fbConvert = document.getElementById(\'fancybox_disable_img_in_url\'),
				fbShare = document.getElementById(\'fancybox_convert_photo_share\'),
				fbBBC = document.getElementById(\'fancybox_bbc_img\');
				
			let postimage_hide = !document.getElementById(\'fancybox_convert_photo_share\').checked,
			 	postimage_dd = $(\'#fancybox_convert_postimage_share\'),
				postimage_dt = $(\'#setting_fancybox_convert_postimage_share\');	

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
			
			if (postimage_hide)
			{
				postimage_dd.parent().slideUp();
				postimage_dt.parent().slideUp();
			}	
			else
			{
				postimage_dd.parent().slideDown();
				postimage_dt.parent().slideDown();
			}
		}
		
		showhidefbOptions();', true);

	// All the options, well at least some of them!
	$config_vars = array(
		array('check', 'fancybox_enabled', 'postinput' => $txt['fancybox_enabled_desc']),
		// Transition effects and speed
		array('title', 'fancybox_animation'),
		array('select', 'fancybox_openEffect', array(
			'zoom' => $txt['fancybox_effect_zoom'],
			'zoom-in-out' => $txt['fancybox_effect_elastic'],
			'fade' => $txt['fancybox_effect_fade'],
			'none' => $txt['fancybox_effect_none'])
		),
		array('int', 'fancybox_openSpeed'),

		array('select', 'fancybox_navEffect', array(
			'slide' => $txt['fancybox_effect_slide'],
			'circular' => $txt['fancybox_effect_circular'],
			'tube' => $txt['fancybox_effect_tube'],
			'rotate' => $txt['fancybox_effect_rotate'],
			'zoom-in-out' => $txt['fancybox_effect_elastic'],
			'fade' => $txt['fancybox_effect_fade'],
			'none' => $txt['fancybox_effect_none'])
		),
		array('int', 'fancybox_navSpeed'),

		array('title', 'fancybox_displayOptions'),
		array('check', 'fancybox_Loop'),
		array('check', 'fancybox_thumbnails', 'onchange' => 'showhidefbOptions();'),
		array('title', 'fancybox_other'),
		array('check', 'fancybox_autoPlay'),
		array('int', 'fancybox_playSpeed'),
		array('check', 'fancybox_bbc_img', 'onchange' => 'showhidefbOptions();'),
		array('check', 'fancybox_disable_img_in_url', 'onchange' => 'showhidefbOptions();'),
		array('check', 'fancybox_convert_photo_share', 'onchange' => 'showhidefbOptions();'),
		array('check', 'fancybox_convert_postimage_share'),
	);

	// Load the settings to the form class
	$fbSettings->setConfigVars($config_vars);

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

		$fbSettings->setConfigVars($config_vars);
		$fbSettings->setConfigValues($_POST);
		$fbSettings->save();

		redirectexit('action=admin;area=addonsettings;sa=fancybox');
	}

	// Continue on to the settings template
	$context['settings_title'] = $txt['fancybox_title'];
	$context['page_title'] = $context['settings_title'] = $txt['fancybox_settings'];
	$context['post_url'] = $scripturl . '?action=admin;area=addonsettings;sa=fancybox;save';

	if (!empty($modSettings['fancybox_thumbnails']))
	{
		updateSettings(array('fancybox_panel_position' => 'top'));
	}

	$fbSettings->prepare();
}

/**
 * Class to update external site links so the thumbnail expands to the full image
 */
class getRemoteLink
{
	/** @var string */
	protected $provider = '';
	/** @var array */
	protected $providers = array();
	/** @var string|false */
	protected $out = '';
	/** @var string */
	protected $url = '';
	/** @var array */
	protected $url_parts = array();

	/**
	 * Main controlling function to update links
	 *
	 * @param string[] $url
	 * @return bool|string
	 */
	public function process_URL($url)
	{
		$this->url = $url;
		$this->out = false;

		// Sites we support
		$this->_active_providers();

		// Parse an image host name for use
		$this->_parseURL();

		// A host we support and is active
		if ($this->provider && $this->_valid())
		{
			// The host function
			$call = '_' . $this->provider;
			$this->$call();
		}

		return $this->out;
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
	 * Parses a URL into its parts, so we have the host provider
	 */
	private function _parseURL()
	{
		$this->url_parts = parse_url($this->url[1]);
		$this->provider = false;

		if ($this->url_parts !== false)
		{
			$this->provider = $this->url_parts['host'];
		}
	}

	/**
	 * Determines if a link is from a host provider we support
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
	 * Imageshack changed how they work, and I'm not sure this is the way to go for all links
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
			{
				$this->out = $link_parts['scheme'] . '://' . str_replace('imagizer.', '', $link_parts['host']) . '/download/' . $link[$count - 2] . '/' . $link[$count - 1];
			}
		}
	}

	/**
	 * photobucket ... The link is to the album so just swap it, not sure there is a thumb one
	 * [URL=http://s220.photobucket.com/user/IDMLLC/media/IMG_9289.jpg.html]
	 * [IMG]http://i220.photobucket.com/albums/dd131/IDMLLC/th_IMG_9289.jpg[/img][/URL]
	 * need to strip the th_ if included
	 */
	private function _photobucket()
	{
		$link_parts = pathinfo($this->url[4]);

		$destination = preg_replace('~^th_~', '', $link_parts['basename']);
		$this->out = str_replace($link_parts['basename'], $destination, $this->url[4]);
	}

	/**
	 * The thumb link has a trailing t, remove that for a link to the full size
	 */
	private function _radikal()
	{
		if (preg_match('~(.*?)/([^/]*?)t\.(png|gif|jp(e)?g|bmp)$~isu', $this->url[4], $out))
		{
			$this->out = $out[1] . '/' . $out[2] . '.' . $out[3];
		}
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
			if (substr($out[1], -1) === 'm')
			{
				$out[1] = substr($out[1], 0, -1);
			}

			if (substr($out[1], -3) === 'med')
			{
				$out[1] = substr($out[1], 0, -3);
			}

			$this->out = $out[1] . '.' . $out[2];
		}
	}

	/**
	 * postimage
	 * This service requires an external lookup to find the full size image
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
	 * flic.kr has a variety of smalls, here we look for _t _s or _q and swap it with _b
	 */
	private function _flic()
	{
		if (preg_match('~(.*?)(_[t|s|q|k]\.)(png|gif|jp(e)?g|bmp)$~isu', $this->url[4], $out))
		{
			if ($out[2] === '_k.')
			{
				$this->out = $this->url[4];
			}
			else
			{
				$this->out = $out[1] . '_b.' . $out[3];
			}
		}
	}

	/**
	 * Smugmug ... missing a recent example to test
	 */
	private function _smugmug()
	{
		if (preg_match('~(.*?)(?:\/S\/)(.*?)(?:-S)\.(png|gif|jp(e)?g|bmp)$~isu', $this->url[4], $out))
		{
			$this->out = $out[1] . '/O/' . $out[2] . '.' . $out[3];
		}
	}
}
