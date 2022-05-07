<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 本地文件,io文件历史记录
 * 
 * 
 * 历史版本生成
 * 操作触发检测: 文件编辑保存;上传覆盖;粘贴覆盖;(排除历史版本回退)  IO::setContent
 * 目录变更更新: 重命名,上层文件夹重命名;移动,上层文件夹移动;
 * 删除处理: 文件删除,上层文件夹删除;
 * 历史版本管理: 列表,打开,下载;回退,删除,清空; diff对比
 */
class explorerHistoryLocal extends Controller{
	function __construct(){
		parent::__construct();
		$this->checkAuth();
	}
	
	public function checkAuth(){
		$path = Input::get('path','require');
		Action('explorer.auth')->canWrite($path);
		$this->path = $this->parsePath($path);
		if(!$this->path){
			show_json(LNG('explorer.dataError'),false);
		}
	}
	public function delegate(){
		$action = ACT;
		$this->$action();
	}
	
	// 目录检测;
	private function parsePath($path){
		if(!$path) return false;
		$pathParse  = KodIO::parse($path);
		$driverType = $pathParse['type'];
		$allow = !$driverType || $driverType == KodIO::KOD_IO || $driverType == KodIO::KOD_SHARE_ITEM;
		if(!$allow || !$pathParse['isTruePath']) return false;
		
		if($driverType == KodIO::KOD_SHARE_ITEM){
			$driver = IO::init($path);
			if($driver->pathParse['truePath']){
				return $this->parsePath($driver->pathParse['truePath']);
			}
			return false;
		}
		return $path;
	}
	
	public function get(){
		$history = IOHistory::listData($this->path);
		if(!$history){show_json(LNG('explorer.dataError'),false);}
		$result   = array_page_split($history['list']);

		if(Model('SystemOption')->get('versionType') == 'A'){
			$result['list'] = array_slice($history['list'],0,5);
			$result['pageInfo']['totalNum']  = count($result['list']);
			$result['pageInfo']['pageTotal'] = 1;
			$result['pageInfo']['page'] = 1;
		}
		$userList = array();
		foreach ($result['list'] as $key=>$item){
			$userKey = 'user-'.$item['createUser'];
			if(!$userList[$userKey]){
				$userList[$userKey] = Model("User")->getInfoSimpleOuter($item['createUser']);
			}
			$result['list'][$key]['createUser'] = $userList[$userKey];
		}
		$result['current'] = IO::info($this->in['path']);
		show_json($result);
	}
	public function remove(){
		$res = IOHistory::remove($this->path,$this->in['id']);
		$msg = $res ? LNG('explorer.success') : IO::getLastError(LNG('explorer.error'));
		show_json($msg,!!$res);
	}
	public function clear(){
		$res = IOHistory::clear($this->path);
		$msg = $res ? LNG('explorer.success') : IO::getLastError(LNG('explorer.error'));
		show_json($msg,!!$res);
	}
	// 回滚到最新; 将某个版本设置为当前版本;
	public function rollback() {
		$res = IOHistory::rollback($this->path,$this->in['id']);
		$msg = $res ? LNG('explorer.success') : IO::getLastError(LNG('explorer.error'));
		show_json($msg,!!$res);
	}
	public function setDetail(){
		$maxLength = $GLOBALS['config']['systemOption']['historyDescLengthMax'];
		$msg  = LNG('common.lengthLimit').'('.LNG('explorer.noMoreThan').$maxLength.')';
		$data = Input::getArray(array(
			'detail'=> array('check'=>'length','param'=>array(0,$maxLength),'msg'=>$msg),
		));
		$res = IOHistory::setDetail($this->path,$this->in['id'],$data['detail']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.dataError');
		show_json($msg,!!$res);
	}
	public function fileOut(){
		IOHistory::fileOut($this->path,$this->in['id']);
	}
	public function fileInfo(){
		return IOHistory::fileInfo($this->path,$this->in['id']);
	}
}
