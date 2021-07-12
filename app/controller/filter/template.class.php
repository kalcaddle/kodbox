<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

/**
 * 通用数据过滤;
 */
class filterTemplate extends Controller{
	function __construct(){
		parent::__construct();
	}
	
	// 自动绑定处理;
	public function bind(){
		Hook::bind('user.index.index',array($this,'lessCompile'));
	}
	
	
	/**
	less 自动编译处理;  缓存处理; 5s编译;
	*/
	public function lessCompile(){
		if(!STATIC_DEV) return;
		if(!$this->lessChange()) return;
		Action("test.debug")->less(false);
	}
	private function lessChange(){
		$path = BASIC_PATH.'static/style/skin_dev';
		$list = IO::listAll($path);
		$str  = '';
		foreach ($list as $item) {
			$str .= $item['path'].';'.$item['modifyTime'].';'.$item['size'];
		}
		$hashNew	= md5($str);
		$hashBefore = Cache::get('debug.lessCompile.key');
		if($hashNew == $hashBefore) return false;
		Cache::set('debug.lessCompile.key',$hashNew);
		return true;
	}
}