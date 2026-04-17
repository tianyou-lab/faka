<?php

namespace com;
/**
 *  IP 地理位置查询类 修改自 CoolCode.CN
 *  由于使用UTF8编码 如果使用纯真IP地址库的话 需要对返回结果进行编码转换
 */
class IpLocation {
    /**
     * 根据所给 IP 地址或域名返回所在地区信息
     *
     * @access public
     * @param string $ip
     * @return array
     */
    public function getlocation($ip='') {
		if(empty($ip)) $ip = get_client_ip();
        $location['ip'] = gethostbyname($ip);   // 将输入的域名转化为IP地址
        $json = iconv('GB2312','UTF-8//IGNORE',http_request("http://whois.pconline.com.cn/ipJson.jsp?ip=".$location['ip']."&json=true"));
		$ipjson = json_decode($json,true);
		$location['country']    = $ipjson['addr'];
        if ($location['country'] == "") {
	      $location['country'] = "unknown";
	    }
		$location['area']       = '';
        return $location;
    }


}