#!/usr/bin/env php5
<?php
# You must edit the following values with your own
$username = 'my_username';
$password = 'my_password';
$video_file = '/the/path/to/my/video.avi';

require_once 'CloudKey.php';

$cloudkey = new CloudKey($username, $password);

# Check that we are authenticated
print_r($cloudkey->user->whoami());

# Upload a video
$res = $cloudkey->file->upload_file($video_file);
print_r($res);

# The url of the uploaded video 
$source_url = $res->url;

# The list of encoding presets that we want
$presets = array('flv_h263_mp3', 'mp4_h264_aac', 'mp4_h264_aac_hq', 'mp4_h264_aac_hd', 'flv_h263_mp3_ld', 'jpeg_thumbnail_medium', 'jpeg_thumbnail_source');

# A list of metadata we want to add to our media
$meta = array('title' => 'my first video', 'author' => 'John Doe');

# We can now start the publishing process
$media = $cloudkey->media->publish(array('presets' => $presets, 'meta' => $meta, 'url' => $source_url));

# We display the informations of the media we just created and store the media ID
print_r($media);
$media_id = $media->id;

# We'll poll the status of one of our asset
while(1) {
    # We fetch the informations of the asset 'flv_h263_mp3'
    $res = $cloudkey->media->get_asset(array('id' => $media_id, 'preset' => 'flv_h263_mp3'));
    print_r($res);
    # If it is ready we ask for the url of the flash player
    if ($res->status == 'ready') {
        echo "Your media is ready, here is the url for your embed code:\n";
        echo $cloudkey->media->get_mediaplayer_url(array('id' => $media_id)) . "\n";
	break;
    # If the transcoding process failed we display an error
    } else if ($res->status == 'error') {
        echo "Error while transcoding the media\n";
    }
    sleep(1);
}