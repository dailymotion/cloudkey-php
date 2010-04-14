<?php

class CloudKey
{
    private
        $namespaces = array();

    protected
        $username = null,
        $password = null,
        $base_url = null,
        $proxy = null;

    function __construct($username, $password, $base_url = null, $proxy = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->base_url = $base_url;
        $this->proxy = $proxy;
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
            $this->namespaces[$name] = new $class($this->username, $this->password, $this->base_url, null, $this->proxy);
            $this->namespaces[$name]->parent = $this;
        }

        return $this->namespaces[$name];
    }

}

class CloudKey_User extends CloudKey_Base
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

class CloudKey_Media extends CloudKey_Base
{
}

class CloudKey_File extends CloudKey_Base
{
}

class CloudKey_Base
{
    protected
        $username = null,
        $password = null,
        $base_url = 'http://api.dmcloud.net',
        $namespace = null,
        $proxy = null;

    public
        $connect_timeout = 120,
        $response_timeout = 120;

    function __construct($username, $password, $base_url = null, $namespace = null, $proxy = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->proxy = $proxy;

        if ($base_url !== null)
        {
            $this->base_url = $base_url;
        }

        if (get_class($this) !== 'CloudKey_Base' && $namespace === null)
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
            if (true)
            {
                $url .= '?' . http_build_query($params);
            }
            else
            {
                curl_setopt_array($ch, array
                (
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $params,
                ));
            }
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
                }
                break;

            case 401: throw new CloudKey_AuthorizationRequiredException($method);
            case 403: throw new CloudKey_AuthenticationFailedException($method);
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