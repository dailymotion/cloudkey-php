#!/usr/bin/env php5
<?php
# You must edit the following values with your own
$user_id = null;
$api_key = null;
$video_file = '.fixtures/video.3gp';

@include 'local_config.php';

require_once 'CloudKey.php';

$cloudkey = new CloudKey($user_id, $api_key);

# Upload a video
$res = $cloudkey->file->upload_file($video_file);
print_r($res);

# The url of the uploaded video 
$source_url = $res->url;

# The list of encoding assets that we want
$assets = array('flv_h263_mp3', 'mp4_h264_aac', 'mp4_h264_aac_hq', 'jpeg_thumbnail_medium', 'jpeg_thumbnail_source');

# A list of metadata we want to add to our media
$meta = array('title' => 'my first video', 'author' => 'John Doe');

# We can now start the publishing process
$media = $cloudkey->media->create(array('assets_names' => $assets, 'meta' => $meta, 'url' => $source_url));

# We display the informations of the media we just created and store the media ID
print_r($media);
$media_id = $media->id;

# We'll poll the status of one of our asset
while(1) {
    # We fetch the informations of the asset 'flv_h263_mp3'
    $res = $cloudkey->media->get_assets(array('id' => $media_id, 'assets_names' => array('flv_h263_mp3')));
    print_r($res);
    $asset = $res->flv_h263_mp3;
    # If it is ready we ask for the url of the flash player
    if ($asset->status == 'ready') {
        echo "Your media is ready, here is the url for your embed code:\n";
        echo $cloudkey->media->get_stream_url(array('id' => $media_id, 'asset_name' => 'flv_h263_mp3')) . "\n";
	break;
    # If the transcoding process failed we display an error
    } else if ($asset->status == 'error') {
        echo "Error while transcoding the media\n";
    }
    sleep(1);
}
