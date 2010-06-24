The Dailymotion Cloud API PHP binding exposes all API methods described in the API reference. A
master class named `CloudKey` exposes all namespaces through object attributes. For instance, to
call the `count` method from the `media` namespace, the code would be as
follow:

    $cloudkey = new CloudKey($username, $password);
    $result = $cloudkey->media->count();

To pass arguments to method, an associative array is passed as a first argument of the called method:

    $media = $cloudkey->media->info(array('id' => $a_media_id));

When method returns something, the result is either an `stdClass` instance when result is a
structure or an array of `stdClass` instances when result is a list:

    // Simple structure response type
    $media = $cloudkey->media->info(array('id' => $a_media_id));
    echo $media->id;

    // List of structures response type
    $media_list = $cloudkey->media->list();
    foreach ($media_list as $media)
    {
        echo $media->id;
    }

Local Methods
=============

There is some additional methods not documented in the API reference:

`file->upload_file($file)`
--------------------------

There is one additional method not documented in the API reference which is an helper to upload
media files. This helper is available in the `file` namespace and is named `upload_file`. This
method takes a path to a file as a first argument and returns uploaded file information like its URL
which can be provided to the `media.set_asset` method:

    $file = $cloudkey->file->upload_file('path/to/video.mov');
    $media = $cloudkey->media->create();
    $cloudkey->media->set_asset(array('id' => $media->id, 'preset' => 'source', 'url' => $file->url));

`media->get_stream_url()`
------------------------

Arguments:

- `id`: (required) the id of the media
- `format`: the component format, default is 'swf' and currently it is the only available format
- `seclevel`: The security level bitmask with default value of CLOUDGATE_SECLEVEL_NONE (see bellow for more info)
- `expires`: The UNIX timestamp of the time until this URL remains valid (default null)

The following arguments are only required if you don't activate the `CLOUDGATE_SECLEVEL_DELEGATE` option (not
recommended) and activated one of the corresponding security level:

- `asnum`: The AS number to limit this URL to (default null)
- `ip`: The IP to limit this URL to (default null)
- `useragent`: The exact User-Agent header sting to limit this URL to (default null)

This method returns an URL to the media stream component signed with the chosen security level. This
component can then be integrated into a player.

    // Create a media stream URL limited only to the AS of the end-user and valid for 1 hour
    $url = $cloudkey->media->get_stream_url(array('id' => $media->id, 'seclevel' => CLOUDGATE_SECLEVEL_DELEGATE|CLOUDGATE_SECLEVEL_ASNUM, 'expires' => time() + 60*60));


Exceptions
==========

Methods can throw exceptions when errors occurs, be prepared to handle them. Here is a list of
exception which could be thrown:

- `CloudKey_Exception:` When unexpected API response occurs
- `CloudKey_ProtocolException:` When transport layer error occurs
- `CloudKey_InvalidNamespaceException:` When an invalid namespace is used
- `CloudKey_InvalidMethodException:` When a not existing method is called
- `CloudKey_NotFoundException:` When action is requested on an not existing item
- `CloudKey_MissingParamException:` When a method is called with a missing mandatory parameter
- `CloudKey_InvalidParamException:` When a method is called with a invalid parameter
- `CloudKey_AuthorizationRequiredException:` When an authenticated method is call with not authentication information
- `CloudKey_AuthenticationFailedException:` When authentication information is invalid

Security Levels
===============

Security level defines the mechanism used by Dailymotion Cloud architecture to ensure the signed URL
will be used by a single end-user. The different security levels are:

- `NONE`: The signed URL will be valid for everyone
- `ASNUM`: The signed URL will only be valid for the AS of the end-user. The ASNUM (for Autonomous
  System Number) stands for the network identification, each ISP have a different ASNUM for
  instance.
- `IP`: The signed URL will only be valid for the IP of the end-user. This security level may
  wrongly block some users which have their internet access load-balanced between several proxies.
  This is the case in some office network or some ISPs.
- `USERAGENT`: The signed URL will only be valid of the User-Agent of the end-user. This security
  level can be added to `ASNUM` or `IP` security levels.
- `DELEGATE`: This option instructs the signing algorithm that security level information won't be
  embeded into the signature but gathered and lock at the first use (see First Access Locking
  Security Model below). When this bit is active, you don't have to pass the end-user information
  to the signing algorithm (this is the recommended approach).
- `USEONCE`: This security level ensure the generated URL will only be usable once (using this level
  on an asset URL will prevent seeking from working.