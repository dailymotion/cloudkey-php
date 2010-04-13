<?php

class WApiC
{
    protected $login, $password, $base_url, $namespace, $proxy;

    public
        $connect_timeout = 120,
        $response_timeout = 120;

    public __construct($login, $password, $base_url, $namespace = null, $proxy = null)
    {
        $this->login = $login;
        $this->password = $password;
        $this->base_url = $base_url;
        $this->proxy = $proxy;

        if (__CLASS__ !== 'WApiC' && $namespace === null)
        {
            $this->namespace = strtolower(__CLASS__);
        }
        else
        {
            $this->namespace = $namespace;
        }
    }

    public function __call($method, $args)
    {
        $params = '';
        if (isset($args[0])) && !is_array($args[0]))
        {
            throw new InvalidArgumentException(sprintf('%s requires an array as first argument', $method));
            $params = http_build_query($args[0]);
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

        $url = sprintf('%s/json/%s%s', $this->base_url, $path, $params);

        $ch = curl_init();
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
            // HANDLE ERRORS
        }

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)
        curl_close($ch);

        switch ($status_code)
        {
            case 404:
            case 400:
                $result = json_decode($json_response);
                if ($result === null)
                {
                    throw new BadMethodCallException('This method does not exists: ' . $method);
                }
                switch ($result['type'])
                {
                    case 'ApiNotFound':     throw new WApiC_NotFoundException();
                    case 'ApiMissingParam': throw new WApiC_MissingParamException();
                    case 'ApiInvalidParam': throw new WApiC_InvalidParamException();
                }
                break;

            case 401: throw new WApiC_AuthorizationRequiredException();
            case 403: throw new WApiC_AuthenticationFailedException();
            case 204: return null; // Empty response

            default:
                if ($status_code >= 200 && $status_code < 400)
                {
                    return json_decode($json_response);
                }
                else
                {
                    throw WApiC_Exception($status_code);
                }
        }
    }
}

class WApiC_Exception extends Exception {}
class WApiC_NotFoundException extends WApiC_Exception {}
class WApiC_MissingParamException extends WApiC_Exception {}
class WApiC_InvalidParamException extends WApiC_Exception {}
class WApiC_AuthenticationFailedException extends WApiC_Exception {}
class WApiC_AuthorizationRequiredException extends WApiC_Exception {}