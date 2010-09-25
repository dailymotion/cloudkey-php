<?php

define('CLOUDKEY_SECLEVEL_NONE',      0);
define('CLOUDKEY_SECLEVEL_DELEGATE',  1 << 0);
define('CLOUDKEY_SECLEVEL_ASNUM',     1 << 1);
define('CLOUDKEY_SECLEVEL_IP',        1 << 2);
define('CLOUDKEY_SECLEVEL_USERAGENT', 1 << 3);
define('CLOUDKEY_SECLEVEL_USEONCE',   1 << 4);
define('CLOUDKEY_SECLEVEL_COUNTRY',   1 << 5);
define('CLOUDKEY_SECLEVEL_REFERER',   1 << 6);

class CloudKey
{
    private
        $objects = array();

    protected
        $user_id = null,
        $api_key = null,
        $base_url = null,
        $cdn_url = null,
        $owner_id = null,
        $proxy = null;

    public function __construct($user_id, $api_key, $base_url = null, $cdn_url = null, $owner_id = null, $proxy = null)
    {
        $this->user_id = $user_id;
        $this->api_key = $api_key;
        $this->base_url = $base_url;
        $this->cdn_url = $cdn_url;
        $this->owner_id = $owner_id;
        $this->proxy = $proxy;
    }

    public function __get($name)
    {
        if (!isset($this->objects[$name]))
        {
            $class = 'CloudKey_' . ucfirst($name);
            if (!class_exists($class))
            {
                throw new CloudKey_InvalidNamespaceException($name);
            }
            $this->objects[$name] = new $class($this->user_id, $this->api_key, $this->base_url, $this->cdn_url, $this->owner_id, null, $this->proxy);
            $this->objects[$name]->parent = $this;
        }

        return $this->objects[$name];
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
    public function get_embed_url($args)
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

    public function get_stream_url($args)
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
        $countries = null;
        $referers = null;
        $extension = '';
        $download = false;
        extract($args);
        if ($extension == '')
        {
            $parts = explode('_', $preset);
            $extension = ($parts[0] != $preset) ? $parts[0] : $extension;
        }
        $url = sprintf('%s/route/%s/%s/%s%s', $this->cdn_url, $this->owner_id, $id, $preset, $extension != '' ? ".$extension" : '');
        return $this->_sign_url($url, $this->api_key, $seclevel, $asnum, $ip, $useragent, $countries, $referers, $expires) . ($download ? '&throttle=0&helper=0&cache=0' : '');
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
            throw new CloudKey_TransportException(curl_error($ch), curl_errno($ch));
        }

        curl_close($ch);

        return json_decode($json_response);
    }
}

class CloudKey_Api
{
    protected
        $user_id = null,
        $api_key = null,
        $endpoint = null,
        $base_url = 'http://api.dmcloud.net',
        $cdn_url = 'http://cdn.dmcloud.net',
        $owner_id = null,
        $object = null,
        $proxy = null;

    public
        $connect_timeout = 120,
        $response_timeout = 120;

    public function __construct($user_id, $api_key, $base_url = null, $cdn_url = null, $owner_id = null, $object = null, $proxy = null)
    {
        $this->user_id = $user_id;
        $this->api_key = $api_key;
        $this->proxy = $proxy;

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

        if (get_class($this) !== 'CloudKey_Api' && $object === null)
        {
            $this->object = str_replace('cloudkey_', '', strtolower(get_class($this)));
        }
        else
        {
            $this->object = $object;
        }

        $this->endpoint = $this->base_url . '/api';
    }

    public function __call($method, $args)
    {
        if (isset($args[0]))
        {
            if (!is_array($args[0]))
            {
                throw new CloudKey_InvalidMethodException(sprintf('%s requires an array as first argument', $method));
            }
            $args = $args[0];
        }
        else
        {
            unset($args);
        }

        $object = null;
        if (strpos($method, '__') !== FALSE)
        {
            list($object, $method) = explode('__', $method);
        }
        elseif ($this->object !== null)
        {
            $object = $this->object;
        }

        $request = array
        (
            'call' => $object . '.' . $method,
        );

        if (isset($args))
        {
            $request['args'] = $args;
        }

        $request['auth'] = $this->user_id . ':' . md5($this->user_id . $this->_normalize($request) . $this->api_key);

        $ch = curl_init();

        curl_setopt_array($ch, array
        (
            CURLOPT_URL => $this->endpoint,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
            CURLOPT_TIMEOUT => $this->response_timeout,
        ));

        if ($this->proxy !== null)
        {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        $json_response = curl_exec($ch);

        if (curl_errno($ch))
        {
            throw new CloudKey_TransportException(curl_error($ch), curl_errno($ch));
        }

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($json_response);

        if ($response === null)
        {
            throw new CloudKey_SerializerException('Cannot unserialize response.');
        }

        if (isset($response->error))
        {
            $error = $response->error;
            if (!isset($error->code))
            {
                throw new CloudKey_RPCException('Invalid error format.');
            }

            $message = isset($error->message) ? $error->message : null;

            switch ($error->code)
            {
                case 200: $e = new CloudKey_ProcessorException($message); break;
                case 300: $e = new CloudKey_TransportException($message); break;
                case 400: $e = new CloudKey_AuthenticationErrorException($message); break;
                case 410: $e = new CloudKey_RateLimitExceededException($message); break;
                case 500: $e = new CloudKey_SerializerException($message); break;

                case 600: $e = new CloudKey_InvalidRequestException($message); break;
                case 610: $e = new CloudKey_InvalidObjectException($message); break;
                case 620: $e = new CloudKey_InvalidMethodException($message); break;
                case 630: $e = new CloudKey_InvalidParamException($message); break;

                case 1000: $e = new CloudKey_ApplicationException($message); break;
                case 1010: $e = new CloudKey_NotFoundException($message); break;
                case 1020: $e = new CloudKey_ExistsException($message); break;
                case 1030: $e = new CloudKey_LimitExceededException($message); break;

                default: $e = new CloudKey_RPCException($message, $error->code); break;
            }

            $e->data = $error->data;
            throw $e;
        }
        else
        {
            return isset($response->result) ? $response->result : null;
        }
    }

    protected function _normalize($data)
    {
        $normalized = '';

        if (is_array($data))
        {
            if (isset($data[0]))
            {
                foreach ($data as $value)
                {
                    $normalized .= is_array($value) ? $this->_normalize($value) : $value;
                }
            }
            else
            {
                ksort($data);
                foreach ($data as $key => $value)
                {
                    $normalized .= $key . (is_array($value) ? $this->_normalize($value) : $value);
                }
            }
        }
        else
        {
            $normalized = $data;
        }

        return $normalized;
    }

    protected function _sign_url($url, $secret, $seclevel=CLOUDKEY_SECLEVEL_NONE, $asnum=null, $ip=null, $useragent=null, $countries=null, $referers=null, $expires=null)
    {
        // Compute digest
        $expires = intval($expires == null ? time() + 7200 : $expires);
        @list($url, $query) = @explode('?', $url, 2);
        $secparams = '';
        $public_secparams = array();
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
            if ($seclevel & CLOUDKEY_SECLEVEL_COUNTRY)
            {
                if (!isset($countries) || count($countries) === 0)
                {
                    throw new InvalidArgumentException('COUNTRY security level required and no country list provided.');
                }
                if (!is_array($countries))
                {
                    throw new InvalidArgumentException('Invalid format for COUNTRY, should be an array of country codes.');
                }
                if ($countries[0] === '-')
                {
                    array_shift($countries);
                    $countries = '-' . implode(',', $countries);
                }
                else
                {
                    $countries = implode(',', $countries);
                }
                if (!preg_match('/^-(?:[a-zA-Z]{2})(:?,[a-zA-Z]{2})*$/', $countries))
                {
                    throw new InvalidArgumentException('Invalid format for COUNTRY security level parameter.');
                }
                $public_secparams[] = 'cc=' . strtolower($useragent);
            }
            if ($seclevel & CLOUDKEY_SECLEVEL_REFERER)
            {
                if (!isset($referers) || count($referers) === 0)
                {
                    throw new InvalidArgumentException('REFERER security level required and no referer list provided.');
                }
                if (!array($referers))
                {
                    throw new InvalidArgumentException('Invalid format for REFERER, should be a list of url strings.');
                }
                $public_secparams[] = 'rf=' . urlencode(implode(' ', array_map(create_function('$r', 'return str_replace(" ", "%20", $r);'), $referers)));
            }
        }
        $public_secparams_encoded = '';
        if (count($public_secparams) > 0)
        {
            $public_secparams_encoded = base64_encode(gzcompress(implode('&', $public_secparams)));
        }
        $rand    = '';
        $letters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < 8; $i++)
        {
            $rand .= $letters[rand(0, 35)];
        }
        $digest = md5(implode('', array($seclevel, $url, $expires, $rand, $secret, $secparams, $public_secparams_encoded)));

        // Return signed URL
        return sprintf('%s?%sauth=%s-%d-%s-%s%s', $url, ($query ? $query . '&' : ''), $expires, $seclevel, $rand, $digest, ($public_secparams_encoded ? '-' . $public_secparams_encoded : ''));
    }
}

class CloudKey_Exception extends Exception {}
class CloudKey_RPCException extends CloudKey_Exception {public $data = null;}
class CloudKey_ProcessorException extends CloudKey_RPCException {}
class CloudKey_TransportException extends CloudKey_RPCException {}
class CloudKey_SerializerException extends CloudKey_RPCException {}
class CloudKey_AuthenticationErrorException extends CloudKey_RPCException {}
class CloudKey_RateLimitExceededException extends CloudKey_AuthenticationErrorException {}

class CloudKey_InvalidRequestException extends CloudKey_RPCException {}
class CloudKey_InvalidObjectException extends CloudKey_InvalidRequestException {}
class CloudKey_InvalidMethodException extends CloudKey_InvalidRequestException {}
class CloudKey_InvalidParamException extends CloudKey_InvalidRequestException {}

class CloudKey_ApplicationException extends CloudKey_RPCException {}
class CloudKey_NotFoundException extends CloudKey_ApplicationException {}
class CloudKey_ExistsException extends CloudKey_ApplicationException {}
class CloudKey_LimitExceededException extends CloudKey_ApplicationException {}

