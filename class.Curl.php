<?php
/*

Copyright (c) 2012 Dave Koopman

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
    define('VERIFYHOST', false);
    define('MAXREDIRS', 10);
    #define('USERAGENT', "Lynx/2.8.5rel.1 libwww-FM/2.14 SSL-MM/1.4.1 OpenSSL/0.9.8e-fips-rhel5");
    define('USERAGENT', "User-Agent=Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
    
 
class Curl
{
    public $url;
    public $response_code;
    public $response_header;
    public $response_headers;
    public $response_body;
    public $cookieJar;
    private $response;
    private $ch;
    static $stderr = null;
 
    public function __construct($cookieJar=false)
    {
        $this->cookieJar = $cookieJar ? $cookieJar : tempnam("/tmp", "cookieJar");
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, VERIFYHOST);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, VERIFYHOST);
        curl_setopt ($this->ch, CURLOPT_USERAGENT, USERAGENT);
        curl_setopt ($this->ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt ($this->ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt ($this->ch, CURLOPT_CRLF, true);
        curl_setopt ($this->ch, CURLOPT_HEADER, true);
        curl_setopt ($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt ($this->ch, CURLOPT_ENCODING, "gzip"); // "" means all supported
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, MAXREDIRS);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_USERAGENT, USERAGENT);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
 
        curl_setopt($this->ch, CURLOPT_HTTPHEADER,
                                array(
                                "Accept: */*",
                                "Accept-Language: en-US"
                                ));
    }
 
    public function __destruct()
    {
        // curl_close($this->ch);
    }
 
    public function nextpage($url, $method='GET', $data=false, $referer=false, $extraPost=false)
    {
        $this->url = $url;
        if ( $referer )
        curl_setopt ($this->ch, CURLOPT_REFERER, $referer);
        if (strtoupper($method)=='POST')
        {
            curl_setopt($this->ch, CURLOPT_POST, 1);
            $postdata = array();
            foreach ($data as $key=>$val)
                $postdata[] = urlencode($key)."=".urlencode($val);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, implode("&", $postdata).($extraPost ? (count($postdata)>0 ? '&' : '').$extraPost : ""));
            if ( $extraPost && count($postdata)==0)
            curl_setopt($this->ch, CURLOPT_HTTPHEADER,
                                array(
                                "Accept: */*",
                                "Accept-Language: en-US",
                                "Content-Type: application/json; charset=utf-8"
                                ));
        }
        else
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
 
        curl_setopt($this->ch, CURLOPT_URL, $url);
 
        $this->response = curl_exec($this->ch);
        $this->parse_response();
 
        $this->url = $this->getUrl();
 
        return $this->response_body;
    }
 
    private function parse_response()
    {
        // Split response into header and body sections
        list($this->response_header, $this->response_body) = split("\r?\n\r?\n", $this->response, 2);
        $response_header_lines = split("\r?\n", $this->response_header);
 
        // First line of headers is the HTTP response code
        $http_response_line = array_shift($response_header_lines);
        if(preg_match('@^HTTP/[0-9]\.[0-9] ([0-9]{3})@',$http_response_line, $matches))
        {
            $this->response_code = $matches[1];
        }
 
        // put the rest of the headers in an array
        $this->response_headers = array();
        foreach($response_header_lines as $header_line)
        {
        if ( preg_match("/^\w/", $header_line) )
            list($header,$value) = explode(': ', $header_line, 2);
        else
            $value = $header_line;
        $this->response_headers[$header] .= ( $this->response_headers[$header] ? "\n" : "") . $value;
        }
    }
 
    public function getUrl()
    {
        return curl_getinfo ( $this->ch, CURLINFO_EFFECTIVE_URL );
    }
}
