<?php
use think\Db;

/**
 * 将字符解析成数组
 * @param string $str 查询字符串
 * @return array
 */
function parseParams($str)
{
    $arrParams = [];
    parse_str(html_entity_decode(urldecode($str)), $arrParams);
    return $arrParams;
}

/**
 * 子孙树，用于菜单整理
 * @param array $param 菜单数据
 * @param int   $pid   父级ID
 * @return array
 */
function subTree($param, $pid = 0)
{
    static $res = [];
    foreach ($param as $key => $vo) {
        if ($pid == $vo['pid']) {
            $res[] = $vo;
            subTree($param, $vo['id']);
        }
    }
    return $res;
}

/**
 * 整理菜单树方法
 * @param array $param 菜单数据
 * @return array
 */
function prepareMenu($param)
{
    $parent = [];
    $child  = [];
    foreach ($param as $key => $vo) {
        if ($vo['pid'] == 0) {
            $vo['href'] = '#';
            $parent[] = $vo;
        } else {
            $vo['href'] = url($vo['name']);
            $child[] = $vo;
        }
    }
    foreach ($parent as $key => $vo) {
        foreach ($child as $k => $v) {
            if ($v['pid'] == $vo['id']) {
                $parent[$key]['child'][] = $v;
            }
        }
    }
    unset($child);
    return $parent;
}

/**
 * 格式化字节大小
 * @param int    $size      字节数
 * @param string $delimiter 数字和单位分隔符
 * @return string
 */
function format_bytes($size, $delimiter = '')
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    for ($i = 0; $size >= 1024 && $i < 5; $i++) {
        $size /= 1024;
    }
    return $size . $delimiter . $units[$i];
}
