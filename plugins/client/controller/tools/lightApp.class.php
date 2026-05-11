<?php 
/**
 * 第三方轻应用过滤
 */
class clientToolsLightApp extends Controller {
    public $pluginName;
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'clientPlugin';
    }

    public function index() {
        // $config['settingSystemDefault']['lightAppLimit'] = 0;    // 指定不过滤
        if (_get($GLOBALS['config'],'settingSystemDefault.lightAppLimit') == '0') return;

        Hook::bind('explorer.lightapp.get.after', array($this, 'lightAppGet'));
        Hook::bind('user.view.options.after', array($this, 'userOptions'));
    }

    /**
     * 轻应用列表获取
     * @param [type] $result
     * @return void
     */
    public function lightAppGet($result) {
        if (!$result || !$result['code']) return $result;
        if (empty($result['data'])) return $result;
        $versionType = Model('SystemOption')->get('versionType');
        if ($versionType == 'A') return $result;

        $model   = Model('SystemLightApp');
        $appList = $this->getDefApps();
        foreach ($result['data'] as $i => $app) {
            $name = $app['name'];
            if (in_array($name, $appList)) {
                unset($result['data'][$i]);
                $model->remove($name);
            }
        }

        return $result;
    }

    // 获取默认（全部）app列表
    private function getDefApps() {
        $cckey = 'kod.lightApp.name.list';
        $cache = Cache::get($cckey);
        if ($cache) return $cache;
        $str  = file_get_contents(BASIC_PATH.'data/system/apps.php');
		$data = json_decode(substr($str, strlen('<?php exit;?>')),true);
		$data = is_array($data) ? array_keys($data) : array();
        if (!$data) {
            $data = array(
                '哔哩哔哩','飞书文档','ProcessOn','desmos','zhihu','douban','weibo','icloud','时钟','快递查询','js在线压缩','高德地图','Fruits Shooter',
                '小游戏集合','有道词典','迅捷文档转换','OfficeConverter','百度脑图','QQ音乐','网易云音乐','创可贴','trello','即时工具','石墨文档'
            );
        }
        $oldApps = array(
            '时钟','365日历','快递查询','黑8对决','百度随心听','计算器','天气','js在线压缩','中国象棋','音悦台','高德地图','有道词典','豆瓣电台','iqiyi影视',
            'Web PhotoShop','icloud','迅捷文档转换','Vector Magic','Kingdom Rush','腾讯canvas','OfficeConverter','pptv直播','搜狐影视','百度DOC',
            '百度脑图','网易云音乐','创可贴','trello','一起写office','ProcessOn','石墨文档','微信'
        );
        $data = array_unique(array_merge($data,$oldApps));
        Cache::set($cckey, $data);
        return $data;
    }

    // 更新新用户默认app
    public function userOptions($options) {
        $newUserApp = _get($options, 'system.options.newUserApp', '');
        if ($newUserApp) {
            $defaultApp = array('高德地图','trello','icloud');
            $newUserApp = array_diff(explode(',',$newUserApp),$defaultApp);
            $newUserAppNow = implode(',',$newUserApp);
            if ($newUserApp != $newUserAppNow) {
                Model('SystemOption')->set('newUserApp',$newUserAppNow);
                $options['system']['options']['newUserApp'] = $newUserAppNow;
            }
        }
		return $options;
    }

}