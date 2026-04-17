<?php

namespace app\api\controller;
use app\api\model\OrderApiModel;
use think\Controller;
use think\Db;

class Payment extends Controller
{
    /**
     * 支付宝支付回调（增强版本）
     */
    public function alipay_notify()
    {
        try {
            $params = $_POST;
            
            // 记录原始回调数据
            $this->logPayment('', 'alipay', $params, 'received', '收到回调');
            
            // 防重放攻击检查
            $notify_id = $params['notify_id'] ?? '';
            if (!empty($notify_id)) {
                $replay_key = 'alipay_notify_' . md5($notify_id);
                if (cache($replay_key)) {
                    echo 'fail'; // 重复通知
                    return;
                }
                // 标记通知已处理（缓存2小时）
                cache($replay_key, true, 7200);
            }
            
            // 验证必要参数
            $required_params = ['out_trade_no', 'trade_no', 'trade_status', 'total_amount'];
            foreach ($required_params as $param) {
                if (empty($params[$param])) {
                    $this->logPayment('', 'alipay', $params, 'fail', "缺少必要参数：{$param}");
                    echo 'fail';
                    return;
                }
            }
            
            // 增强签名验证
            if (!$this->verifyAlipaySignEnhanced($params)) {
                $this->logPayment('', 'alipay', $params, 'fail', '签名验证失败');
                echo 'fail';
                return;
            }
            
            // 验证订单状态
            if (!in_array($params['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
                $this->logPayment($params['out_trade_no'], 'alipay', $params, 'fail', '交易状态不正确');
                echo 'fail';
                return;
            }
            
            $order_no = $params['out_trade_no'];
            $trade_no = $params['trade_no'];
            
            // 验证订单金额
            if (!$this->verifyOrderAmount($order_no, $params['total_amount'])) {
                $this->logPayment($order_no, 'alipay', $params, 'fail', '订单金额不匹配');
                echo 'fail';
                return;
            }
            
            // 处理支付回调
            $orderModel = new OrderApiModel();
            $result = $orderModel->paymentCallback($order_no, $trade_no);
            
            if ($result['code'] == 1) {
                $this->logPayment($order_no, 'alipay', $params, 'success', '处理成功');
                echo 'success';
            } else {
                $this->logPayment($order_no, 'alipay', $params, 'fail', $result['msg']);
                echo 'fail';
            }
            
        } catch (\Exception $e) {
            $this->logPayment($order_no ?? '', 'alipay', $_POST, 'error', $e->getMessage());
            echo 'fail';
        }
    }
    
    /**
     * 微信支付回调
     */
    public function wechat_notify()
    {
        try {
            $xml = file_get_contents('php://input');
            $params = $this->xmlToArray($xml);
            
            // 验证签名
            if (!$this->verifyWechatSign($params)) {
                echo $this->arrayToXml(['return_code' => 'FAIL', 'return_msg' => '签名验证失败']);
                return;
            }
            
            // 验证支付状态
            if ($params['return_code'] != 'SUCCESS' || $params['result_code'] != 'SUCCESS') {
                echo $this->arrayToXml(['return_code' => 'FAIL', 'return_msg' => '支付失败']);
                return;
            }
            
            $order_no = $params['out_trade_no'];
            $trade_no = $params['transaction_id'];
            
            // 处理支付回调
            $orderModel = new OrderApiModel();
            $result = $orderModel->paymentCallback($order_no, $trade_no);
            
            if ($result['code'] == 1) {
                // 记录支付日志
                $this->logPayment($order_no, 'wechat', $params, 'success');
                echo $this->arrayToXml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
            } else {
                // 记录失败日志
                $this->logPayment($order_no, 'wechat', $params, 'fail', $result['msg']);
                echo $this->arrayToXml(['return_code' => 'FAIL', 'return_msg' => $result['msg']]);
            }
            
        } catch (\Exception $e) {
            $this->logPayment($order_no ?? '', 'wechat', $xml ?? '', 'error', $e->getMessage());
            echo $this->arrayToXml(['return_code' => 'FAIL', 'return_msg' => '处理异常']);
        }
    }
    
    /**
     * 验证支付宝签名
     */
    private function verifyAlipaySign($params)
    {
        $public_key = $_ENV['ALIPAY_PUBLIC_KEY'] ?? config('alipay.public_key');
        if (empty($public_key)) {
            return false;
        }
        
        $sign = $params['sign'];
        unset($params['sign'], $params['sign_type']);
        
        ksort($params);
        $string = '';
        foreach ($params as $key => $value) {
            if ($value != '') {
                $string .= $key . '=' . $value . '&';
            }
        }
        $string = rtrim($string, '&');
        
        $public_key = "-----BEGIN PUBLIC KEY-----\n" .
                     wordwrap($public_key, 64, "\n", true) .
                     "\n-----END PUBLIC KEY-----";
        
        $key = openssl_pkey_get_public($public_key);
        $verify = openssl_verify($string, base64_decode($sign), $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);
        
        return $verify === 1;
    }
    
    /**
     * 验证微信签名
     */
    private function verifyWechatSign($params)
    {
        $key = $_ENV['WECHAT_KEY'] ?? config('wechat.key');
        if (empty($key)) {
            return false;
        }
        
        $sign = $params['sign'];
        unset($params['sign']);
        
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($v != '') {
                $string .= $k . '=' . $v . '&';
            }
        }
        $string .= 'key=' . $key;
        
        return strtoupper(md5($string)) === $sign;
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
     * 增强的支付宝签名验证
     */
    private function verifyAlipaySignEnhanced($params)
    {
        $public_key = $_ENV['ALIPAY_PUBLIC_KEY'] ?? config('alipay.public_key');
        if (empty($public_key)) {
            return false;
        }
        
        $sign = $params['sign'];
        $sign_type = $params['sign_type'] ?? 'RSA2';
        
        // 只支持RSA2签名
        if ($sign_type !== 'RSA2') {
            return false;
        }
        
        unset($params['sign'], $params['sign_type']);
        
        // 构建待签名字符串
        ksort($params);
        $string = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $string .= $key . '=' . $value . '&';
            }
        }
        $string = rtrim($string, '&');
        
        // 格式化公钥
        $public_key = "-----BEGIN PUBLIC KEY-----\n" .
                     wordwrap($public_key, 64, "\n", true) .
                     "\n-----END PUBLIC KEY-----";
        
        // 验证签名
        $key = openssl_pkey_get_public($public_key);
        if (!$key) {
            return false;
        }
        
        $verify = openssl_verify($string, base64_decode($sign), $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);
        
        return $verify === 1;
    }
    
    /**
     * 验证订单金额
     */
    private function verifyOrderAmount($order_no, $callback_amount)
    {
        $order = Db::name('info')->where('morder', $order_no)->find();
        if (!$order) {
            return false;
        }
        
        // 金额比较（处理浮点数精度问题）
        return abs(floatval($order['mamount']) - floatval($callback_amount)) < 0.01;
    }
    
    /**
     * 记录支付日志
     */
    private function logPayment($order_no, $pay_type, $data, $status, $error_msg = '')
    {
        try {
            Db::name('payment_log')->insert([
                'order_no' => $order_no,
                'pay_type' => $pay_type,
                'notify_data' => is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data,
                'status' => $status,
                'error_msg' => $error_msg,
                'ip' => getIP(),
                'create_time' => time()
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
        }
    }
}
