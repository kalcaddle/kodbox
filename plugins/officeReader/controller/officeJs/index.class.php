<?php 
/**
 * office文档前端解析
 * 
 * docx：https://github.com/mwilliamson/mammoth.js
 * xls/xlsx：https://github.com/SheetJS/sheetjs
 * xlsx：https://github.com/mengshukeji/Luckysheet
 *       https://mengshukeji.github.io/LuckysheetDocs/zh/guide
 *       https://madewith.cn/709
 * pptx：https://github.com/meshesha/PPTXjs
 * 
 * 在线转换：https://convertio.co/zh/xls-xlsx/
 */
class officeReaderOfficeJsIndex extends Controller {
    protected $pluginName;
	public function __construct() {
		parent::__construct();
		$this->pluginName = 'officeReaderPlugin';
    }

    public function index(){
        $extList = array(
            // 'doc'  => 'mammothjs', 
            'docx'  => 'mammothjs', 
            'xlsx'  => 'luckysheet', // sheetjs
            'xls'   => 'luckysheet',
            'pptx'  => 'pptxjs',
            // 'ppt'   => 'pptxjs',
        );
        $ext = $this->in['ext'];
        if(!isset($extList[$ext])) return;
        $app = $extList[$ext];
        Action($this->pluginName)->showJsTpl($app);
    }

}