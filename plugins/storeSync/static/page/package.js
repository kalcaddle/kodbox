define(function(require, exports) {
	return {
		formStyle: {className:"form-inline"},
		enable:{
			type:"switch",
			value:1,	
			display: LNG['storeSync.task.open'],
			switchItem:{"0":"","1":"title,pathA,pathB,isBidi,rptType,checkNum"},
			desc: LNG['storeSync.task.openDesc']
		},
		title: {
			type:"input",
			value:"",	
			display: LNG['storeSync.task.name'],
			"require":1
		},
		pathA: {
			type: "fileSelect",
			value: "",
			display: LNG['storeSync.task.pathA'],
			info:{
				title:LNG['explorer.selectFolder'],
				single:true,
				type:'folder',
				// pathOpen:'{io:1}/',
				valueKey:'path',
				valueShowKey:'pathDisplay', //显示的key;
				valueShow:'',//显示的key的值;
				// pathTree:'{block:driver}/',
			},
			require:1
		},
		pathB: {
			type: "fileSelect",
			value: "",
			display: LNG['storeSync.task.pathB'],
			info:{
				title:LNG['explorer.selectFolder'],
				single:true,
				type:'folder',
				// pathOpen:'{io:1}/',
				valueKey:'path',
				valueShowKey:'pathDisplay', //显示的key;
				valueShow:'',//显示的key的值;
				// pathTree:'{block:driver}/',
			},
			require:1
		},
		isBidi:{
			type:"switch",
			value:0,	
			display: LNG['storeSync.task.isBidi'],
		},
		rptType: {
			type:"radio",
			value:"1",
			display: LNG['storeSync.task.rptName'],
			info:{"0":LNG['common.cover'],"1":LNG['common.skip'],"2":LNG['common.rename']},
			desc:"<br/>"+LNG['storeSync.task.rptNameDesc'],
			require:1
		},
		// checkNum:{
		// 	type:"switch",
		// 	value:0,	
		// 	display:"检查文件数",
		// 	// className: 'hidden',
		// 	desc:"文件过多时（>100000）不支持同步"
		// },

    }
});