<?php

namespace app\api\model;
use think\Model;
use think\Db;

class PaymentApiModel extends Model
{
    /**
     * 生成支付二维码
     */
    public function generateQRCode($order_no, $pay_type)
    {
        try {
            // 获取订单信息
            $order = Db::name('info')->where('morder', $order_no)->find();
            if (!$order) {
                return ['code' => 0, 'msg' => '订单不存在'];
            }
            
            if ($order['mstatus'] != 2) {
                return ['code' => 0, 'msg' => '订单状态异常，无法支付'];
            }
            
            // 根据支付类型生成支付链接
            $payment_url = '';
            $qr_code_url = '';
            
            switch ($pay_type) {
                case 'alipay':
                    $payment_url = $this->generateAlipayUrl($order);
                    break;
                case 'wechat':
                    $payment_url = $this->generateWechatUrl($order);
                    break;
                default:
                    return ['code' => 0, 'msg' => '不支持的支付类型'];
            }
            
            if (empty($payment_url)) {
                return ['code' => 0, 'msg' => '支付链接生成失败'];
            }
            
            // 生成二维码
            $qr_code_url = $this->generateQRCodeImage($payment_url, $order_no);
            
            return [
                'code' => 1,
                'msg' => '支付二维码生成成功',
                'data' => [
                    'order_no' => $order_no,
                    'pay_type' => $pay_type,
                    'amount' => $order['mamount'],
                    'payment_url' => $payment_url,
                    'qr_code_url' => $qr_code_url,
                    'expire_time' => time() + 900 // 15分钟过期
                ]
            ];
            
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => '生成支付二维码失败：' . $e->getMessage()];
        }
    }
    
    /**
     * 生成支付宝支付链接
     */
    private function generateAlipayUrl($order)
    {
        // 获取支付宝配置
        $alipay_config = $this->getAlipayConfig();
        
        if (empty($alipay_config['app_id']) || empty($alipay_config['private_key'])) {
            throw new \Exception('支付宝配置不完整');
        }
        
        // 构建支付参数
        $params = [
            'app_id' => $alipay_config['app_id'],
            'method' => 'alipay.trade.precreate',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => url('api/payment/alipay_notify', '', true, true),
            'biz_content' => json_encode([
                'out_trade_no' => $order['morder'],
                'total_amount' => $order['mamount'],
                'subject' => '商品购买-' . $order['morder'],
                'store_id' => 'API_STORE',
                'timeout_express' => '15m'
            ])
        ];
        
        // 生成签名
        $sign = $this->generateAlipaySign($params, $alipay_config['private_key']);
        $params['sign'] = $sign;
        
        // 构建请求URL
        $query_string = http_build_query($params);
        return $alipay_config['gateway'] . '?' . $query_string;
    }
    
    /**
     * 生成微信支付链接
     */
    private function generateWechatUrl($order)
    {
        // 获取微信支付配置
        $wechat_config = $this->getWechatConfig();
        
        if (empty($wechat_config['app_id']) || empty($wechat_config['mch_id']) || empty($wechat_config['key'])) {
            throw new \Exception('微信支付配置不完整');
        }
        
        // 构建支付参数
        $params = [
            'appid' => $wechat_config['app_id'],
            'mch_id' => $wechat_config['mch_id'],
            'nonce_str' => md5(time() . mt_rand(1000, 9999)),
            'body' => '商品购买-' . $order['morder'],
            'out_trade_no' => $order['morder'],
            'total_fee' => $order['mamount'] * 100, // 微信支付金额单位为分
            'spbill_create_ip' => getIP(),
            'notify_url' => url('api/payment/wechat_notify', '', true, true),
            'trade_type' => 'NATIVE'
        ];
        
        // 生成签名
        $sign = $this->generateWechatSign($params, $wechat_config['key']);
        $params['sign'] = $sign;
        
        // 构建XML
        $xml = $this->arrayToXml($params);
        
        // 发送请求（带重试机制）
        $response = $this->httpRequestWithRetry($wechat_config['gateway'], $xml);
        $result = $this->xmlToArray($response);
        
        if ($result['return_code'] != 'SUCCESS' || $result['result_code'] != 'SUCCESS') {
            throw new \Exception('微信支付请求失败：' . ($result['err_code_des'] ?? $result['return_msg']));
        }
        
        return $result['code_url'];
    }
    
    /**
     * 生成二维码图片
     */
    private function generateQRCodeImage($content, $order_no)
    {
        // 使用配置的二维码生成服务
        $qr_api = config('api.qr_code.service_url', 'https://api.qrserver.com/v1/create-qr-code/');
        $params = [
            'size' => config('api.qr_code.size', '200x200'),
            'data' => $content,
            'format' => config('api.qr_code.format', 'png')
        ];
        
        $qr_url = $qr_api . '?' . http_build_query($params);
        
        // 也可以保存到本地
        $local_path = config('api.qr_code.path', '/uploads/qrcode/') . date('Ymd') . '/';
        $local_file = $local_path . 'pay_' . $order_no . '.png';
        
        // 确保目录存在
        $full_path = UPLOAD_PATH . $local_file;
        if (!is_dir(dirname($full_path))) {
            mkdir(dirname($full_path), 0755, true);
        }
        
        // 下载并保存二维码
        $qr_content = file_get_contents($qr_url);
        if ($qr_content) {
            file_put_contents($full_path, $qr_content);
            return $local_file;
        }
        
        return $qr_url;
    }
    
    /**
     * 生成支付宝签名
     */
    private function generateAlipaySign($params, $private_key)
    {
        ksort($params);
        $string = '';
        foreach ($params as $key => $value) {
            if ($key != 'sign' && $value != '') {
                $string .= $key . '=' . $value . '&';
            }
        }
        $string = rtrim($string, '&');
        
        $private_key = "-----BEGIN RSA PRIVATE KEY-----\n" .
                      wordwrap($private_key, 64, "\n", true) .
                      "\n-----END RSA PRIVATE KEY-----";
        
        $key = openssl_pkey_get_private($private_key);
        openssl_sign($string, $sign, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);
        
        return base64_encode($sign);
    }
    
    /**
     * 生成微信支付签名
     */
    private function generateWechatSign($params, $key)
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($k != 'sign' && $v != '') {
                $string .= $k . '=' . $v . '&';
            }
        }
        $string .= 'key=' . $key;
        
        return strtoupper(md5($string));
    }
    
    /**
     * 数组转XML
     */
    private function arrayToXml($params)
    {
        $xml = '<xml>';
        foreach ($params as $key => $value) {
            $xml .= '<' . $key . '>' . $value . '</' . $key . '>';
        }
        $xml .= '</xml>';
        return $xml;
    }
    
    /**
     * XML转数组
     */
    private function xmlToArray($xml)
    {
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
    
    /**
     * HTTP请求
     */
    private function httpRequest($url, $data = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("CURL错误: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("HTTP错误: " . $httpCode);
        }
        
        return $response;
    }
    
    /**
     * 带重试机制的HTTP请求
     */
    private function httpRequestWithRetry($url, $data = '', $max_retries = 3)
    {
        $retries = 0;
        
        while ($retries < $max_retries) {
            try {
                $response = $this->httpRequest($url, $data);
                
                if ($response) {
                    return $response;
                }
                
            } catch (\Exception $e) {
                $retries++;
                
                if ($retries >= $max_retries) {
                    throw new \Exception("支付接口调用失败，已重试 {$max_retries} 次：" . $e->getMessage());
                }
                
                // 指数退避
                sleep(pow(2, $retries));
            }
        }
        
        throw new \Exception("支付接口调用失败");
    }
    
    /**
     * 获取支付宝配置
     */
    private function getAlipayConfig()
    {
        return [
            'app_id' => $_ENV['ALIPAY_APP_ID'] ?? config('alipay.app_id'),
            'private_key' => $this->decryptKey($_ENV['ALIPAY_PRIVATE_KEY'] ?? config('alipay.private_key')),
            'public_key' => $_ENV['ALIPAY_PUBLIC_KEY'] ?? config('alipay.public_key'),
            'gateway' => $_ENV['ALIPAY_GATEWAY'] ?? config('alipay.gateway', 'https://openapi.alipay.com/gateway.do')
        ];
    }
    
    /**
     * 获取微信支付配置
     */
    private function getWechatConfig()
    {
        return [
            'app_id' => $_ENV['WECHAT_APP_ID'] ?? config('wechat.app_id'),
            'mch_id' => $_ENV['WECHAT_MCH_ID'] ?? config('wechat.mch_id'),
            'key' => $this->decryptKey($_ENV['WECHAT_KEY'] ?? config('wechat.key')),
            'gateway' => 'https://api.mch.weixin.qq.com/pay/unifiedorder'
        ];
    }
    
    /**
     * 解密密钥（如果有加密的话）
     */
    private function decryptKey($encrypted_key)
    {
        // 如果密钥是加密存储的，在这里解密
        // 这里只是示例，具体实现需要根据实际加密方式调整
        return $encrypted_key;
    }
}
