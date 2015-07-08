<?php
/*
Plugin Name: Save & Import Image from URL
Author: basteln3rk
Author URI: https://github.com/basteln3rk
Version: 0.5
Description: Allows you save an image from a URL to the WordPress media gallery. It replaces
the built-in "Import from URL" media upload tab, which only inserts a hotlink to the image
into the post, and makes sure that thumbnails of the imported image are also created locally.
Support for image renaming, tested with WordPress MU.
*/


/**
 * Removes the media 'From URL' string. (http://wordpress.stackexchange.com/a/76290/70506)
 * @see wp-includes|media.php
 */
add_filter('media_view_strings', function ( $strings ) {
    unset( $strings['insertFromUrlTitle'] );
    return $strings;
});

add_filter('media_upload_tabs', function($tabs) {
	$tabs['fp_grabfromurl'] = __('Save & Import from URL');
	return($tabs);
});

add_action('media_upload_fp_grabfromurl', function() {
	return wp_iframe('fpGrabFromURLIframe');
});

function fpUploadForm($headerMessage = '') {
	echo '<form action="" method="post" id="image-form" class="media-upload-form type-form">';
	echo '<input type="hidden" name="post_id" value="' . $_REQUEST['post_id'] . '">';
	wp_nonce_field('media-form');

	echo '<div class="wrap media-embed" style="padding-left: 20px">' . 	$headerMessage .
	'<form>
	<table class="form-table"><tbody>
		<tr class="form-field form-required">
			<th scope="row" class="label">
			<span class="alignleft">Image URL</span>
			<span class="alignright"><abbr title="required" class="required">*</abbr></span>
			</th>
			<td class="field"><input name="grabfrom_url" type="url" required value="' . (isset($_POST['grabfrom_url']) ? $_POST['grabfrom_url'] : '') . '"></td>
		</tr>
		<tr class="form-field form-required">
			<th class="label">
			<span class="alignleft">Save As File Name</span>
			<span class="alignright"><abbr title="required" class="required">*</abbr></span>
			</th>
			<td><input name="grabfrom_saveas" type="text" required value="' . (isset($_POST['grabfrom_saveas']) ? $_POST['grabfrom_saveas'] : '') . '"></td>
		</tr>
	</table>';

	echo <<<HTML
	<br/>
	<script>
	function validateGrabfromFrom() {
		// make sure file extension is sensible
		var url = jQuery("input[name='grabfrom_url']").val() || '',
			urlExt = url.substring(url.lastIndexOf('.')+1).toLowerCase(),
			saveas = jQuery("input[name='grabfrom_saveas']").val() || '',
			saveasExt = saveas.substring(saveas.lastIndexOf('.')+1).toLowerCase();
		if (saveasExt != urlExt) {
			if (saveasExt == 'jpg' && urlExt == 'jpeg') {
				// that's fine
			}
			else {
				alert('File extensions of URL and Save as should match!');
				return(false);
			}
		}
	}
	</script>
	<input class="button-primary" type="submit" onClick="return validateGrabfromFrom();" name="grabfrom[submit]" value="Import Image" />

</div>
</form>
HTML;

}

function fpHandleUpload() {

	if (!wp_verify_nonce($_POST['_wpnonce'], 'media-form')) {
		return new WP_Error('grabfromurl', 'Could not verify request nonce');
	}

	// build up array like PHP file upload
	$file = array();
	$file['name'] = $_POST['grabfrom_saveas'];
	$file['tmp_name'] = download_url($_POST['grabfrom_url']);

	if (is_wp_error($file['tmp_name'])) {
		@unlink($file['tmp_name']);
		return new WP_Error('grabfromurl', 'Could not download image from remote source');
	}

	$attachmentId = media_handle_sideload($file, $_POST['post_id']);

	// create the thumbnails
	$attach_data = wp_generate_attachment_metadata( $attachmentId,  get_attached_file($attachmentId));

	wp_update_attachment_metadata( $attachmentId,  $attach_data );

	return $attachmentId;	
}

function fpGrabFromURLIframe() {
	media_upload_header();

	if (isset($_POST['grabfrom_url'])) {
		// this is an upload request. let's see!
		$attachmentId = fpHandleUpload();
		if (is_wp_error($attachmentId)) {
			fpUploadForm('<div class="error form-invalid">' . $attachmentId->get_error_message(). '</div>');
		}
		else {
			echo "<style>h3, #plupload-upload-ui,.max-upload-size { display: none }</style>";
			media_upload_type_form("image", null, $attachmentId);
		}
	}
	else {
		fpUploadForm();
	}
}