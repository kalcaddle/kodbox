<?php 
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

// 对外站点联合分享处理;
class explorerShareOut extends Controller{
	private $model;
	function __construct(){
		parent::__construct();
		$GLOBALS['config']['jsonpAllow'] = true;
	}
	
	// 检测是否允许接收外站联合分享; 分享发起端,前端添加编辑用户时检测
	public function shareCheck(){
		$data = $this->shareParamParse();
		if(!$data['_authTo']){show_json(LNG('explorer.shareOut.errorTarget'),false);}
		show_json("ok:check",true);
	}
	// 接收处理: 自动生成协作分享--独立IO;个人中心显示与我协作;
	public function shareMake(){
		$data  = $this->shareParamParse();
		$model = Model('Share');
		
		$siteFrom   = rtrim(get_url_link($data['siteFrom']),'/');
		$sourcePath = 'share@'.intval($data['shareID']).'@'.$siteFrom;
		$shareFind  = $model->where(array('userID'=>0,'sourcePath'=>$sourcePath))->find();
		if(!$data['_authTo']){
			if($shareFind){$model->remove($shareFind['shareID']);}
			if(!$data['shareOut']){show_json('ok',true);}
			show_json(LNG('explorer.shareOut.errorTarget'),false);
		}
		
		// 检测来源站点,并且检测否启用了外部站点分享功能;
		$apiFrom  = $siteFrom.'/index.php?explorer/shareOut/sendCheckAllow';
		$response = url_request($apiFrom,'GET',array(),false,false,true,6);
		if(_get($response,'data.info') != 'kodbox'){// 不是kodbox站点输出则忽略;
			show_json(LNG('explorer.shareOut.errorNetwork').",".$siteFrom,false);
		}else if(!_get($response,'data.code')){
			show_json(_get($response,'data.data'),false);
		}
		
		$saveData = array(
			'isLink'	=> '0','isShareTo' => '1',
			'password'	=> '__SHSRE_OUTER__',
			'sourcePath'=> $sourcePath,
			'authTo' 	=> $data['_authTo'],
			'options'	=> array(
				'site'			=> $siteFrom,
				'shareID'   	=> $data['shareID'],
				'shareHash' 	=> $data['shareHash'],
				'shareUser' 	=> $data['shareUser'],
				'sourceInfo'	=> $data['sourceInfo'],
				'shareTarget'	=> $data['_shareTarget'],
			),
		);
		if($shareFind){
			$model->shareEdit($shareFind['shareID'],$saveData);
			$shareID = $shareFind['shareID'];
		}else{
			$shareID = $model->shareAddSystem(0,$saveData);
			$model->shareEdit($shareID,$saveData);
		}
		// show_json([$model->getInfo($shareID),$model->getInfo($data['shareID']),$saveData,$data]);
		show_json("ok:".($shareFind ? 'edit':'add').';shareID='.$shareID,true);
	}
	
	// 获取分享对象信息; 对象类型/对象id/权限id;
	private function shareParamParse(){
		$data = Input::getArray(array(
			'_check'	=> array("check"=>"require"),
			'siteFrom'	=> array("check"=>"url"),
			'siteTo'	=> array("check"=>"url"),
			'shareID'	=> array("check"=>"number"),
			'shareHash'	=> array("check"=>"require"),
			'shareUser'	=> array("check"=>"require"),
			'sourceInfo'=> array("check"=>"json","default"=>array()),
			'shareOut'	=> array("check"=>"json","default"=>array()),// [{target,auth},...]
		));
		$this->tableInit();
		$config 	= Model('SystemOption')->get();
		$checkKey 	= Mcrypt::decode($data['_check'],'kodShareOut');
		$limitKey   = 'shareOutRecive-'.$data['siteFrom'];
		if($config['shareOutAllowRecive'] != '1'){show_json(LNG('explorer.shareOut.errorDisableReceive'),false);}
		if($data['siteFrom'] != $checkKey){show_json(LNG('explorer.share.errorParam'),false);}
		if(!Cache::limitCall($limitKey,5,10,false)){show_json(LNG('explorer.shareOut.errorCallLimit'),false);}
		
		$authList = $this->authListMake();
		$data['_shareTarget'] = array();$data['_authTo'] = array();
		$dataAdd = array();
		foreach($data['shareOut'] as $item){
			$target 	= $item['target'];
			$targetType = SourceModel::TYPE_USER;$targetID = 0;
			if(substr($target,0,strlen('user:')) === 'user:'){
				$dataInfo = Model('User')->getInfoSimple(substr($target,strlen('user:')));
				if($dataInfo){$targetID = $dataInfo['userID'];}
			}else if(substr($target,0,strlen('group:')) === 'group:'){
				$dataInfo = Model('Group')->getInfoSimple(substr($target,strlen('group:')));
				if($dataInfo){$targetID = $dataInfo['groupID'];$targetType = SourceModel::TYPE_GROUP;}
			}else{
				$dataInfo = Model('User')->userLoginFind($target);
				if($dataInfo){$targetID = $dataInfo['userID'];}
			}
			$authID = $authList[$item['auth']];
			$dataAddKey = $targetType.'-'.$targetID;// 去重处理;
			if(!$targetID || !$authID || $dataAdd[$dataAddKey]){continue;}
			
			$dataAdd[$dataAddKey] = true;
			$_item = array_field_key($item,array('target','secret','auth'));
			$data['_shareTarget'][] = array_merge($_item,array('authID'=>$authID,'to'=>$target.'@'.$data['siteTo']));
			$data['_authTo'][] = array('targetType'=>$targetType,'targetID'=>$targetID,"authID"=>$authID);
		}
		return $data;
	}
	
	// 权限映射到当前站点文档权限;
	private function authListMake(){
		return array(
			'read' => Model('Auth')->findAuthMax(
				AuthModel::AUTH_SHOW | AuthModel::AUTH_VIEW | AuthModel::AUTH_DOWNLOAD,
				AuthModel::AUTH_UPLOAD | AuthModel::AUTH_EDIT | AuthModel::AUTH_REMOVE
			),
			'write' => Model('Auth')->findAuthMax(
				AuthModel::AUTH_SHOW | AuthModel::AUTH_VIEW | AuthModel::AUTH_DOWNLOAD | AuthModel::AUTH_UPLOAD | AuthModel::AUTH_EDIT | AuthModel::AUTH_REMOVE,
				AuthModel::AUTH_ROOT
			),
		);
	}
	
	// apiKey检测(管理员后台,添加授信站点时前端发起请求)
	public function shareCheckApiKey(){
		$config 	= Model('SystemOption')->get();
		$checkKey 	= Mcrypt::decode($this->in['apiKey'],'kodShareOut');
		if($config['shareOutAllowRecive'] != '1'){show_json(LNG('explorer.shareOut.errorDisableReceive'),false);}
		if($config['shareOutSiteApiKey']  != $checkKey){show_json(LNG('explorer.shareOut.errorApiKey'),false);}
		show_json("ok:check",true);
	}
	// 授信目标站点组织架构获取;分享时前端发起获取;
	public function shareSafeGroup(){
		$config 	= Model('SystemOption')->get();		
		$checkKey 	= Mcrypt::decode($this->in['sk'],$config['shareOutSiteApiKey']);
		if($config['shareOutAllowRecive'] != '1'){show_json(LNG('explorer.shareOut.errorDisableReceive'),false);}
		if($checkKey != 'kodShareOutGroup'){show_json(LNG('explorer.shareOut.errorApiKey').'(timeout)',false);}
		
		$GLOBALS['isRoot'] = true;
		$methodMap = array(
			'groupList'		=> array('action'=>'admin.group.get','param'=>'parentID'),
			'groupSearch'	=> array('action'=>'admin.group.search','param'=>'words'),
			'memberList'	=> array('action'=>'admin.member.get','param'=>'groupID'),
			'memberSearch'	=> array('action'=>'admin.member.search','param'=>'words'),
		);
		$methodInfo = $methodMap[$this->in['method']];
		if(!$methodInfo){show_json('method error!',false);}
		ActionCallResult($methodInfo['action'],function(&$res) use($self){
			if(!$res['code']){return;}
			$index = $GLOBALS['in']['siteIndex'];
			$fieldGroup = array('groupID','name','groupPath','hasChildren','hasChildrenMember');
			$fieldUser  = array('userID','name','nickName','avatar');
			foreach($res['data']['list'] as $i=>$item){
				if($item['groupID']){$item = array_field_key($item,$fieldGroup);}
				if($item['userID']){$item = array_field_key($item,$fieldUser);}
				$item['siteIndex'] = $GLOBALS['in']['siteIndex'];
				$res['data']['list'][$i] = $item;
			}
		});
	}
	
	
	//==================== 作为发送方提供的接口 ===================
	
	// 外部用户访问权限校验; return: false/read/write; (接收端内部IO向发起端请求, 发起端校验处理);
	// 校验通过返回对应权限; 不通过则默认使用外链分享各个限制;
	public function sendCheckAuth($shareInfo){
		if(!$this->in['sk']){return false;}
		if(Model('SystemOption')->get('shareOutAllowSend') != '1'){return false;}
		$decode 	= Mcrypt::decode($this->in['sk'],'kodShareOut').'';
		$dataArr 	= explode('$@$',$decode);
		$authTo 	= array_find_by_field($shareInfo['options']['shareOut'],'to',$dataArr[1]);
		if(!is_array($authTo) || $authTo['secret'] != $dataArr[0]){return false;}
		$GLOBALS['fileEncodeDisable'] = '1';// 屏蔽外链分享,文件内容加密(文档水印及安全--启用文件加密启用时)
		return $authTo['auth'];
	}
	// 接收时向发起站点做访问校验处理; 接收端接收到推送时向发起端后端请求(shareMake中);
	public function sendCheckAllow(){
		$config = Model('SystemOption')->get();
		if($config['shareLinkAllow'] != '1'){show_json(LNG('explorer.shareOut.errorDisableShare'),false,'kodbox');}
		if($config['shareOutAllowSend'] != '1'){show_json(LNG('explorer.shareOut.errorDisableSend'),false,'kodbox');}
		show_json("ok:check",true,'kodbox');
	}
	
	// 当前站点分享信息获取时,授信站点列表获取(apiKey加密); 供前端显示用(前端获取用户部门信息)
	public function sendShareSiteAppend($shareInfo){
		$config = Model('SystemOption')->get();
		$this->tableInit();
		if(!$shareInfo || !is_array($shareInfo)){return $shareInfo;}
		if($config['shareLinkAllow'] != '1' || $config['shareOutAllowSend'] != '1'){return $shareInfo;}
		$result   = array();$listAdd = array();
		$siteList = json_decode(_get($config,'shareOutSiteSafe',''),true);
		if(!is_array($siteList)){$siteList = array();}
		foreach($siteList as $site){
			if(!$site || $site['isOpen'] != '1' || !$site['apiKey']){continue;}
			if($listAdd[$site['url']]){continue;} // 去重;
			$result[] = array(
				'url' 	=> $site['url'],
				'name'	=> $site['name'],
				'sk' 	=> Mcrypt::encode('kodShareOutGroup',$site['apiKey'],600),//有效期10分钟;
			);
			$listAdd[$site['url']] = true;
		}
		$shareInfo['shareOutSite'] = $result;
		return $shareInfo;
	}
	
	// 表结构初始化; varchar(1000)=>text (65536字符);
	private function tableInit(){
		if(Model('SystemOption')->get('shareOutInit')){return;}
		Model('SystemOption')->set('shareOutInit','1');
		$sql = "ALTER TABLE `share` CHANGE `options` `options` text COLLATE 'utf8_general_ci' NULL COMMENT 'json 配置信息' AFTER `numDownload`;";
		if(stristr($this->config['database']['DB_TYPE'],'sqlite')){$sql = '';}
		if($sql){Model()->db()->execute($sql);}
	}
}