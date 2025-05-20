<?php
/**
 * User: Vnser
 * Date: 16-07-29
 * Time: 下午11:08
 */

namespace app\lib;
class Curl
{

    //错误信息
    static private $error_code = array(
        1 => '您传送给 libcurl 的网址使用了此 libcurl 不支持的协议。 可能是您没有使用的编译时选项造成了这种情况（可能是协议字符串拼写有误，或没有指定协议 libcurl 代码）。',
        2 => '非常早期的初始化代码失败。 可能是内部错误或问题。',
        3 => '网址格式不正确。',
        5 => '无法解析代理服务器。 指定的代理服务器主机无法解析。',
        6 => '无法解析主机。 指定的远程主机无法解析。',
        7 => '无法通过 connect() 连接至主机或代理服务器。',
        8 => '在连接到 FTP 服务器后，libcurl 需要收到特定的回复。 此错误代码表示收到了不正常或不正确的回复。 指定的远程服务器可能不是正确的 FTP 服务器。',
        9 => '我们无法访问网址中指定的资源。 对于 FTP，如果尝试更改为远程目录，就会发生这种情况。',
        11 => '在将 FTP 密码发送到服务器后，libcurl 需要收到正确的回复。 此错误代码表示返回的是意外的代码。',
        13 => 'libcurl 无法从服务器端收到有用的结果，作为对 PASV 或 EPSV 命令的响应。 服务器有问题。',
        14 => 'FTP 服务器返回 227 行作为对 PASV 命令的响应。 如果 libcurl 无法解析此行，就会返回此代码。',
        15 => '在查找用于新连接的主机时出现内部错误。',
        17 => '在尝试将传输模式设置为二进制或 ascii 时发生错误。',
        18 => '文件传输尺寸小于或大于预期。 当服务器先报告了一个预期的传输尺寸，然后所传送的数据与先前指定尺寸不相符时，就会发生此错误。',
        19 => '‘RETR’ 命令收到了不正常的回复，或完成的传输尺寸为零字节。',
        21 => '在向远程服务器发送自定义 “QUOTE” 命令时，其中一个命令返回的错误代码为 400 或更大的数字（对于 FTP），或以其他方式表明命令无法成功完成。',
        22 => '如果 CURLOPT_FAILONERROR 设置为 TRUE，且 HTTP 服务器返回 >= 400 的错误代码，就会返回此代码。 （此错误代码以前又称为 CURLE_HTTP_NOT_FOUND。）',
        23 => '在向本地文件写入所收到的数据时发生错误，或由写入回调 (write callback) 向 libcurl 返回了一个错误。',
        25 => '无法开始上传。 对于 FTP，服务器通常会拒绝执行 STOR 命令。 错误缓冲区通常会提供服务器对此问题的说明。 （此错误代码以前又称为 CURLE_FTP_COULDNT_STOR_FILE。）',
        26 => '读取本地文件时遇到问题，或由读取回调 (read callback) 返回了一个错误。',
        27 => '内存分配请求失败。 此错误比较严重，若发生此错误，则表明出现了非常严重的问题。',
        28 => '请求操作超时。 已达到根据相应情况指定的超时时间。',
        30 => 'FTP PORT 命令返回错误。 在没有为 libcurl 指定适当的地址使用时，最有可能发生此问题。 请参阅 CURLOPT_FTPPORT。',
        31 => 'FTP REST 命令返回错误。 如果服务器正常，则应当不会发生这种情况。',
        33 => '服务器不支持或不接受范围请求。',
        34 => '此问题比较少见，主要由内部混乱引发。',
        35 => '同时使用 SSL/TLS 时可能会发生此错误。 您可以访问错误缓冲区查看相应信息，其中会对此问题进行更详细的介绍。 可能是证书（文件格式、路径、许可）、密码及其他因素导致了此问题。',
        36 => '尝试恢复超过文件大小限制的 FTP 连接。',
        37 => '无法打开 FILE:// 路径下的文件。 原因很可能是文件路径无法识别现有文件。 建议您检查文件的访问权限。',
        38 => 'LDAP 无法绑定。LDAP 绑定操作失败。',
        39 => 'LDAP 搜索无法进行。',
        41 => '找不到函数。 找不到必要的 zlib 函数。',
        42 => '由回调中止。 回调向 libcurl 返回了 “abort”。',
        43 => '内部错误。 使用了不正确的参数调用函数。',
        45 => '界面错误。 指定的外部界面无法使用。 请通过 CURLOPT_INTERFACE 设置要使用哪个界面来处理外部连接的来源 IP 地址。 （此错误代码以前又称为 CURLE_HTTP_PORT_FAILED。）',
        47 => '重定向过多。 进行重定向时，libcurl 达到了网页点击上限。 请使用 CURLOPT_MAXREDIRS 设置上限。',
        48 => '无法识别以 CURLOPT_TELNETOPTIONS 设置的选项。 请参阅相关文档。',
        49 => 'telnet 选项字符串的格式不正确。',
        51 => '远程服务器的 SSL 证书或 SSH md5 指纹不正确。',
        52 => '服务器未返回任何数据，在相应情况下，未返回任何数据就属于出现错误。',
        53 => '找不到指定的加密引擎。',
        54 => '无法将选定的 SSL 加密引擎设为默认选项。',
        55 => '无法发送网络数据。',
        56 => '接收网络数据失败。',
        58 => '本地客户端证书有问题',
        59 => '无法使用指定的密钥',
        60 => '无法使用已知的 CA 证书验证对等证书',
        61 => '无法识别传输编码',
        62 => 'LDAP 网址无效',
        63 => '超过了文件大小上限',
        64 => '请求的 FTP SSL 级别失败',
        65 => '进行发送操作时，curl 必须回转数据以便重新传输，但回转操作未能成功',
        66 => 'SSL 引擎初始化失败',
        67 => '远程服务器拒绝 curl 登录（7.13.1 新增功能）',
        68 => '在 TFTP 服务器上找不到文件',
        69 => '在 TFTP 服务器上遇到权限问题',
        70 => '服务器磁盘空间不足',
        71 => 'TFTP 操作非法',
        72 => 'TFTP 传输 ID 未知',
        73 => '文件已存在，无法覆盖',
        74 => '运行正常的 TFTP 服务器不会返回此错误',
        75 => '字符转换失败',
        76 => '调用方必须注册转换回调',
        77 => '读取 SSL CA 证书时遇到问题（可能是路径错误或访问权限问题）',
        78 => '网址中引用的资源不存在',
        79 => 'SSH 会话中发生无法识别的错误',
    );
    static private $_instance;
    //上次访问错误号
    public $errno = 0;
    //上次访问信息
    private $request = [];
    //请求响应信息
    private $response = [];
    //选项信息
    protected $option = array(
        'post' => FALSE,//是否post请求
        'url' => NULL,//请求url
        'header' => NULL,//响应头
        'data' => NULL,//数据信息
        'upload' => NULL,//上传文件
        'cookie' => NULL,//cookie数据
        'cert' => NULL,//证书
    );
    //配置信息
    private $config = array(
        'CURLOPT_TIMEOUT' => 60,//请求最大时间
        'CURLOPT_HEADER' => TRUE,//是否请求header
    );

    /**
     * 入口方法取得实例对象
     * @param string|array $option
     * @return Curl
     * */
    static public function main($option = '')
    {
        if (!isset(self::$_instance))
            self::$_instance = new self;

        if (is_array($option)) {
            self::$_instance->option = array_merge(self::$_instance->option, $option);
        } else {
            if (!empty($option)) {
                self::$_instance->url($option);
            }
        }
        return self::$_instance;
    }

    /**
     * 构造方法
     * */
    public function __construct($option = '')
    {
        if (is_array($option)) {
            $this->option = array_merge($this->option, $option);
        } else {
            if (!empty($option)) {
                $this->url($option);
            }
        }
    }

    /**
     * 获取请求-请求头信息
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * 获取响应头信息
     * @return array
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * 魔术方法
     * */
    public function __set($key, $val)
    {
        $this->config[$key] = $val;
    }

    /**
     * 设置请求URL
     * @param string $url
     * @return self
     * */
    public function url($url)
    {
        $this->option['url'] = $url;
        return $this;
    }

    /**
     * 设置请求数据
     * @param array|string $data 请求数据
     * @return self
     * */
    public function data($data)
    {
        // $this->curl['orgdata'] = $data;//原始数据
        if (is_array($data)) {
            //数组
            $this->option['data'] = http_build_query($data);//self::joins('=','&',$data);
        }
        if (is_string($data)) {
            //字符串
            $this->option['data'] = $data;
        }
        return $this;
    }

    /**
     * 设置请求头
     * @param array|string $header
     * @return self
     * */
    public function header($header)
    {

        //$hea = array();
        if (is_string($header)) {
            //传参为字符串
            $this->option['header'][] = $header;
        }
        if (is_array($header)) {
            //传参为数组
            foreach ($header as $k => $v) {
                $this->option['header'][] = "{$k}: {$v}";
            }
        }
        /*$this->curl['header'] = $hea;*/
        return $this;
    }

    /**
     * 设置请求cookie数据
     * @param array|string $data 如果为字符串 则：name=user;pwd=123456
     * @return self
     * */
    public function cookie($data)
    {
        if (is_string($data)) {
            //传参为字符串
            $this->option['cookie'] = $data;
        }
        if (is_array($data)) {
            //传参为数组
            $this->option['cookie'] = self::joins('=', ';', $data);
        }
        //$this->option['cookie'] = is_array($data)?self::joins('=',';',$data):$data;
        return $this;
    }

    /**
     * 设置请求上传文件数据
     * @param string|array $file
     * @return self
     * @throws \Exception
     * */
    public function upload($file)
    {
        if (!is_array($file)) {

            $basename = basename($file);
            $o_file['file'] = array(
                'name' => $basename,
                'path' => $file
            );
            $file = $o_file;
        }
        foreach ($file as $k => $v) {
            if (!is_file($v['path']))#上传文件不存在
                throw new \Exception('上传文件目录不存在“' . $v['path'] . '”');
            $mime = mime_content_type($v['path']);
            $head = "{$k}\";filename=\"{$v['name']}\"\r\nContent-Type: {$mime}\r\nm:\"d";
            $content = file_get_contents($v['path']);
            //$f[$head] = $content;
            $this->option['upload'][$head] = $content;
        }
        return $this;
    }

    /**
     * 执行get请求
     * @param array|string $data
     * @return mixed
     * */
    public function get($data = NULL)
    {
        $this->option['post'] = FALSE;
        if ($data !== NULL)
            $this->data($data);

        if (isset($this->option['data'])) {
            //设置有数据的url
            $this->option['url'] = self::mergeUrlData($this->option['url'], $this->option['data']);
        }
        //执行请求
        return $this->exec();

    }

    /**
     * 执行post请求
     * @param string|array $data
     * @return mixed
     * */
    public function post($data = null)
    {

        //设置请求为post
        $this->option['post'] = true;
        if ($data !== NULL)
            $this->data($data);
//        if ($this->option['upload'] !== NULL) {
//            //有文件上传
//            if (isset($this->option['data'])) {
//                parse_str($this->option['data'], $query_arr);
//                $this->option['data'] = array_merge($query_arr, $this->option['upload']);
//            } else {
//                $this->option['data'] = $this->option['upload'];
//            }
//        }
        return $this->exec();
    }


    /**
     * 将制定数组链接为指定字符串
     * @param string $str 链接字符串1
     * @param string $str1 连接字符串2
     * @param array $arr 拼接数组
     * @return string
     * @throws \Exception 参数$arr必须为Array
     * */
    static public function joins($str, $str1, $arr)
    {
        //非数组
        if (!is_array($arr))
            throw new \Exception('参数$arr必须为Array');
        $arrs = array();
        //传参为数组
        foreach ($arr as $k => $v) {
            $arrs[] = "{$k}{$str}{$v}";
        }
        return join($str1, $arrs);
    }

    /**
     * url数据合并处理
     * @param string $url
     * @param string $data
     * @return string
     * */
    public function mergeUrlData($url, $data)
    {
        $parse_url = parse_url($url);
        $url = "{$parse_url['scheme']}://{$parse_url['host']}";
        if (isset($parse_url['port']))
            $url .= ":{$parse_url['port']}";
        if (isset($parse_url['path']))
            $url .= "{$parse_url['path']}?";
        if (isset($parse_url['query']))
            $url .= "{$parse_url['query']}";
        if (!empty($parse_url['query']))
            $url .= "&";
        $url .= $data;
        return $url;
    }

    /**
     * 设置证书
     * @param string $certPath 公钥证书路径
     * @param string $keyPath 私钥证书路径
     * @return object
     * */
    public function certificate($certPath, $keyPath = '')
    {
        $this->option['cert']['certPath'] = $certPath;
        if (!empty($keyPath))
            $this->option['cert']['keyPath'] = $keyPath;
        return $this;
    }

    /**
     * 获取最后一次请求错误信息
     * @return string
     * */
    public function getError()
    {
        return self::$error_code[$this->errno];
    }

    /**
     * 执行curl函数请求
     * @return mixed
     * */
    protected function exec()
    {


        $ch = curl_init();
        //请求地址
        curl_setopt($ch, CURLOPT_URL, $this->option['url']);
        //显示响应头
        curl_setopt($ch, CURLOPT_HEADER, $this->config['CURLOPT_HEADER']);
        //是否自动显示内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //执行请求最大时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['CURLOPT_TIMEOUT']);
        //是否执行post请求
        curl_setopt($ch, CURLOPT_POST, $this->option['post']);
        if (strtolower(parse_url($this->option['url'])['scheme']) == 'https') {
            //请求https协议
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法
            if (isset($this->option['cert'])) {
                if (count($this->option['cert']) == 2) {
                    curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                    curl_setopt($ch, CURLOPT_SSLCERT, $this->option['cert']['certPath']);
                    //默认格式为PEM，可以注释
                    curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                    curl_setopt($ch, CURLOPT_SSLKEY, $this->option['cert']['keyPath']);
                } else {
                    curl_setopt($ch, CURLOPT_SSLCERT, $this->option['cert']['certPath']);
                }
            }
        }

        if ($this->option['post'] === TRUE) {
            //post请求数据设置
            isset($this->option['data']) && curl_setopt($ch, CURLOPT_POSTFIELDS, $this->option['data']);

            curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
        }

        if (isset($this->option['header'])) {
            //设置请求头
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->option['header']);
        }

        if (isset($this->option['cookie'])) {
            //cookie数据设置
            curl_setopt($ch, CURLOPT_COOKIE, $this->option['cookie']);
        }

        $result = @curl_exec($ch);
        //赋值给对象属性
        $this->request = $this->option;
        //释放选项属性
        unset($this->option);
        if ($result === false or $result === NULL) {
            //获取内容出错
            $this->errno = curl_errno($ch);
            return false;
        }
        //header长度
        $headLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        if ($this->config['CURLOPT_HEADER']) {
            //截取内容
            $header = substr($result, 0, $headLength);
            $expHea = explode("\r\n", $header);
            foreach ($expHea as $v) {
                if (empty($v))
                    continue;
                $hea = explode(':', $v, 2);
                if (count($hea) == 1) {
                    $this->response['header'][] = trim($hea[0]);
                } else {
                    $this->response['header'][$hea[0]] = trim($hea[1]);
                }
            }
            $this->response['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->response['body'] = substr($result, $headLength)? : '';
        } else {
            $this->response['body'] = $result;
        }
        //关闭释放curl
        curl_close($ch);
        return $this->response['body'];
    }


}