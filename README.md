Remote methods
==============

The Dailymotion Cloud API PHP binding exposes all API methods described in the API reference.
A master class named `CloudKey` exposes all namespaces through object attributes. For instance,
a call to the `count` method in the `media` namespace would be as follow:

    $cloudkey = new CloudKey($user_id, $api_key);
    $result = $cloudkey->media->count();

For methods expecting parameters, these must be passed as an associative array:

    $media = $cloudkey->media->info(array('id' => $a_media_id));

The returned values (when available) are either `stdClass` instances (when result is a structure)
or arrays of `stdClass` instances (when result is a list of structures):

    # Simple structure response
    $media = $cloudkey->media->info(array('id' => $a_media_id));
    echo $media->id;

    # List of structures response
    $page = 1;
    while(1) {
        $res = $cloudkey->media->list(array('fields' => array('id', 'meta.title'), 'page' => $page++));
        foreach($res->list as $media) {
            printf("%s %s\n", $media->id, $media->meta->title);
        }
        if ($res->page == $res->pages) {
            break;
        }
    }

Local methods
=============

Additional local methods are exposed by the CloudKey class. They are not documented in the API
reference and provided as helpers to ease the Dailymotion Cloud API integration.

`file->upload_file($path)`
--------------------------

This method manages files uploads to the Dailymotion Cloud upload servers. Information pertinent
to the API media creation processus (especially the media_set_asset method) will be returned as
a `dict`.

Arguments:

- `$path`: (required) the path of the uploaded file.

Example:

    # source asset upload example
    $file = $cloudkey->file->upload_file('path/to/video.mov');
    $media = $cloudkey->media->create();
    $cloudkey->media->set_asset(array('id' => $media->id, 'asset_name' => 'source', 'url' => $file->url));

`media.get_embed_url(id)`
-------------------------

This method returns a signed URL to a Dailymotion Cloud player embed (see the API reference for details).
The generated URL is perishable, and access is granted based on the provided security level bitmask.

Arguments:

- `id`: (required) the media id.
- `seclevel`: the security level bitmask (default is `CLOUDKEY_SECLEVEL_NONE`, see below for details).
- `expires`: the UNIX epoch expiration time (default is `time() + 7200` (2 hours from now)).
- `secure`: `true` to get the https embed url (default is `false`).

The following arguments may be required if the `CLOUDKEY_SECLEVEL_DELEGATE` option is not specified in
the seclevel parameter, depending on the other options. This is not recommanded as it would probably
lead to spurious access denials, mainly due to GeoIP databases discrepancies.

- `asnum`: the client's autonomous system number (default is `None`).
- `ip`: the client's IP adress (default is `None`).
- `useragent`: the client's HTTP User-Agent header (default is `None`).

Example:

    // Create an embed URL limited only to the AS of the end-user and valid for 1 hour
    $url = $cloudkey->media->get_embed_url(array('id' => $media->id, 'seclevel' => CLOUDKEY_SECLEVEL_DELEGATE | CLOUDKEY_SECLEVEL_ASNUM, 'expires' => time() + 3600));

`media.get_stream_url(id)`
--------------------------

This method returns a signed URL to a Dailymotion Cloud video stream (see the API reference for details).
The generated URL is perishable, and access is granted based on the provided security level bitmask.

Arguments:

- `id`: (required) the media id.
- `asset_name`: the desired media asset asset_name name (default is `mp4_h264_aac`).
- `seclevel`: the security level bitmask (default is `CLOUDKEY_SECLEVEL_NONE`, see below for details).
- `expires`: the UNIX epoch expiration time (default is `time() + 7200` (2 hours from now)).
- `download`: `True` to get the download url (default is `False`).
- `filename`: the download url filename (overrides the `download` parameter if set).
- `version`: arbitrary integer inserted in the url for the cache flush.
Use this parameter only if needed, and change its value only when a cache flush is required.
- `protocol`: streaming protocol ('hls', 'rtmp', 'hps' or 'http'). Overrides the `download` parameter if 'http'.

The following arguments may be required if the `CLOUDKEY_SECLEVEL_DELEGATE` option is not specified in
the seclevel parameter, depending on the other options. This is not recommanded as it would probably
lead to spurious access denials, mainly due to GeoIP databases discrepancies.

- `asnum`: the client's autonomous system number (default is `None`).
- `ip`: the client's IP adress (default is `None`).
- `useragent`: the client's HTTP User-Agent header (default is `None`).

Example:

    // Create a stream URL limited only to the AS of the end-user and valid for 1 hour
    url = cloudkey.media.get_stream_url(id=media['id'], seclevel=CLOUDKEY_SECLEVEL_DELEGATE | CLOUDKEY_SECLEVEL_ASNUM, expires=time() + 3600)
    $url = $cloudkey->media->get_stream_url(array('id' => $media->id, 'seclevel' => CLOUDKEY_SECLEVEL_DELEGATE | CLOUDKEY_SECLEVEL_ASNUM, 'expires' => time() + 3600));

Security level options
======================

The security level defines the mechanism used by the Dailymotion Cloud architecture to ensure a mediastream
URL access will be limited to a single user or a group of users. The different (combinable) options are:

- `CLOUDKEY_SECLEVEL_NONE`: the URL access is granted to everyone.

- `CLOUDKEY_SECLEVEL_ASNUM`: the URL access is granted to the specified AS number only. AS numbers stands for
  'Autonomous System number' and roughly map groups of IP to telcos and large organizations on the Internet
   (each ISP has its own AS number for instance, Dailyotion's AS number is AS41690).

- `CLOUDKEY_SECLEVEL_IP`: the URL access is granted to the specified IP address only. This option may lead to
   spurious access denials as some users are load-balanced behind multiple proxies when accessing the Internet
   (this is mostly the case with ISPs and large organizations).

- `CLOUDKEY_SECLEVEL_USERAGENT`: the URL access is granted to users sending the specified User-Agent HTTP header
   only.

- `CLOUDKEY_SECLEVEL_DELEGATE`: the ASNUM, IP and User-Agent values are to be gathered at the server side during
  the first URL access and don't need to be specified at the client side beforehand (this is the recommanded approach
  as it will ensure a 100%-accurate ASNUM recognition).

- `CLOUDKEY_SECLEVEL_USEONCE`: the URL access is granted once only (using this option will probably prevent seeking
   from working correctly).

For more information, please refer to the Dailymotion Cloud streams security documentation.

Exceptions
==========

The Dailymotion Cloud API methods may throw exceptions when errors occur, and they should be catched in your
code. The available exceptions are:

- `CloudKey_Exception`: an unexpected API response occured.

- `CloudKey_ProtocolException`: a transport layer error occured.

- `CloudKey_InvalidNamespaceException`: an invalid namespace was used.

- `CloudKey_InvalidMethodException`: an invalid method was called.

- `CloudKey_NotFoundException`: an action was requested on an invalid item.

- `CloudKey_MissingParamException`: a mandatory parameter was missing from a method call.

- `CloudKey_InvalidParamException`: an invalid parameter was specified in a method call.

- `CloudKey_AuthorizationRequiredException`: an authenticated method was call but no authentication information was provided.

- `CloudKey_AuthenticationFailedException`: invalid authentication information was provided.
