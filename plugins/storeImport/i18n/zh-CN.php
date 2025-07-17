<?php
return array(
	'storeImport.meta.name'			=> "数据导入",
	'storeImport.meta.title'		=> "存储数据快速导入",
	'storeImport.meta.desc'			=> "本插件提供了存储数据导入功能，通过对指定磁盘或对象存储添加的存储进行扫描，实现存储数据快速导入到网盘。",
	
	'storeImport.main.import'		=> "导入",
	'storeImport.main.dataImport'	=> "存储数据导入",
	'storeImport.main.ioNotSup'		=> "不支持的存储类型：",
	'storeImport.main.ioPathErr'=> "原始数据目录错误，必须为存储目录：{io:x}",
	'storeImport.main.ioStoreErr'	=> "原始数据所在存储无效！",
	'storeImport.main.ioNotSupErr'	=> "不支持此存储类型导入：",
	'storeImport.main.ioFromNetErr'	=> "原始数据所在存储无法连接。",
	'storeImport.main.ioToErr'		=> "网盘存放目录错误，必须为网盘系统目录：个人空间或部门及其子目录",
	'storeImport.main.noPermission'	=> "您无权执行此操作！",
	'storeImport.main.loginTokenErr'=> "登录状态已失效，请求重新获取执行命令。",

	'storeImport.main.ioFromPath'	=> "原始数据目录",
	'storeImport.main.selectPath'	=> "选择目录",
	'storeImport.main.ioFromPathDesc'=> "需要导入的原始数据目录，必须为存储下的目录",
	'storeImport.main.ioFromPathErr'=> "原始数据目录错误，必须为存储目录！",
	'storeImport.main.ioToPath'		=> "网盘存放目录",
	'storeImport.main.ioToPathDesc'	=> "需要存放的网盘系统目录，如个人空间、企业网盘或其子目录",
	'storeImport.main.ioToPathErr'	=> "网盘存放目录错误，必须为网盘系统目录！",
	'storeImport.main.rootGroup'	=> "企业网盘",
	'storeImport.main.importDesc'	=> "<h5>通过对磁盘或对象存储进行扫描，自动构建索引，实现文件快速导入到网盘。</h5>
										<div class='mt-10 mb-20'>
											<li>1. 网盘文件需通过存储路径访问，<u>因此在导入之前，需将待导入的原始数据目录（或父级目录）添加为存储</u>；</li>
											<li>2. 此操作不会对原始数据做改动，只构建文件索引映射到网盘，<u>在导入之后，不能对原始数据做任何改动，避免索引失效</u>；</li>
											<li>3. 导入数据与网盘默认存储数据的路径结构不同，建议将导入数据的存储作为附加存储（而非系统默认存储）使用，以维护数据结构统一；</li>
											<li>4. 导入之前建议对数据库进行备份，以免出现意外。</li>
										</div>
										<div>注意：<u>文件路径长度超过256个字符会被限制导入</u>，相关日志放在网盘存放目录下的“导入失败日志-长度超256字符”文件夹中，可在导入完成后自行查看并处理。</div>",

	'storeImport.task.rptErr'		=> "任务执行中，请勿重复操作！",
	'storeImport.task.subErr'		=> "任务进行中，请勿重复提交！",
	'storeImport.task.stopByUser'	=> "任务手动终止！",
	'storeImport.task.start'		=> "存储导入数据，开始：",
	'storeImport.task.end'			=> "存储导入数据，完成：",
	'storeImport.task.startExt'		=> "，开始",
	'storeImport.task.starting'		=> "准备导入...(读取中，请稍候)",
	'storeImport.task.ing'			=> "正在导入...",
	'storeImport.task.stopErr'		=> "异常终止！",
	'storeImport.task.stopErrDesc'	=> "存储导入数据，异常中断",
	'storeImport.task.afterTime'	=> "剩余时间约：",
	'storeImport.task.errLog'		=> "导入失败日志-长度超256字符",

	'storeImport.task.importEnd'	=> "导入完成！",
	'storeImport.task.taskEnd'		=> "任务结束！",
	'storeImport.task.taskErr'		=> "任务异常！",
	'storeImport.task.reqErr'		=> "请求失败，或任务终止！",
	'storeImport.task.getting'		=> "读取中，请稍候",
	'storeImport.task.desc1'		=> "数据量很大时，可能会因为超时导致任务中止，可选择在终端执行以下命令进行导入：",
	'storeImport.task.desc2'		=> "注意：此命令使用 `sudo -u nginx` 是为了确保 PHP 进程以正确的用户身份执行，避免文件权限问题。如果你的 Web 服务器用户不是 `nginx`，请根据你的服务器配置将 `nginx` 替换为适当的用户，如 root、www-data/apache 等。",
);