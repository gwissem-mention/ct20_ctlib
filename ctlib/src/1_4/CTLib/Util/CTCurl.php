<?php
namespace CTLib\Util;

class CTCurl extends Curl
{
    const REQUEST_POST = "POST";
    const REQUEST_GET = "GET";
    const REQUEST_PUT = "PUT";
    const REQUEST_DELETE = "DELETE";
    const USER_AGENT = "celltrak 2.0";
    
    protected $data = array();
    protected $method = null;
    protected $contentLength = null;
    protected $contentType = null;
    protected $isBatch = false;
    protected $batchLimit = null;
    protected $dataNumericPrefix = null;

    public function __set($opt, $value)
    {
        $opt = strtoupper($opt);
        switch ($opt) {
            case "METHOD":
                $this->method = $value;
                switch ($value) {
                    case self::REQUEST_POST:
                        parent::__set("POST", true);
                    break;

                    case self::REQUEST_GET:
                        parent::__set("HTTPGET", true);
                    break;
                
                    case self::REQUEST_PUT:
                        parent::__set("CUSTOMREQUEST", "PUT");
                    break;
                
                    case self::REQUEST_DELETE:
                        parent::__set("CUSTOMREQUEST", "DELETE");
                    break;
                
                    default:
                        throw new \Exception("Request method is invalid");
                }
                break;
            case "DATA":
                if (!is_array($value)) {
                    throw new \Exception("data property {$opt} has to be an array");
                }
                $this->data = $value;
                break;
            case "ISBATCH":
                if (!is_bool($value)) {
                    throw new \Exception("data property {$opt} has to be a boolean value");
                }
                $this->isBatch = $value;
                break;
            case "BATCHLIMIT":
                if (!is_int($value)) {
                    throw new \Exception("data property {$opt} has to be int");
                }
                $this->batchLimit = $value;
                break;
            /*
            case "CONTENTLENGTH":
                $this->contentLength = "Content-length: {$value}";
                break;
            case "CONTENTTYPE":
                $this->contentType = "Content-Type: {$value}";
                break;
            */
        }

        parent::__set($opt, $value);
    }

    public function __get($opt)
    {
        $opt = strtoupper($opt);
        switch ($opt) {
            case "METHOD":
                return $this->method;
            case "DATA":
                return $this->data;
            case "ISBATCH":
                return $this->isBatch;
            case "BATCHLIMIT":
                return $this->batchLimit;
            /*
            case "CONTENTLENGTH":
                return $this->contentLength;
            case "CONTENTTYPE":
                return $this->contentType;
            */
        }

        return parent::__get($opt);
    }

    public function exec()
    {
	    if (isset($this->PostFields)) {
	        return parent::exec();
	    }

        if (empty($this->data)) {
            throw new \Exception("request data does not exist!");
        }

        if (empty($this->url)) {
            throw new \exception("request url does not exist!");
        }

        $this->useragent = self::USER_AGENT;

        if (!$this->isBatch) {
            $this->buildRequestData($this->data);
            $response = parent::exec();
            if ($response === false) {
                throw new \Exception($this->error(), $this->errno());
            }
            return $response;
        }

        //process batch data
        $result = array();
        for($i = 0; $i < count($this->data); $i += $this->batchLimit)
        {
            $batchCount = $this->batchLimit;
            if ($i + $this->batchLimit > count($this->data)) {
                $batchCount = count($this->data) - $i;
            }

            $batchData = array();
            for ($j = $i; $j < $i + $batchCount; $j++) {
                $batchData[] = $this->data[$j];
            }

            $this->buildRequestData($batchData);

            $response = parent::exec();

            if ($response === false) {
                throw new \Exception($this->error(), $this->errno());
            }
            $result[] = $response;
        }

        return $result;
    }

    protected function buildRequestData($data)
    {
        $queryString = "";
        if (Arr::isAssociative($data)) {
            $queryString = http_build_query($data);
        }
        else {
            foreach ($data as $row) {
                if (is_array($row) || is_object($row)) {
                    $queryString .= http_build_query($row) . "&";
                }
                else {
                    $queryString .= urlencode($row) . "&";
                }
            }
            $queryString = rtrim($queryString, "&");
        }
        if ($this->method == self::REQUEST_GET) {
            $urlQuery = parse_url($this->url, \PHP_URL_QUERY);
            if ($urlQuery) {
                $this->url .= "&" . $queryString;
            }
            else {
                $this->url .= "?" . $queryString;
            }
        }
        else {
            $this->PostFields = $queryString;
        }
    }
}
