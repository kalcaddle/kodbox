ClassBase.define({
    init: function () {},
    config: function(){
        return {
            "formStyle":{className:"form-inline"},
            "addBtn":{
                "type":"button",
				"info":{
                    add:{display:'<b class="font-icon ri-add-circle-line"></b>'+LNG['common.add'],className:"kui-btn kui-btn-blue"},
				}
			},
            "status":{
                "type":"segment",
                "value":"all",
                "info":{
                    "all": LNG['common.all'],
                    "1": LNG['msgWarning.main.running'],
                    "0": LNG['msgWarning.main.stopped'],
                },
                // "display":"状态"
            },
            "class":{
                "type":"select",
                "value":"all",
                "info":{
                    "all":  LNG['common.all'],
                    "dev": 	LNG['msgWarning.ntc.clsDev'],
                    "svr": 	LNG['msgWarning.ntc.clsSvr'],
                    "sys": 	LNG['msgWarning.ntc.clsSys'],
                    "app": 	LNG['msgWarning.ntc.clsApp'],
                    "ops": 	LNG['msgWarning.ntc.clsOps'],
                    "data": LNG['msgWarning.ntc.clsData'],
                    "coll": LNG['msgWarning.ntc.clsColl'],
                    "safe": LNG['msgWarning.ntc.clsSafe'],
                },
                "display":LNG['msgWarning.ntc.class']
            },
            "level":{
                "type":"select",
                "value":"",
                "info":{
                    "all": LNG['common.all'],
                    "level1": '<i class="ntc-lvl bg-blue-normal"></i> '+LNG['msgWarning.ntc.level1'],
                    "level2": '<i class="ntc-lvl bg-yellow-normal"></i> '+LNG['msgWarning.ntc.level2'],
                    "level3": '<i class="ntc-lvl bg-orange-normal"></i> '+LNG['msgWarning.ntc.level3'],
                    "level4": '<i class="ntc-lvl bg-red-normal"></i> '+LNG['msgWarning.ntc.level4'],
                },
                "display":LNG['msgWarning.ntc.level']
            },
            
            "time":{
                "type":"segment",
                "value":"30",
                "info":{
                    "0":    LNG['admin.today'],
                    "30":   LNG['admin.monthDay'],
                    "all":  LNG['common.all'],
                    "diy":  LNG['common.diy'],
                },
                "switchItem":{
                    "0":    "",
                    "30":   "",
                    "all":  "",
                    "diy":  "timeFrom,timeTo"
                },
            },
            "timeFrom":{
                "type": "dateTime",
                "value": dateFormat(time()-7*24*3600,"Y-m-d"),
                "info": {"format":"Y-m-d"},
                "className": "inline",
                // "attr": {style:"width:88px!important"},
            },
            "timeTo":{
                "type": "dateTime",
                "value": dateFormat(false,"Y-m-d"),
                "info": {"format":"Y-m-d"},
                "className": "inline",
                // "attr": {style:"width:88px!important"},
            },

            // TODO 通知对象（用户）、通知结果
        }
    }
});