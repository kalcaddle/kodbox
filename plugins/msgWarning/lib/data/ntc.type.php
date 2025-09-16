<?php 

/**
 * 通知方式列表
 * 列表直接从数组中读取，各配置参数（data/status）从系统实际配置中判断获取，如短信：已安装短信插件且启用，则data=1&status=1
 * 企微/钉钉的启用状态需存数据库，如：ntcTypeList=[weixin=1/0]；后续如果有更多方式需存额外信息，届时再扩展
 */
class NtcType {

    /**
     * 通知方式列表（初始值）
     * @return void
     */
    public static function listData() {
        return array(
			array(
                'type'  => 'ktips', 
                'name'  => LNG('NTC_TYPE_KTIPS'),
                'desc'  => LNG('NTC_TYPE_KTIPS_DESC'),
                'data'  => 1, 
                'status'=> 1
            ),
			array(
                'type'  => 'kwarn', 
                'name'  => LNG('NTC_TYPE_KWARN'),
                'desc'  => LNG('NTC_TYPE_KWARN_DESC'),
                'data'  => 1, 
                'status'=> 1
            ),
			array(
                'type'  => 'email', 
                'name'  => LNG('NTC_TYPE_EMAIL'), 
                'desc'  => LNG('NTC_TYPE_EMAIL_DESC'),
                'data'  => 0, 
                'status'=> 1
            ),
            // TODO 短信当前仅支持验证码，不支持自定义模板，暂不启用
			// array(
            //     'type'  => 'sms', 	
            //     'name'  => LNG('NTC_TYPE_SMS'), 	
            //     'desc'  => LNG('NTC_TYPE_SMS_DESC'),
            //     'data'  => 0, 
            //     'status'=> 0
            // ),
			array(
                'type'  => 'weixin', 	
                'name'  => LNG('NTC_TYPE_WEIXIN'), 
                'desc'  => LNG('NTC_TYPE_WEIXIN_DESC'),
                'data'  => 0, 
                'status'=> 0
            ),
			array(
                'type'  => 'dding', 	
                'name'  => LNG('NTC_TYPE_DDING'), 	
                'desc'  => LNG('NTC_TYPE_DDING_DESC'),
                'data'  => 0, 
                'status'=> 0
            ),
		);
    }

}