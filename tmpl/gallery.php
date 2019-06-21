<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.Gallery
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Filesystem\File;

$value = $field->value;

if (!$value) {
	return;
}

/* TODO: 
* select to load assets localy, from CDN or don't load
* select minified/not minifed versions
* set gallery id (currently #gallery)
* set grid options (child widths, gutters...)
* select lightbox animation
* set overlay background (currently selector uk-overlay-primary is set, option to choose color)
* select overlay icon
* set image alt tag
* set image captions
*/

// Loading the language
Factory::getLanguage()->load('plg_fields_gallery', JPATH_ADMINISTRATOR);

$value = (array)$value;

$thumbWidth     = $fieldParams->get('thumbnail_width', '64');
$maxImageWidth  = $fieldParams->get('max_width', 0);
$maxImageHeight = $fieldParams->get('max_height', 0);
$loaduikit 		= $fieldParams->get('loaduikit', 0);

if($loaduikit == 0) {
	// Loading UIkit
	HTMLHelper::_('stylesheet', 'plg_fields_gallery/uikit.min.css', ['version' => 'auto', 'relative' => true]);
	HTMLHelper::_('script', 'plg_fields_gallery/uikit.min.js', ['version' => 'auto', 'relative' => true]);
	HTMLHelper::_('script', 'plg_fields_gallery/uikit-icons.min.js', ['version' => 'auto', 'relative' => true]);
}
if($loaduikit == 1) {
	$doc = Factory::getDocument();
	$doc->addStyleSheet('https://cdn.jsdelivr.net/npm/uikit@3.1.6/dist/css/uikit.min.css');
	$doc->addScript('https://cdn.jsdelivr.net/npm/uikit@3.1.6/dist/js/uikit.min.js');
	$doc->addScript('https://cdn.jsdelivr.net/npm/uikit@3.1.6/dist/js/uikit-icons.min.js');
}

// Main container
$buffer = '<div id="gallery" class="uk-child-width-1-2@s uk-child-width-1-3@m uk-grid-small" uk-grid uk-lightbox="animation: fade">';

foreach ($value as $path) {
	// Only process valid paths
	if (!$path) {
		continue;
	}

	if ($path == '-1') {
		$path = '';
	}

	// The root folder
	$root = 'images/' . $fieldParams->get('directory', '');

	foreach (Folder::files(JPATH_ROOT . '/' . $root . '/' . $path, '.', $fieldParams->get('recursive', '1'), true) as $file) {
		// Skip none image files
		if (!in_array(strtolower(File::getExt($file)), array('jpg', 'png', 'bmp', 'gif',))) {
			continue;
		}

		// Getting the properties of the image
		$properties = Image::getImageFileProperties($file);

		// Relative path
		$localPath    = str_replace(Path::clean(JPATH_ROOT . '/' . $root . '/'), '', $file);
		$webImagePath = $root . '/' . $localPath;

		if (($maxImageWidth && $properties->width > $maxImageWidth) || ($maxImageHeight && $properties->height > $maxImageHeight)) {
			$resizeWidth  = $maxImageWidth ? $maxImageWidth : '';
			$resizeHeight = $maxImageHeight ? $maxImageHeight : '';

			if ($resizeWidth && $resizeHeight) {
				$resizeWidth .= 'x';
			}

			$resize = JPATH_CACHE . '/plg_fields_gallery/gallery/' . $field->id . '/' . $resizeWidth . $resizeHeight . '/' . $localPath;

			if (!File::exists($resize)) {
				// Creating the folder structure for the max sized image
				if (!Folder::exists(dirname($resize))) {
					Folder::create(dirname($resize));
				}

				try {
					// Creating the max sized image for the image
					$imgObject = new Image($file);

					$imgObject = $imgObject->resize(
						$properties->width > $maxImageWidth ? $maxImageWidth : 0,
						$properties->height > $maxImageHeight ? $maxImageHeight : 0,
						true,
						Image::SCALE_INSIDE
					);

					$imgObject->toFile($resize);
				} catch (Exception $e) {
					Factory::getApplication()->enqueueMessage(Text::sprintf('PLG_FIELDS_GALLERY_IMAGE_ERROR', $file, $e->getMessage()));
				}
			}

			if (File::exists($resize)) {
				$webImagePath = Uri::base(true) . str_replace(JPATH_ROOT, '', $resize);
			}
		}

		// Thumbnail path for the image
		$thumb = JPATH_CACHE . '/plg_fields_gallery/gallery/' . $field->id . '/' . $thumbWidth . '/' . $localPath;

		if (!File::exists($thumb)) {
			try {
				// Creating the folder structure for the thumbnail
				if (!Folder::exists(dirname($thumb))) {
					Folder::create(dirname($thumb));
				}

				// Getting the properties of the image
				$properties = Image::getImageFileProperties($file);

				if ($properties->width > $thumbWidth) {
					// Creating the thumbnail for the image
					$imgObject = new Image($file);
					$imgObject->resize($thumbWidth, 0, false, Image::SCALE_INSIDE);
					$imgObject->toFile($thumb);
				}
			} catch (Exception $e) {
				Factory::getApplication()->enqueueMessage(Text::sprintf('PLG_FIELDS_GALLERY_IMAGE_ERROR', $file, $e->getMessage()));
			}
		}

		if (File::exists($thumb)) {
			$buffer .= '<div>';
			$buffer .= '<div class="uk-inline-clip uk-cover-container uk-transition-toggle">';
			$buffer .= '<img src="' . Uri::base(true) . str_replace(JPATH_ROOT, '', $thumb) . '" alt="Gallery Item" class="uk-transition-scale-up uk-transition-opaque">';
			$buffer .= '<div class="uk-overlay-primary uk-position-cover uk-transition-fade">';
			$buffer .= '<span class="uk-position-center" uk-icon="icon: search; ratio: 2"></span>';
			$buffer .= '</div>';
			$buffer .= '<a class="uk-position-cover" href="' . $webImagePath . '"></a>';
			$buffer .= '</div>';
			$buffer .= '</div>';
		} else {
			$buffer .= '<div>';
			$buffer .= '<div class="uk-inline-clip uk-cover-container uk-transition-toggle">';
			$buffer .= '<img src="' . $webImagePath . '" alt="Gallery Item" class="uk-transition-scale-up uk-transition-opaque">';
			$buffer .= '<div class="uk-overlay-primary uk-position-cover uk-transition-fade">';
			$buffer .= '<span class="uk-position-center" uk-icon="icon: search; ratio: 2"></span>';
			$buffer .= '</div>';
			$buffer .= '<a class="uk-position-cover" href="' . $webImagePath . '"></a>';
			$buffer .= '</div>';
			$buffer .= '</div>';
		}
	}
}

$buffer .= '</div>';

echo $buffer;
