<?php


function download($order)
{
	alert($order);
	$filename = 'upload/'.$order.'.txt'; //文件路径
header("Content-Type: application/force-download");
header("Content-Disposition: attachment; filename=".basename($filename));
readfile($filename);
	
}




