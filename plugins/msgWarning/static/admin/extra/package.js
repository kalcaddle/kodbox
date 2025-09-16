define(function(require, exports) {
    return {
        // 通知方式form
        ntcType: {
            email: {
				"type":{
					"type":"segment",
					"value":"0",
					"display":LNG['msgWarning.type.svrEmail'],
					"className":"mr-10",
					"desc":'<br/>'+LNG['admin.setting.sendEmailDesc'],
					"info":{"0":LNG['common.systemDefault'],"1":LNG['common.diy']},
					"switchItem":{
						"0":"",
						"1":`sep602,smtp,host,email,secure,password`
					},
				},
				"sep001":"<hr/>",
				"smtp":{
					// "type":"radio",
					"type":"segment",
					"value":"1",
					"display":LNG['admin.setting.emailType'],
					"info":{"1":"SMTP","2":LNG['common.others']},
				},
				"host":{
					"type":"input",
					"value":"",	
					"display":LNG['admin.setting.emailHost'],
					"attr": {placeholder: LNG['admin.setting.emailHostInput']},
					"desc":'<br/>'+LNG['admin.setting.emailHostDesc'],
					// "require":1
				},
				"email":{
					"type":"input",
					"value":"",	
					"display":LNG['admin.setting.emailSend'],
					"attr": {placeholder: LNG['admin.setting.emailSendInput']},
					"desc":'<br/>'+LNG['admin.setting.emailSendDesc'],
					// "require":1
				},
				"secure":{
					"type":"segment",
					"value":"ssl",
					"display":LNG['admin.setting.secureType'],
					"info":{"none":LNG['admin.setting.disFunNo'],"ssl":"SSL","tls":"TLS"},
				},
				"password":{
					"type":"password",
					"value":"",	
					"display":LNG['admin.setting.emailPwd'],
					"attr": {placeholder: LNG['admin.setting.emailPwdTips']},
					"desc":"<a data-action='emailTest' href='javascript:void(0)'>"+LNG['admin.setting.emailSendTest']+"</a>&nbsp;&nbsp;"+LNG['admin.setting.ensureEmailOk'],
				},
				"tested":{
					"type":"input",
					"value":0,	
					"display":LNG['msgWarning.type.emailTest'],
					"className":"hidden"
				},
			},
			sms: {
				"type":{
					"type":"segment",
					"value":"0",
					"display":LNG['msgWarning.type.svrSms'],
					"className":"mr-10",
					"desc":'<br/>'+LNG['msgWarning.type.smsDesc'],
					"info":{"0":LNG['common.systemDefault'],"1":LNG['common.diy']},
					"switchItem":{
						"0":"",
						"1":`desc`
					},
				},
				"desc":{
					"type":"html",
					"value":"<div class='info-alert info-alert-blue'>"+LNG['msgWarning.type.smsSvrDesc']+" <a href='./#admin/plugin&tab=buy' target='_blank'>"+LNG['msgWarning.main.goSet']+"</a></div>",
					"display":LNG['admin.setting.recDesc']
				}
			},
			weixin: {
				"status": {
					"type":"switch",
					"value":0,
					"display":LNG['msgWarning.main.openSvr'],
					"desc":LNG['msgWarning.type.weixinDesc']+" <a href='./#admin/plugin&tab=buy' target='_blank'>"+LNG['msgWarning.main.goSet']+"</a>",
					"require":1
				},
			},
			dding: {
				"status": {
					"type":"switch",
					"value":0,
					"display":LNG['msgWarning.main.openSvr'],
					"desc":LNG['msgWarning.type.ddingDesc']+" <a href='./#admin/plugin&tab=buy' target='_blank'>"+LNG['msgWarning.main.goSet']+"</a>",
					"require":1
				},
			},
        },



        // TODO 同一个事件可能需要发给不同的用户，如磁盘异常，需同时发给管理员和用户，且用户仅kwarn
        // 由此拓展：同一时间可能不同触发条件，发给不同的人
        ntcEvnt: {
            formStyle:{
                className:"dialog-form-has-menu dialog-form-style-default form-box-title-left",
                classNameWap:"dialog-form-has-menu dialog-form-style-simple form-box-title-block",
                tabs:{
                    info:"event,title,desc,class,level,status",
                    policy:"polTitle",
                    notice:"ntcTarget,ntcMethod,ntcMore,ntcCntMax,ntcCntMaxDay,ntcTimeFreq,ntcTimeFrom,ntcTimeTo",
                    eg:"egType,egTypeKtips,egTypeKwarn,egTypeEmail,egTypeSms,egTypeWeixin,egTypeDding",
                },	
                tabsName:{
                    info:	LNG['msgWarning.evnt.basic'],
                    policy:	LNG['msgWarning.evnt.policy'],
                    notice: LNG['msgWarning.evnt.setting'],
                    eg:		LNG['msgWarning.evnt.example'],
                }
            },

            event:{
                type: "input",
                value: "",
                display:LNG['msgWarning.evnt.title'],
                className:"hidden"
            },
            title:{
                type: "html",
                value: "",
                display:LNG['msgWarning.evnt.title']
            },
            desc:{
                type: "html",
                value: "",
                display:LNG['msgWarning.evnt.desc']
            },
            class:{
                type: "html",
                value: "",
                display:LNG['msgWarning.evnt.class']
            },
            level:{
                type: "html",
                value: "",
                display:LNG['msgWarning.evnt.level']
            },
            status:{
                type: "switch",
                value: 0,
                display:LNG['msgWarning.evnt.status'],
                // className:"disable-event"
                className:"switch-not-allowed"
            },

            polTitle: {
                type: "html",
                value: LNG['msgWarning.evnt.polDesc'],
                display:LNG['msgWarning.evnt.polTitle']
            },
            
            ntcTarget:{
                type: "userGroup",
                value: "",
                display:LNG['msgWarning.evnt.target'],
                selectType:"mutil",
                require: 1,
			},
            // ntcMethod:{
            //     type: "select",
            //     value: "",
            //     display:"通知方式",
            //     info:{},
            //     selectType:"mutil",
            //     desc:'',
            //     require: 1,
			// },
            ntcMethod:{
                type: "checkbox",
                value: "",
                display:LNG['msgWarning.evnt.method'],
                info:{},
                desc:'',
                require: 1,
            },
            ntcMore:{
				type:"button",
				className:"form-button-line",//横线腰线
				value:"",
				info:{
					more:{
						display:LNG['msgWarning.evnt.setMore']+" <b class='caret'></b>",
						className:"btn-default btn-sm btn",
					}
				},
				switchItem:{
                    more:"ntcCntMax,ntcCntMaxDay,ntcTimeFreq,ntcTimeFrom,ntcTimeTo"
                },
			},
            ntcCntMax:{
                type: "number",
                value: 0,
                display:LNG['msgWarning.evnt.cntMax'],
                desc:LNG['msgWarning.evnt.cntDesc'],
                info:{"from":1},
            },
            ntcCntMaxDay:{
                type: "number",
                value: 0,
                display:LNG['msgWarning.evnt.cntMaxDay'],
                desc:LNG['msgWarning.evnt.cntDesc'],
                info:{"from":1},
            },
            ntcTimeFreq:{
                type: "number",
                value: 60,
                display:LNG['msgWarning.evnt.timeFreq'], // 发送频率
                desc:LNG['common.minute'],
                info:{"from":1},
            },
            ntcTimeFrom:{
				type:"dateTime",
				value:"00:00",
				info:{"format":"H:i"},
				attr:{"style":"width:80px;"},
				display:LNG['msgWarning.evnt.timeRange'],
                className:"switch-not-allowed"
			},
			ntcTimeTo:{
				type:"dateTime",
				value:"23:59",
				info:{"format":"H:i"},
				attr:{"style":"width:80px;"},
                className:"switch-not-allowed"
			},


			egType:{
				type:"segment",
				value:"ktips",
                display:LNG['msgWarning.evnt.method'],
				info:{
					ktips:	LNG['msgWarning.type.ktips'],
					kwarn:	LNG['msgWarning.type.kwarn'],
					email:	LNG['msgWarning.type.email'],
					// sms:	LNG['msgWarning.type.sms'],	// TODO 暂不支持
					weixin:	LNG['msgWarning.type.weixin'],
					dding:	LNG['msgWarning.type.dding'],
				},
				switchItem:{
					ktips: 	'egTypeKtips',
					kwarn: 	'egTypeKwarn',
					email: 	'egTypeEmail',
					// sms: 	'egTypeSms',
					weixin: 'egTypeWeixin',
					dding: 	'egTypeDding',
				}
			},
			egTypeKtips:{
				type: "html",
				value: "",
				display:LNG['msgWarning.type.ktips'],
			},
			egTypeKwarn:{
				type: "html",
				value: "",
				display:LNG['msgWarning.type.kwarn'],
			},
			egTypeEmail:{
				type: "html",
				value: "",
				display:LNG['msgWarning.type.ntcEmail'],
			},
			// egTypeSms:{
			// 	type: "html",
			// 	value: "",
			// 	display:LNG['msgWarning.type.ntcSms'],
			// },
			egTypeWeixin:{
				type: "html",
				value: "",
				display:LNG['msgWarning.type.ntcWeixin'],
			},
			egTypeDding:{
				type: "html",
				value: "",
				display:LNG['msgWarning.type.ntcDding'],
			},
        },
    };
});