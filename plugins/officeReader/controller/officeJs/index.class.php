<?php 
class officeReaderOfficeJsIndex extends Controller {
    protected $pluginName;
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeReaderPlugin';
    }

    public function index(){
        $path = Action($this->pluginName)->filePath($this->in['path']);
		$info = IO::info($path);
		$fileExt = $info['ext'];
		if(!in_array($fileExt, array('docx', 'xls', 'xlsx'))) return;

        $app = $fileExt == 'docx' ? 'mammothjs' : 'sheetjs';
        $link = $this->fileLink($app, $path);
        header('Location:' . $link);exit;
    }

    /**
     * 获取js输出静态文件地址
     * @param [type] $app
     * @param [type] $path
     * @return void index.html?app=sheetjs&callback=fileout
     */
    public function fileLink($app, $path){
        // $pluginHost = $GLOBALS['config']['PLUGIN_HOST'];
        $pluginHost = APP_HOST . 'plugins/';
        $pluginHost .= str_replace('Plugin', '', $this->pluginName);
        $link = $pluginHost . "/static/office/{$app}/index.html";
        $callback = APP_HOST . '?explorer/index/fileOut&path=' . $path;
        return $link . '?app=' . $app . '&callback=' . urlencode($callback);
    }

    /**
     * 在线预览：docx
     * https://github.com/mwilliamson/mammoth.js
     * @param [type] $path
     * @return void
     */
    public function mammothjs($path){

    }

    /**
     * 在线预览：xls/xlsx
     * https://github.com/SheetJS/sheetjs
     * @param [type] $path
     * @return void
     */
    public function sheetjs($path){

    }

    /**
     * 在线编辑：xlsx
     * https://github.com/fleetscythe/Luckysheet
     * @param [type] $path
     * @return void
     */
    public function luckySheet($path){

    }

}