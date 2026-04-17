<?php
use think\Db;
use Aliyun\Core\Config;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;

/**
 * 字符串截取，支持中文和其他编码
 * @param string $str     原始字符串
 * @param int    $start   起始位置
 * @param int    $length  截取长度
 * @param string $charset 编码
 * @param bool   $suffix  是否添加省略号
 * @return string
 */
function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true)
{
    if (function_exists("mb_substr")) {
        $slice = mb_substr($str, $start, $length, $charset);
    } elseif (function_exists('iconv_substr')) {
        $slice = iconv_substr($str, $start, $length, $charset);
        if (false === $slice) {
            $slice = '';
        }
    } else {
        $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312']  = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']     = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']    = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice . '...' : $slice;
}

/**
 * 文件非法检测，针对图片马
 * @param string $file 文件路径
 */
function feifa_file($file)
{
    $filename = UPLOAD_PATH . $file;
    $source = fopen($filename, 'rb');
    if (($size = filesize($filename)) > 512) {
        $hexs = bin2hex(fread($source, 512));
        fseek($source, $size - 512);
        $hexs .= bin2hex(fread($source, 512));
    } else {
        $hexs = bin2hex(fread($source, $size));
    }
    if (is_resource($source)) fclose($source);
    $bins = hex2bin($hexs);
    foreach (['<?php ', '<% ', '<script '] as $key) {
        if (stripos($bins, $key) !== false || preg_match("/(3C534352495054)|(2F5343524950543E)|(3C736372697074)|(2F7363726970743E)/is", $hexs)) {
            @unlink($filename);
            exit(json_encode(array('code' => -1, 'msg' => '上传失败,文件非法！'), JSON_UNESCAPED_UNICODE));
        }
    }
}

/**
 * 读取配置
 * @return array
 */
function load_config()
{
    $list = Db::name('config')->select();
    $config = [];
    foreach ($list as $k => $v) {
        $config[trim($v['name'])] = $v['value'];
    }
    return $config;
}

/**
 * 读取分站配置（已废弃，保留空函数避免调用报错）
 * @param int $memberid
 * @return array
 */
function load_config_child($memberid)
{
    return [];
}

/**
 * 验证手机号是否正确
 * @param string $mobile 手机号
 * @return bool
 */
function isMobile($mobile)
{
    if (!is_numeric($mobile)) {
        return false;
    }
    return preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $mobile) ? true : false;
}

/**
 * 发送短信
 * @param string $mobile   接收手机号
 * @param string $tplCode  短信模板
 * @param array  $tplParam 短信内容数组
 * @return array
 */
function sendMsg($mobile, $tplCode, $tplParam)
{
    if (empty($mobile) || empty($tplCode)) return array('Message' => '缺少参数', 'Code' => 'Error');
    if (!isMobile($mobile)) return array('Message' => '无效的手机号', 'Code' => 'Error');

    $accessKeyId     = config('alisms_appkey');
    $accessKeySecret = config('alisms_appsecret');
    $signName        = config('alisms_signname');
    if (empty($accessKeyId) || empty($accessKeySecret)) return array('Message' => '请先在后台配置短信参数', 'Code' => 'Error');

    $templateParam = $tplParam;
    $templateCode  = $tplCode;
    if (is_array($templateParam)) {
        foreach ($templateParam as $k => $v) {
            $templateCode = str_replace('${' . $k . '}', $v, $templateCode);
        }
    }

    $url = 'http://xw.wyisp.com/api/send_sms';
    $post_data = array(
        'id'      => $accessKeyId,
        'docking' => $accessKeySecret,
        'content' => "【{$signName}】" . $templateCode,
        'mobile'  => $mobile,
    );
    $o = '';
    foreach ($post_data as $k => $v) {
        $o .= "$k=" . urlencode($v) . '&';
    }
    $data = substr($o, 0, -1);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $content = curl_exec($ch);
    curl_close($ch);

    $smscode = json_decode($content, true);
    if ($smscode['code'] == '1') {
        $result['Code']    = "OK";
        $result['Message'] = "发送成功";
    } else {
        $result['Code']    = "0";
        $result['Message'] = "发送失败";
    }
    return $result;
}
/**
 * 生成网址的二维码，返回图片地址
 * @param string $token 唯一标识
 * @param string $url   二维码内容URL
 * @param int    $size  二维码尺寸
 * @return string
 */
function Qrcode($token, $url, $size = 8)
{
    $md5 = md5($token);
    $dir = date('Ymd') . '/' . substr($md5, 0, 10) . '/';
    $patch = 'qrcode/' . $dir;
    if (!file_exists($patch)) {
        mkdir($patch, 0755, true);
    }
    $file = 'qrcode/' . $dir . $md5 . '.png';
    if (!file_exists($file)) {
        QRcode::png($url, $file, 'L', $size, 2, true);
    }
    return $file;
}

/**
 * 循环删除目录和文件
 * @param string $dir_name 目录路径
 * @return bool
 */
function delete_dir_file($dir_name)
{
    $result = false;
    if (is_dir($dir_name)) {
        if ($handle = opendir($dir_name)) {
            while (false !== ($item = readdir($handle))) {
                if ($item != '.' && $item != '..') {
                    if (is_dir($dir_name . DS . $item)) {
                        delete_dir_file($dir_name . DS . $item);
                    } else {
                        unlink($dir_name . DS . $item);
                    }
                }
            }
            closedir($handle);
            if (rmdir($dir_name)) {
                $result = true;
            }
        }
    }
    return $result;
}

/**
 * 时间格式化（相对时间）
 * @param int $time 时间戳
 * @return string
 */
function formatTime($time)
{
    $t = time() - $time;
    $mon = (int)($t / (86400 * 30));
    if ($mon >= 1) return '一个月前';
    $day = (int)($t / 86400);
    if ($day >= 1) return $day . '天前';
    $h = (int)($t / 3600);
    if ($h >= 1) return $h . '小时前';
    $min = (int)($t / 60);
    if ($min >= 1) return $min . '分钟前';
    return '刚刚';
}

/**
 * 发送邮件
 * @param string $address    收件人邮箱
 * @param string $title      邮件标题
 * @param string $message    邮件正文
 * @param string $attachment 附件路径
 * @return array
 */
function SendMail($address, $title, $message, $attachment)
{
    $mail = new \phpmailer\phpmailer;
    $mail->isSMTP();
    $mail->CharSet    = "utf8";
    $mail->Host       = config("mail_host");
    $mail->SMTPAuth   = true;
    $mail->Username   = config("mail_username");
    $mail->Password   = config("mail_password");
    $mail->Port       = config("mail_port");
    $mail->SMTPSecure = "ssl";
    $mail->setFrom(config("mail_username"), config("mail_senduser"));
    $mail->addAddress($address, "");
    $mail->addReplyTo(config("mail_username"), "Reply");
    if (!empty($attachment)) {
        $mail->addAttachment($attachment);
    }
    $mail->Subject = "=?utf-8?B?" . base64_encode($title) . "?=";
    $mail->Body    = $message;
    if (!$mail->send()) {
        $code = 0;
        $msg  = $mail->ErrorInfo;
    } else {
        $code = 1;
        $msg  = '发送成功';
    }
    return array('code' => $code, 'msg' => $msg);
}

/**
 * 获取客户端IP地址
 * @return string
 */
function getIP()
{
    if (getenv("HTTP_CLIENT_IP")) {
        $ip = getenv("HTTP_CLIENT_IP");
    } elseif (getenv("HTTP_X_FORWARDED_FOR")) {
        $ip = getenv("HTTP_X_FORWARDED_FOR");
    } elseif (getenv("REMOTE_ADDR")) {
        $ip = getenv("REMOTE_ADDR");
    } else {
        $ip = "Unknow";
    }
    return $ip;
}

/**
 * 判断是否为移动设备
 * @return bool
 */
function isMobilePc()
{
    if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
        return true;
    }
    if (isset($_SERVER['HTTP_VIA'])) {
        return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
    }
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $clientkeywords = array(
            'nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg',
            'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone',
            'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb',
            'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone',
            'cldc', 'midp', 'wap', 'mobile'
        );
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
            return true;
        }
    }
    if (isset($_SERVER['HTTP_ACCEPT'])) {
        if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
            return true;
        }
    }
    return false;
}

/**
 * 判断是否微信浏览器
 * @return bool
 */
function is_weixin()
{
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
        return true;
    }
    return false;
}

/**
 * 判断是否QQ浏览器
 * @return bool
 */
function is_qq()
{
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/') !== false) {
        return true;
    }
    return false;
}

/**
 * 判断是否微信或QQ浏览器
 * @return bool
 */
function is_weixinorqq()
{
    return is_weixin() || is_qq();
}

/**
 * 统一返回格式
 * @param string $msg  消息
 * @param int    $code 状态码
 * @param array  $data 数据
 * @return array
 */
function TyReturn($msg, $code = -1, $data = [])
{
    $rs = ['code' => $code, 'msg' => $msg];
    if (!empty($data)) $rs['data'] = $data;
    return $rs;
}

/**
 * 为空判断
 * @param mixed $str
 * @return bool
 */
function SuperIsEmpty($str)
{
    return empty($str);
}

/**
 * 过滤所有空格
 * @param string $str
 * @return string
 */
function myTrim($str)
{
    $search  = array(" ", "　", "\n", "\r", "\t");
    $replace = array("", "", "", "", "");
    return str_replace($search, $replace, $str);
}

/**
 * 记录会员金额日志
 * @param int    $memberid 会员ID
 * @param string $make     描述
 * @param int    $type     类型
 * @param float  $money    金额
 * @param string $userip   IP地址
 */
function writemoneylog($memberid, $make, $type, $money, $userip = '')
{
    if ($userip == '') {
        $userip = getIP();
    }
    $data = [
        'memberid'    => $memberid,
        'money'       => $money,
        'make'        => $make,
        'type'        => $type,
        'ip'          => $userip,
        'create_time' => time(),
    ];
    Db::name('member_money_log')->insert($data);
}

/**
 * 记录会员登录日志
 * @param int    $memberid 会员ID
 * @param string $make     描述
 */
function writeloginlog($memberid, $make)
{
    $data = [
        'memberid'    => $memberid,
        'description' => $make,
        'ip'          => getIP(),
        'create_time' => time(),
    ];
    Db::name('member_login_log')->insert($data);
}

/**
 * 记录会员积分日志
 * @param int    $memberid 会员ID
 * @param string $make     描述
 * @param int    $type     类型
 * @param int    $integral 积分
 */
function writeintegrallog($memberid, $make, $type, $integral)
{
    $data = [
        'memberid'    => $memberid,
        'integral'    => $integral,
        'make'        => $make,
        'type'        => $type,
        'ip'          => getIP(),
        'create_time' => time(),
    ];
    Db::name('member_integral_log')->insert($data);
}

/**
 * 记录管理员操作日志
 * @param int    $uid         管理员ID
 * @param string $username    管理员名称
 * @param string $description 描述
 * @param int    $status      状态
 */
function writelog($uid, $username, $description, $status)
{
    $data = [
        'admin_id'    => $uid,
        'admin_name'  => $username,
        'description' => $description,
        'status'      => $status,
        'ip'          => getIP(),
        'add_time'    => time(),
    ];
    Db::name('Log')->insert($data);
}

/**
 * 记录订单历史
 * @param array $param 订单数据
 */
function writeinfohistory($param)
{
    Db::name('info_history')->insert($param);
}

/**
 * 记录分销明细
 * @param array $param 分销数据
 */
function writetgmoneylog($param)
{
    Db::name('tgmoney_log')->insert($param);
}

/**
 * 记录发送日志
 * @param array $param 发送数据
 */
function writesendsmslog($param)
{
    Db::name('sendsms_log')->insert($param);
}

/**
 * 记录系统日志
 * @param array $param 包含make和level字段
 */
function writesystemlog($param)
{
    $header  = request()->header();
    $referer = isset($header['referer']) ? $header['referer'] : '';
    $systemLogData = [
        'url'         => strtolower($header['host']) . '/' . strtolower(request()->module()) . '/' . strtolower(request()->controller()) . '/' . strtolower(request()->action()),
        'referer'     => $referer,
        'userid'      => session('useraccount.id') ? session('useraccount.id') : 0,
        'ip'          => getIP(),
        'make'        => $param['make'],
        'level'       => $param['level'],
        'create_time' => time(),
    ];
    Db::name('system_log')->insert($systemLogData);
}

/**
 * 金额累计
 * @param int    $memberid 会员ID
 * @param float  $money    金额
 * @param string $type     类型字段名
 */
function writeamounttotal($memberid, $money, $type)
{
    $result = Db::name('amount_total_log')->where('memberid', $memberid)->find();
    if ($result == false) {
        $sql = "insert ignore into think_amount_total_log set " . $type . "=:addmoney,memberid=:memberid";
    } else {
        $sql = "update think_amount_total_log set " . $type . "=" . $type . "+:addmoney where memberid=:memberid";
    }
    Db::execute($sql, ['addmoney' => $money, 'memberid' => $memberid]);
}

/**
 * 取中间文本
 * @param string $str      全文本
 * @param string $leftStr  左边文本
 * @param string $rightStr 右边文本
 * @return string
 */
function getSubstr($str, $leftStr, $rightStr)
{
    $left = strpos($str, $leftStr);
    $right = strpos($str, $rightStr, $left);
    if ($left < 0 or $right <= $left) return '';
    return substr($str, $left + strlen($leftStr), $right - $left - strlen($leftStr));
}

/**
 * 获取后台路径
 * @return string
 */
function getadminpath()
{
    $routetext = file_get_contents(APP_PATH . "admin.php");
    $admin = getSubstr($routetext, "Route::rule('", "','admin/login/index')");
    return $admin;
}

/**
 * 生成随机数字
 * @param int $length 位数
 * @return int
 */
function generate_code($length = 6)
{
    return rand(pow(10, ($length - 1)), pow(10, $length) - 1);
}

/**
 * 加密函数
 * @param string $txt 明文
 * @param string $key 密钥
 * @return string
 */
function passport_encrypt($txt, $key)
{
    srand((double)microtime() * 1000000);
    $encrypt_key = md5(rand(0, 32000));
    $ctr = 0;
    $tmp = '';
    for ($i = 0; $i < strlen($txt); $i++) {
        $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
        $tmp .= $encrypt_key[$ctr] . ($txt[$i] ^ $encrypt_key[$ctr++]);
    }
    return base64_encode(passport_key($tmp, $key));
}

/**
 * 解密函数
 * @param string $txt 密文
 * @param string $key 密钥
 * @return string
 */
function passport_decrypt($txt, $key)
{
    $txt = passport_key(base64_decode($txt), $key);
    $tmp = '';
    for ($i = 0; $i < strlen($txt); $i++) {
        $md5 = $txt[$i];
        $tmp .= $txt[++$i] ^ $md5;
    }
    return $tmp;
}

/**
 * 加解密辅助函数
 * @param string $txt         文本
 * @param string $encrypt_key 密钥
 * @return string
 */
function passport_key($txt, $encrypt_key)
{
    $encrypt_key = md5($encrypt_key);
    $ctr = 0;
    $tmp = '';
    for ($i = 0; $i < strlen($txt); $i++) {
        $ctr = $ctr == strlen($encrypt_key) ? 0 : $ctr;
        $tmp .= $txt[$i] ^ $encrypt_key[$ctr++];
    }
    return $tmp;
}

/**
 * 生成唯一订单号（YYYYMMDDHHIISSNNNNNNNNCC）
 * @return string
 */
function createOrder()
{
    $order_id_main = date('YmdHis') . rand(10000000, 99999999);
    $order_id_len = strlen($order_id_main);
    $order_id_sum = 0;
    for ($i = 0; $i < $order_id_len; $i++) {
        $order_id_sum += (int)(substr($order_id_main, $i, 1));
    }
    $order_id = $order_id_main . str_pad((100 - $order_id_sum % 100) % 100, 2, '0', STR_PAD_LEFT);
    return $order_id;
}

/**
 * 判断是否IE浏览器
 * @return int
 */
function isIE()
{
    if (stripos($_SERVER['HTTP_USER_AGENT'], "Triden") !== false || stripos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== false) {
        return 1;
    }
    return 0;
}

/**
 * 判断Android或iPhone
 * @return int 1=Android 2=iPhone/iPad
 */
function androidOriphone()
{
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    if (strpos($agent, 'android')) {
        return 1;
    }
    if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
        return 2;
    }
}

/**
 * 替换分站商品信息
 * @param array $childFlData 分站商品数据
 * @param array $dataFl      主站商品数据
 * @return array
 */
function replaceChild($childFlData, $dataFl)
{
    $fields = [
        'mname'       => -1,
        'mprice_bz'   => -1,
        'mnotice'     => -1,
        'xqnotice'    => -1,
        'sort'        => -1,
        'msgboxtip'   => -1,
        'tuijian'     => -1,
        'hot'         => -1,
        'ykongge'     => -1,
        'zkongge'     => -1,
        'color'       => -1,
        'kamitou'     => -1,
        'kamiwei'     => -1,
    ];
    foreach ($fields as $field => $default) {
        if ($childFlData[$field] != $default) {
            $dataFl[$field] = $childFlData[$field];
        }
    }
    if ($childFlData['mprice'] > 0) {
        $dataFl['mprice'] = $childFlData['mprice'];
    }
    if ($childFlData['marketprice'] > 0) {
        $dataFl['marketprice'] = $childFlData['marketprice'];
    }
    if ($childFlData['imgurl'] != -1) {
        $dataFl['imgurl'] = $childFlData['imgurl'];
        if (!empty($childFlData['yunimgurl'])) {
            $dataFl['yunimgurl'] = $childFlData['yunimgurl'];
        }
        if (!empty($childFlData['imgurl'])) {
            $dataFl['imgurl'] = $childFlData['imgurl'];
        }
    }
    return $dataFl;
}

/**
 * 主站处理图片URL
 * @param array $v 商品数据
 * @return array
 */
function replaceImgurl($v)
{
    $v['yunimgurl']  = str_replace('\\', '/', $v['yunimgurl']);
    $v['imgurl']     = str_replace('\\', '/', $v['imgurl']);
    $v['webimgurl']  = $v['imgurl'];
    if (!empty($v['yunimgurl'])) {
        $domain = config('qiniu_domain');
        $v['valueimgurl'] = $v['yunimgurl'];
        $v['imgurl']      = $domain . '/' . $v['yunimgurl'];
        $v['webimgurl']   = $v['imgurl'];
        return $v;
    }
    if (!empty($v['imgurl'])) {
        $v['valueimgurl'] = $v['imgurl'];
        $v['imgurl']      = '/uploads/face/' . $v['imgurl'];
        $v['webimgurl']   = $v['imgurl'];
        return $v;
    }
    $v['valueimgurl'] = $v['imgurl'];
    $v['imgurl']      = '/static/admin/images/head_default.gif';
    $v['webimgurl']   = $v['imgurl'];
    return $v;
}

/**
 * 子站处理图片URL
 * @param array $v 商品数据
 * @return array
 */
function replacezImgurlfImgurl($v)
{
    $v['yunimgurl']  = str_replace('\\', '/', $v['yunimgurl']);
    $v['imgurl']     = str_replace('\\', '/', $v['imgurl']);
    $v['webimgurl']  = $v['imgurl'];
    if (!empty($v['yunimgurl'])) {
        $domain = config('qiniu_domain');
        $v['imgurl']    = $domain . '/' . $v['yunimgurl'];
        $v['webimgurl'] = $v['imgurl'];
        return $v;
    }
    if (!empty($v['imgurl'])) {
        $v['imgurl']    = '/uploads/face/' . $v['imgurl'];
        $v['webimgurl'] = $v['imgurl'];
        return $v;
    }
    $v['imgurl']    = '/static/admin/images/head_default.gif';
    $v['webimgurl'] = $v['imgurl'];
    return $v;
}

/**
 * 过滤emoji表情
 * @param string $str
 * @return string
 */
function filterEmoji($str)
{
    $str = preg_replace_callback(
        '/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str
    );
    return $str;
}

/**
 * 封装input函数
 * @return array
 */
function inputself()
{
    header("Cache-control:private");
    $param    = input('param.');
    $data     = [];
    $pathinfo = input('server.PATH_INFO');
    $pathinfo = str_replace('.' . config('template.view_suffix'), '_' . config('template.view_suffix'), $pathinfo);
    foreach ($param as $key => $val) {
        if ($key != $pathinfo) {
            $data[$key] = $val;
        }
    }
    return $data;
}

/**
 * 检测文件BOM头
 * @param string $filename 文件路径
 * @return bool
 */
function checkBOM($filename)
{
    if (!file_exists($filename)) {
        return false;
    }
    $contents = file_get_contents($filename);
    $charset[1] = substr($contents, 0, 1);
    $charset[2] = substr($contents, 1, 1);
    $charset[3] = substr($contents, 2, 1);
    if (ord($charset[1]) == 239 && ord($charset[2]) == 187 && ord($charset[3]) == 191) {
        return true;
    }
    return false;
}

/**
 * HTTP POST请求
 * @param string $url   请求地址
 * @param array  $param POST参数
 * @return string
 */
function http_request($url, $param = array())
{
    if (!is_array($param)) {
        throw new Exception("参数必须为array");
    }
    $httph = curl_init($url);
    curl_setopt($httph, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($httph, CURLOPT_FOLLOWLOCATION, 2);
    curl_setopt($httph, CURLOPT_RETURNTRANSFER, 2);
    curl_setopt($httph, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)");
    curl_setopt($httph, CURLOPT_POST, 1);
    curl_setopt($httph, CURLOPT_POSTFIELDS, $param);
    curl_setopt($httph, CURLOPT_HEADER, 0);
    $rst = curl_exec($httph);
    curl_close($httph);
    return $rst;
}

/**
 * 屏蔽部分蜘蛛抓取
 */
function off_spider()
{
    $ua = $_SERVER['HTTP_USER_AGENT'];
    if (stripos($ua, "bot") !== false || stripos($ua, "spider") !== false || stripos($ua, "http") !== false || stripos($ua, "lib") !== false || stripos($ua, "java") !== false) {
        exit(header("status: 404 Not Found"));
    }
}

/**
 * 版本号转整数
 * @param string $ver 版本号如 1.2.3
 * @return int
 */
function versionToInteger($ver)
{
    $ver = explode(".", $ver);
    $str = "";
    foreach ($ver as $k => $v) {
        $str .= str_pad(($v ? $v : 0), 3, 0, STR_PAD_LEFT);
    }
    $str = str_pad($str, 9, "0", STR_PAD_RIGHT);
    return (int)"{$str}";
}

/**
 * 整数转版本号字符串
 * @param int $ver 版本整数
 * @return string
 */
function versionToString($ver)
{
    if ($ver > 999) {
        if ($ver > 999999) {
            $ver = $ver . "";
            $str = (int)substr($ver, 0, strlen($ver) - 6) . '.' . (int)substr($ver, -6, 3) . '.' . (int)substr($ver, -3);
        } else {
            $ver = $ver . "";
            $str = (int)substr($ver, 0, strlen($ver) - 3) . '.' . (int)substr($ver, -3);
        }
    } else {
        $str = $ver;
    }
    return "{$str}";
}
