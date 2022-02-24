<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

// 文件发布: 首先复制到系统临时目录,并开放该目录给指定用户可见; 发布:从临时目录复制到发布目录;

class explorerPublish extends Controller{
	function __construct(){
		parent::__construct();
	}
	
	// 构造发布临时目录并生成访问路径;
	public function makeTemp($fromPath,$publishPath,$userAuth){
		$fromPathInfo = IO::info($fromPath);
		$destPathInfo = IO::info($publishPath);
		if(!$fromPathInfo || !$destPathInfo || $destPathInfo['type'] != 'folder' ) return;
		
		$systemPublish = KodIO::systemPath('systemPublish');
		$tempPath = IO::copy($fromPath,$systemPublish);
		$tempPathInfo = IO::info($tempPath);
		if(!$tempPathInfo) return;
		
		$publishData = array(
			'fromPath' 		=> $fromPath,
			'publishPath'	=> $publishPath,
			'time'			=> time(),
		);
		$publishData = json_encode($publishData);
		Model('Source')->metaSet($tempPathInfo['sourceID'],'publishData',$publishData);
		
		$viewPath = $this->makeShare($tempPath,$userAuth);		
		return array('tempPath'=>$tempPath,'viewPath'=>$viewPath);
	}
	
	// 取消临时文件(夹)
	public function cancle($tempPath){
		$pathInfo = IO::info($tempPath);
		if(!$tempPath || !$pathInfo || !$pathInfo['sourceID']) return;
		$this->removeShare($tempPath);
		IO::remove($tempPath);
	}
	
	// 发布
	public function finished($tempPath){
		$pathInfo = IO::info($tempPath);
		$publishData = _get($pathInfo,'metaInfo.publishData');
		if(!$tempPath || !$publishData) return;
		$publishData = json_decode($publishData,true);
		if(!$publishData || !IO::info($publishData['publishPath'])) return false;
		
		IO::copy($tempPath,$publishData['publishPath']);
		$this->cancle($tempPath);
	}
	
	
	/**
	 * 构建临时访问路径;
	 * authTo: [{"targetType":"1","targetID":"23","authID":"1"},...]
	 */
	private function makeShare($tempPath,$userAuth){
		$pathInfo = IO::info($tempPath);
		$authTo   = array();
		foreach ($userAuth as $userID=>$authID){
			if(!$userID || !$authID) continue;
			$authTo[] = array("targetType"=>SourceModel::TYPE_USER, 'targetID'=>$userID,"authID"=>$authID);
		}
		if(!$authTo || !$pathInfo) return false;
		
		$data = array('isShareTo' => 1,'authTo'=> $authTo);
		$shareID = Model('Share')->shareAdd($pathInfo['sourceID'],$data);
		return KodIO::makePath(KodIO::KOD_SHARE_ITEM,$shareID,$pathInfo['sourceID']);
	}
	private function removeShare($tempPath){
		$pathInfo = IO::info($tempPath);
		$shareID  = $pathInfo['sourceInfo']['shareInfo']['shareID'];
		if(!$shareID) return;
		Model('Share')->remove(array($shareID));
	}
}
