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
$assets = array('mp4_h264_aac', 'mp4_h264_aac_hq', 'jpeg_thumbnail_medium', 'jpeg_thumbnail_source');

# A list of metadata we want to add to our media
$meta = array('title' => 'my first video', 'author' => 'John Doe');

# We can now start the publishing process
$media = $cloudkey->media->create(array('assets_names' => $assets, 'meta' => $meta, 'url' => $source_url));

# We display the informations of the media we just created and store the media ID
print_r($media);
$media_id = $media->id;

# We'll poll the status of one of our asset
while(1) {
    # We fetch the informations of the asset 'mp4_h264_aac'
    $res = $cloudkey->media->get_assets(array('id' => $media_id, 'assets_names' => array('mp4_h264_aac')));
    print_r($res);
    $asset = $res->mp4_h264_aac;
    # If it is ready we ask for the url of the flash player
    if ($asset->status == 'ready') {
        echo "Your media is ready, here is the url for your embed code:\n";
        echo $cloudkey->media->get_stream_url(array('id' => $media_id, 'asset_name' => 'mp4_h264_aac')) . "\n";
	break;
    # If the transcoding process failed we display an error
    } else if ($asset->status == 'error') {
        echo "Error while transcoding the media\n";
    }
    sleep(1);
}

# Get some informations about our media
# more informations here: http://www.dmcloud.net/doc/api/cloud-api.html#info
$res = $cloudkey->media->info(array('id' => $media_id, 'fields' => array('assets.source.duration', 'created', 'meta.title')));
print_r($res);

$duration = $res->assets->source->duration;
$created = $res->created;
$title = $res->meta->title;

echo 'the video "' . $title . '" with id: '. $media_id . ' was created on ' . strftime("%c", $created) . ' and has a duration of ' . $duration . " seconds.\n";

# Get the URL of a thumbnail
echo $cloudkey->media->get_stream_url(array('id' => $media_id, 'asset_name' => 'jpeg_thumbnail_source'));


// We set the thumbnail from a url (jpeg file)
$cloudkey->media->set_thumbnail(array('id' => $media_id, 'url' => 'http://farm5.static.flickr.com/4026/5153920292_354be441b3_o.jpg'));

// We set a thumbnail based on a timecode (format = HH:MM:SS.mm - HH: 2 digits hours, MM: 2 digits minutes, SS: 2 digits seconds, mm: 2 digits ms)
$cloudkey->media->set_thumbnail(array('id' => $media_id, 'timecode' => '00:00:03.00'));
