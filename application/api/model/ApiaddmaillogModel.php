<?php
namespace app\api\model;
use think\Model;
use think\Db;

class ApiaddmaillogModel extends Model
{
		protected $name = 'addmaillog';  
    protected $autoWriteTimestamp = true;   // 开启自动写入时间戳

  	public function getAllCount()
    {
        return $this->count();
    }
    /**
     * 获取订单号详细信息
     */
    public function getOrderByOrder($order)
    {
    	if(SuperIsEmpty($order)){
        return TyReturn('订单号输入不正确001',-1); 
    	}
    	
    	$map = [];
    	$msg='';
    	$code=1;
    	
    	
        if($order&&$order!=="")
        {
        	$map['mcard|morder'] = $order;	
        }else{
        	return TyReturn('订单号输入不正确',-1); 
        }	
		$card=db('info')->where($map)->find();	
        if (empty($card)) {
        	$code=-1;
            $msg = '卡号不存在!（转账之后稍等30秒再提取）' . '<br>' . '紧急联系电话：' . config('WEB_MOBILE') . '<br>' . '联系QQ：' . config('WEB_QQ');
        }
        return TyReturn($msg,$code,$card);         
    }
    
    
    /**
     * 编辑订单信息
     */
    public function editOrder($param)
    {
        try{
            $result=db("info")->strict(false)->where('id',$param['id'])->update($param);
            if(false === $result){
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{
                return ['code' => 1, 'data' => '', 'msg' => '编辑成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
    
    
}