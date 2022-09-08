<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/
class explorerListRecent extends Controller{
	public function __construct(){
		$this->model = Model("Source");
		parent::__construct();
	}

	/**
	 * 最近文档；
	 * 仅限自己的文档；不分页；不支持排序；  最新修改时间 or 最新修改 or 最新打开 max top 100;
	 * 
	 * 最新自己创建的文件(上传or拷贝)
	 * 最新修改的，自己创建的文件	
	 * 最新打开的自己的文件 		
	 * 
	 * 资源去重；整体按时间排序【创建or上传  修改  打开】
	 */
	public function listData(){
		$list = array();
		$this->listRecentWith('createTime',$list);		//最近上传or创建
		$this->listRecentWith('modifyTime',$list);		//最后修改
		$this->listRecentWith('viewTime',$list);		//最后打开
		
		//合并重复出现的类型；
		foreach ($list as &$value) {
			$value['recentType'] = 'createTime';
			$value['recentTime'] = $value['createTime'];
			if($value['modifyTime'] > $value['recentTime']){
				$value['recentType'] = 'modifyTime';
				$value['recentTime'] = $value['modifyTime'];
			}
			if($value['viewTime'] > $value['recentTime']){
				$value['recentType'] = 'viewTime';
				$value['recentTime'] = $value['viewTime'];
			}
			$value['recentTime'] = intval($value['recentTime']);
		};unset($value);
		
		$list = array_sort_by($list,'recentTime',true);
		$listRecent = array_to_keyvalue($list,'sourceID');
		$result = array();
		if(!empty($listRecent)){
			$where  = array( 'sourceID'=>array('in',array_keys($listRecent)) );
			$result = $this->model->listSource($where);
		}
		$fileList = array_to_keyvalue($result['fileList'],'sourceID');
		
		//保持排序，合并数据
		foreach ($listRecent as $sourceID => &$value) {
			$item  = $fileList[$sourceID];
			if(!$item){
				unset($listRecent[$sourceID]);
				continue;
			}
			$value = array_merge($value,$item);
		}
		$result['fileList'] = array_values($listRecent);
		
		$todayStart = strtotime(date('Y-m-d 00:00:00'));
		$dayTime    = 24 * 3600;
		$result['groupShow'] = array(
			array(
				'type' 	=> 'time-today',
				'title' => LNG("common.date.today"),
				"desc"  => date("Y-m-d",time()),
				"filter"=> array('recentTime'=>array('>'=> $todayStart )),
			),
			array(
				'type'	=> 'time-yesterday',
				'title'	=> LNG("common.date.yestoday"),
				"desc"  => date("Y-m-d",$todayStart - $dayTime),
				"filter"=> array('recentTime'=>array('<'=> $todayStart,'>'=> $todayStart - $dayTime )),
			),
			array(
				'type'	=> 'time-before',
				'title'	=> LNG('common.date.before'),
				"filter"=> array('recentTime'=>array('<'=> $todayStart - $dayTime )),
			),
		);
		
		return $result;
	}
	private function listRecentWith($timeType,&$result){
		$userID = USER_ID;
		$where  = array(
			'targetType'	=> SourceModel::TYPE_USER,
			'targetID'		=> $userID,
			'isFolder'		=> 0,
			'isDelete'		=> 0,
			'size'			=> array('>',0),
		);
		// 内部协作分享,修改上传内容; 普通用户可能没有权限进入处理;
		if($GLOBALS['isRoot']){
			if(in_array($timeType,array('createTime','modifyTime')) ){
				unset($where['targetID']);
				$where['targetType'] = array('in',array(SourceModel::TYPE_USER,SourceModel::TYPE_GROUP));
			}
			if($timeType == 'createTime'){$where['createUser'] = $userID;}
			if($timeType == 'modifyTime'){$where['modifyUser'] = $userID;}
		}
		$where[$timeType] = array('>',time() - 3600*24*60);//2个月内

		$maxNum = 50;	//最多150项
		$field  = 'sourceID,name,createTime,modifyTime,viewTime';
		$list   = $this->model->field($field)->where($where)
					->limit($maxNum)->order($timeType.' desc')->select();
		$list   = array_to_keyvalue($list,'sourceID');
		$result = array_merge($result,$list);
		$result = array_to_keyvalue($result,'sourceID');
	}
}