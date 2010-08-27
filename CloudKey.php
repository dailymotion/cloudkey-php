<?php

define('CLOUDKEY_SECLEVEL_NONE',      0);
define('CLOUDKEY_SECLEVEL_DELEGATE',  1 << 0);
define('CLOUDKEY_SECLEVEL_ASNUM',     1 << 1);
define('CLOUDKEY_SECLEVEL_IP',        1 << 2);
define('CLOUDKEY_SECLEVEL_USERAGENT', 1 << 3);
define('CLOUDKEY_SECLEVEL_USEONCE',   1 << 4);

class CloudKey
{
    private
        $namespaces = array();

    protected
        $username = null,
        $password = null,
        $base_url = null,
        $cdn_url  = null,
        $owner_id = null,
        $api_key = null,
        $proxy = null,
        $act_as_user = null;

    function __construct($username, $password, $base_url = null, $cdn_url = null, $owner_id = null, $api_key = null, $proxy = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->base_url = $base_url;
        $this->cdn_url  = $cdn_url;
        $this->owner_id = $owner_id;
        $this->api_key  = $api_key;
        $this->proxy = $proxy;
    }

    public function act_as_user($username)
    {
        $this->act_as_user = $username;
        unset($this->namespaces['user']); // reset whoami cache
        foreach ($this->namespaces as $namespace)
        {
            $namespace->extra_params['__user__'] = $this->act_as_user;
        }
    }

    public function __get($name)
    {
        if (!isset($this->namespaces[$name]))
        {
            $class = 'CloudKey_' . ucfirst($name);
            if (!class_exists($class))
            {
                throw new CloudKey_InvalidNamespaceException($name);
            }
            $this->namespaces[$name] = new $class($this->username, $this->password, $this->base_url, $this->cdn_url, $this->owner_id, $this->api_key, null, $this->proxy);
            $this->namespaces[$name]->parent = $this;
            if ($this->act_as_user)
            {
                $this->namespaces[$name]->extra_params['__user__'] = $this->act_as_user;
            }
        }

        return $this->namespaces[$name];
    }

}

class CloudKey_User extends CloudKey_Api
{
    protected
        $whoami = false;

    // Cache result
    public function whoami()
    {
        if (false === $this->whoami)
        {
            $this->whoami = parent::whoami();
        }

        return $this->whoami;
    }
}

class CloudKey_Media extends CloudKey_Api
{
    function get_embed_url($args)
    {
        if ($this->owner_id == null || $this->api_key == null)
        {
            throw new CloudKey_MissingParamException('You must provide valid owner_id and api_key parameters in the constructor to use this method');
        }
        $id = null;
        $seclevel = CLOUDKEY_SECLEVEL_NONE;
        $expires = null;
        $asnum = null;
        $ip = null;
        $useragent = null;
        extract($args);
        $url = sprintf('%s/embed/%s/%s', $this->base_url, $this->owner_id, $id);
        return $this->_sign_url($url, $this->api_key, $seclevel, $asnum, $ip, $useragent, $expires);
    }

    function get_stream_url($args)
    {
        if ($this->owner_id == null || $this->api_key == null)
        {
            throw new CloudKey_MissingParamException('You must provide valid owner_id and api_key parameters in the constructor to use this method');
        }
        $id = null;
        $preset = 'mp4_h264_aac';
        $seclevel = CLOUDKEY_SECLEVEL_NONE;
        $expires = null;
        $asnum = null;
        $ip = null;
        $useragent = null;
        $extension = '';
        $download = false;
        extract($args);
        if ($extension == '')
        {
            $parts = explode('_', $preset);
            $extension = ($parts[0] != $preset) ? $parts[0] : $extension;
        }
        $url = sprintf('%s/route/%s/%s/%s%s', $this->cdn_url, $this->owner_id, $id, $preset, $extension != '' ? ".$extension" : '');
        return $this->_sign_url($url, $this->api_key, $seclevel, $asnum, $ip, $useragent, $expires) . ($download ? '&throttle=0&helper=0&cache=0' : '');
    }
}

class CloudKey_File extends CloudKey_Api
{
    public function upload_file($file)
    {
        $result = parent::upload();

        $ch = curl_init();

        curl_setopt_array($ch, array
        (
            CURLOPT_URL => $result->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
            CURLOPT_TIMEOUT => $this->response_timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => array('file' => '@' . $file),
        ));

        if ($this->proxy !== null)
        {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        $json_response = curl_exec($ch);

        if (curl_errno($ch))
        {
            throw new CloudKey_ProtocolException(curl_error($ch), curl_errno($ch));
        }

        curl_close($ch);

        return json_decode($json_response);
    }
}

class CloudKey_Api
{
    protected
        $username = null,
        $password = null,
        $base_url = 'http://api.dmcloud.net',
        $cdn_url  = 'http://cdn.dmcloud.net',
        $owner_id = null,
        $api_key  = null,
        $namespace = null,
        $proxy = null;

    public
        $extra_params = null,
        $connect_timeout = 120,
        $response_timeout = 120;

    function __construct($username, $password, $base_url = null, $cdn_url = null, $owner_id = null, $api_key = null, $namespace = null, $proxy = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->proxy = $proxy;
        $this->extra_params = array();

        if ($base_url !== null)
        {
            $this->base_url = $base_url;
        }
        if ($cdn_url !== null)
        {
            $this->cdn_url = $cdn_url;
        }
        if ($owner_id !== null)
        {
            $this->owner_id = $owner_id;
        }
        if ($api_key !== null)
        {
            $this->api_key = $api_key;
        }

        if (get_class($this) !== 'CloudKey_Api' && $namespace === null)
        {
            $this->namespace = str_replace('cloudkey_', '', strtolower(get_class($this)));
        }
        else
        {
            $this->namespace = $namespace;
        }
    }

    public function __call($method, $args)
    {
        $params = null;
        if (isset($args[0]))
        {
            if (!is_array($args[0]))
            {
                throw new CloudKey_InvalidMethodException(sprintf('%s requires an array as first argument', $method));
            }
            $params = $args[0];
        }

        if (is_array($this->extra_params) && !empty($this->extra_params))
        {
            if ($params === null)
            {
                $params = $this->extra_params;
            }
            else
            {
                $params = array_merge($params, $this->extra_params);
            }
        }

        $path = $method;
        if (strpos($method, '__') !== FALSE)
        {
            $path = str_replace('__', '/', $method);
        }
        elseif ($this->namespace !== null)
        {
            $path = $this->namespace . '/' . $method;
        }

        $url = sprintf('%s/json/%s', $this->base_url, $path);

        $ch = curl_init();

        if ($params !== null)
        {
            foreach ($params as $key => $value)
            {
                if (!is_scalar($value))
                {
                    $params[$key] = json_encode($value);
                }
            }
            $url .= '?' . http_build_query($params);
        }

        curl_setopt_array($ch, array
        (
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
            CURLOPT_TIMEOUT => $this->response_timeout,
        ));

        if ($this->proxy !== null)
        {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        if ($this->username !== null)
        {
            curl_setopt_array($ch, array
            (
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => sprintf('%s:%s', $this->username, $this->password),
            ));
        }

        $json_response = curl_exec($ch);

        if (curl_errno($ch))
        {
            throw new CloudKey_ProtocolException(curl_error($ch), curl_errno($ch));
        }

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        switch ($status_code)
        {
            case 404:
            case 400:
                $result = json_decode($json_response);
                if ($result === null)
                {
                    throw new BadMethodCallException($method);
                }
                switch ($result->type)
                {
                    case 'ApiNotFound':     throw new CloudKey_NotFoundException($method);
                    case 'ApiMissingParam': throw new CloudKey_MissingParamException($method);
                    case 'ApiInvalidParam': throw new CloudKey_InvalidParamException($method);
                    default:                throw new CloudKey_Exception($method, $result->type);
                }

            case 401:
                if ($this->username !== null)
                {
                    throw new CloudKey_AuthenticationFailedException($method);
                }
                else
                {
                    throw new CloudKey_AuthorizationRequiredException($method);
                }

            case 204: return null; // Empty response

            default:
                if ($status_code >= 200 && $status_code < 400)
                {
                    return json_decode($json_response);
                }
                else
                {
                    throw new CloudKey_Exception($status_code);
                }
        }
    }

    protected function _sign_url($url, $secret, $seclevel=CLOUDKEY_SECLEVEL_NONE, $asnum=null, $ip=null, $useragent=null, $expires=null)
    {
        // Compute digest
        $expires = intval($expires == null ? time() + 7200 : $expires);
        @list($url, $query) = @explode('?', $url, 2);
        $secparams = '';
        if (!($seclevel & CLOUDKEY_SECLEVEL_DELEGATE))
        {
            if ($seclevel & CLOUDKEY_SECLEVEL_ASNUM)
            {
                if (!isset($asnum))
                {
                    throw new InvalidArgumentException('ASNUM security level required and no AS number provided.');
                }
                $secparams += $asnum;
            }
            if ($seclevel & CLOUDKEY_SECLEVEL_IP)
            {
                if (!isset($asnum))
                {
                    throw new InvalidArgumentException('IP security level required and no IP address provided.');
                }
                $secparams += $ip;
            }
            if ($seclevel & CLOUDKEY_SECLEVEL_USERAGENT)
            {
                if (!isset($asnum))
                {
                    throw new InvalidArgumentException('USERAGENT security level required and no user-agent provided.');
                }
                $secparams += $useragent;
            }
        }
        $rand    = '';
        $letters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < 8; $i++)
        {
            $rand .= $letters[rand(0, 35)];
        }
        $digest = md5(implode('', array($seclevel, $url, $expires, $rand, $secret, $secparams)));

        // Return signed URL
        return sprintf('%s?%sauth=%s-%d-%s-%s', $url, ($query ? $query . '&' : ''), $expires, $seclevel, $rand, $digest);
    }
}

class CloudKey_Exception extends Exception {}
class CloudKey_ProtocolException extends CloudKey_Exception {}
class CloudKey_InvalidNamespaceException extends CloudKey_Exception {}
class CloudKey_InvalidMethodException extends CloudKey_Exception {}
class CloudKey_NotFoundException extends CloudKey_Exception {}
class CloudKey_MissingParamException extends CloudKey_Exception {}
class CloudKey_InvalidParamException extends CloudKey_Exception {}
class CloudKey_AuthenticationFailedException extends CloudKey_Exception {}
class CloudKey_AuthorizationRequiredException extends CloudKey_Exception {}
