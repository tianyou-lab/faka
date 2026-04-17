<?php
namespace app\jingdian\controller;
use app\jingdian\model\IndexModel;
use app\jingdian\model\GoodsListModel;
use think\Config;
use think\Loader;
use think\Db;
use com\IpLocationqq;

class Downimg extends Base
{


 public function index(){

			$order=input('param.order');
			$contents="";
			$uf='upload/'.$order.'.txt';
			if(file_exists($uf)){
			  $contents=file_get_contents($uf);
			}else{
				$contents= '文件已删除';
			}
			$checkBom = checkBOM($uf);
			if ($checkBom) 
			{
				$contents=substr($contents,3);
			}
			$tou=getSubstr($contents,"<pretou>","</pretou>");
			$wei=getSubstr($contents,"<prewei>","</prewei>");
			$zhengwen=str_replace($tou,'',$contents);
			$zhengwen=str_replace($wei,'',$zhengwen);
			$zhengwen=str_replace("<pretou>",'',$zhengwen);
			$zhengwen=str_replace("</pretou>",'',$zhengwen);
			$zhengwen=str_replace("<prewei>",'',$zhengwen);
			$zhengwen=str_replace("</prewei>",'',$zhengwen);
			$zhengwen=htmlspecialchars($zhengwen,ENT_QUOTES,"UTF-8");
			if((preg_match('/[0-9]{8}+\//',$zhengwen) !='0'&&preg_match('/\.jpg|\.png|\.gif$/is', $zhengwen)!='0')&&strpos($zhengwen,'http')===false){
					$zhengwen = explode("||||||",str_replace(array("\r\n", "\r", "\n"),'||||||',$zhengwen));
					$zhanshitext = array_filter($zhengwen);
				  
			}
			$this->picDownload($zhanshitext);

 }












	//批量下载图片
 function picDownload($images){	 
			$order=input('param.order');
				//$images的格式为$images=array('xxxx.jpg','yyyy.jpg');可以根据需要自己修改
 
            $filename = UPLOAD_PATH."/upload/" . $order . ".zip";

			if(!file_exists($filename)){		
            // 生成文件
            $zip = new \ZipArchive ();
            // 使用本类，linux需开启zlib，windows需取消php_zip.dll前的注释
            if ($zip->open ($filename ,\ZipArchive::OVERWRITE) !== true) {
                //OVERWRITE 参数会覆写压缩包的文件 文件必须已经存在
                if($zip->open ($filename ,\ZipArchive::CREATE) !== true){
                    // 文件不存在则生成一个新的文件 用CREATE打开文件会追加内容至zip
                    exit ( '无法打开文件，或者文件创建失败' );
                }
            }
            foreach($images as $key => $v){
					//iconv('utf-8','gb2312',$v),因为我的$v中含有中文，file_exists不识别中文，需要转码
                $urlfile=UPLOAD_PATH."/uploads/images/".$v;	
                if(file_exists($urlfile)){
					//get_basename($v)，原来的basename()不识别中文，新建函数获取文件名
					//iconv('utf-8','gb2312',get_basename($v))还是中文问题，没有中文的话basename($v)即可
                    $zip->addFile($urlfile, iconv('utf-8','gb2312',$this->get_basename($v)));
                }
            }
        // 关闭
        $zip->close ();
		}
		
        //下面是输出下载;
		
        header ( "Cache-Control: max-age=0" );
        header ( "Content-Description: File Transfer" );
        header ( 'Content-disposition: attachment; filename=' . basename ( $filename ) ); // 文件名
        header ( "Content-Type: application/zip" ); // zip格式的
        header ( "Content-Transfer-Encoding: binary" ); // 告诉浏览器，这是二进制文件
        header ( 'Content-Length: ' . filesize ( $filename ) ); // 告诉浏览器，文件大小
        @readfile ( $filename );//输出文件;
        @unlink($filename);
            exit;
      }
	  
	  
	   function get_basename($filename){
		 return preg_replace('/^.+[\\\\\\/]/', '', $filename);
		}
		
		
}		