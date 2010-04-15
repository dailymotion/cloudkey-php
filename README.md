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
    for ($media in $media_list)
    {
        echo $media->id;
    }

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
