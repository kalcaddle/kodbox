<?php 

/**
 * 通知事件列表
 * 事件详情以ntcEvntList=[event=>{...}]写入数据库，包含type/status/policy/notice，其中policy只取值；计划任务中的通知事件从数据库读取
 * 系统默认事件（system=1），且等级为预警级以上（level>=3），不允许关闭
 * 自定义事件（system=0）可通过hook追加到列表
 */
class NtcEvnt {
    // 通知事件
    const EVNT_DEV_HEALTH           = 'devHealth';      // 存储介质健康预警
    const EVNT_DEV_DISK_ERR         = 'devDiskErr';     // 存储介质异常告警
    const EVNT_DEV_RAID_ERR         = 'devRaidErr';     // 存储阵列异常告警
    const EVNT_DEV_CHANGE           = 'devChange';      // 外接设备状态变更
    const EVNT_SVR_FILESYS_ERR      = 'svrFileSysErr';  // 文件系统异常
    const EVNT_SVR_DISK_SIZE_ERR    = 'svrDiskSizeErr';  // 系统盘存储空间不足
    const EVNT_SVR_CPU_ERR          = 'svrCpuErr';      // 系统CPU资源过载
    const EVNT_SVR_MEM_ERR          = 'svrMemErr';      // 系统内存资源过载
    const EVNT_SYS_STORE_ERR        = 'sysStoreErr';    // 系统存储连接异常
    const EVNT_SYS_STORE_BAK_ERR    = 'sysStoreBakErr'; // 系统备份存储异常
    const EVNT_SYS_STORE_SIZE_ERR   = 'sysStoreSizeErr'; // 默认存储空间不足
    const EVNT_SYS_STORE_DEF_ERR    = 'sysStoreDefErr'; // 默认存储配置风险
    const EVNT_SYS_BAKTASK_ERR      = 'sysBakTaskErr';  // 备份任务异常
    const EVNT_OPS_USER_ACC_ERR     = 'opsUserAccErr';   // 账号安全风险
    const EVNT_DATA_FILE_DOWN_ERR   = 'dataFileDownErr';    // 文件下载异常

    // 通知事件类别
    const EVNT_CLASS_DEV    = 'dev';    // 硬件资源
    const EVNT_CLASS_SVR    = 'svr';    // 操作系统
    const EVNT_CLASS_SYS    = 'sys';    // 系统服务
    const EVNT_CLASS_APP    = 'app';    // 应用服务
    const EVNT_CLASS_OPS    = 'ops';    // 运维管理
    const EVNT_CLASS_DATA   = 'data';   // 数据资产
    const EVNT_CLASS_COLL   = 'coll';   // 协同办公——Collaboration‌
    const EVNT_CLASS_SAFE   = 'safe';   // 安全防护

    // 通知事件等级
    const EVNT_LEVEL_1      = 1;   // 消息级
    const EVNT_LEVEL_2      = 2;   // 提醒级
    const EVNT_LEVEL_3      = 3;   // 预警级
    const EVNT_LEVEL_4      = 4;   // 告警级


    // 通知事件列表（原始值）
    public static function listData() {
        return array(
            array(
                'event'     => self::EVNT_DEV_HEALTH,
                'title'     => LNG('NTC_EVNT_DEV_HEALTH'),
                'level'     => self::EVNT_LEVEL_3,
                'class'     => self::EVNT_CLASS_DEV,
                'desc'      => LNG('NTC_EVNT_DEV_HEALTH_DESC'),
                'message'   => LNG('msgWarning.dev.devHealth'),
                'system'    => 1,   // 系统默认
                'status'    => 1,   // 启用状态

                // 触发条件：字段和前端form.item一致，value代表含义由具体任务具体处理
                'policy'    => array(
                    // 'xxx' => array()
                ),
                // 通知规则：通知频率、时段、次数等
                'notice'    => array(
                    'method'    => 'ktips,email',  // 通知方式
                    'target'    => '{"user":[1]}',   // 通知对象，默认为管理员

                    // 'cntMax' => 0,      // 总发送上限
                    'cntMaxDay' => 1,   // 每日发送上限
                    'timeFreq'  => 60,  // 最小发送频率（分钟）
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'toAll'     => 0,  // 是否通知所有用户——前端通知，以弹窗方式
                // 'unbatch' => 0,  // 非批量的；0为默认的按class分类批量通知，1为单独通知，用于区分独立通知任务——弃用，改为每个事件单独通知
                'result' => array(  // 通知结果
                    'cntToday'  => 0,   // 今日发送次数，注意每天要重置
                    'cntTotal'  => 0,
                    'ntcTime'   => 0,    // 最后通知时间
                    'tskTime'   => 0,    // 最后任务执行时间
                ),
                'taskFreq'  => 60,   // 任务执行频率（分钟）；注意和通知发送频率区分，任务频率<=通知频率
            ),
            array(
                'event'     => self::EVNT_DEV_DISK_ERR,
                'title'     => LNG('NTC_EVNT_DEV_DISK_ERR'),
                'level'     => self::EVNT_LEVEL_4,
                'class'     => self::EVNT_CLASS_DEV,
                'desc'      => LNG('NTC_EVNT_DEV_DISK_ERR_DESC'),
                'message'   => LNG('msgWarning.dev.devDiskErr'),
                'system'    => 1,
                'status'    => 1,
                'notice'    => array(
                    'method'    => 'kwarn,email',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 0,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'toAll'     => 1,
                'taskFreq'  => 10,
            ),
            array(
                'event'     => self::EVNT_DEV_RAID_ERR,
                'title'     => LNG('NTC_EVNT_DEV_RAID_ERR'),
                'level'     => self::EVNT_LEVEL_4,
                'class'     => self::EVNT_CLASS_DEV,
                'desc'      => LNG('NTC_EVNT_DEV_RAID_ERR_DESC'),
                'message'   => LNG('msgWarning.dev.devRaidErr'),
                'system'    => 1,
                'status'    => 1,
                'notice'    => array(
                    'method'    => 'kwarn,email',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 0,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'toAll'     => 1,
                'taskFreq'  => 10,
            ),
            // TODO 暂不支持
            // array(
            //     'event'     => self::EVNT_DEV_CHANGE,
            //     'title'     => LNG('NTC_EVNT_DEV_CHANGE'),
            //     'level'     => self::EVNT_LEVEL_1,
            //     'class'     => self::EVNT_CLASS_DEV,
            //     'desc'      => LNG('NTC_EVNT_DEV_CHANGE_DESC'),
            //     'message'   => '发现了新的设备 /dev/sda1 。',
            //     'system'    => 1,
            //     'status'    => 0,
            //     'notice'    => array(
            //         'method'    => 'ktips',
            //         'target'    => '{"user":[1]}',
            //         // 'cntMax' => 0,
            //         'cntMaxDay' => 0,
            //         'timeFreq'  => 1,    // 计划任务每分钟执行一次，此处没有必要
            //         'timeFrom'  => '00:00',
            //         'timeTo'    => '23:59',
            //     ),
            //     'taskFreq'  => 1,
            // ),
            // array(
            //     'event'     => self::EVNT_SVR_FILESYS_ERR,
            //     'title'     => LNG('NTC_EVNT_SVR_FILESYS_ERR'),
            //     'level'     => self::EVNT_LEVEL_4,
            //     'class'     => self::EVNT_CLASS_SVR,
            //     'desc'      => LNG('NTC_EVNT_SVR_FILESYS_ERR_DESC'),
            //     'message'   => '检查到服务器文件系统出现异常：xxx 。',
            //     'system'    => 1,
            //     'status'    => 1,
            //     'notice'    => array(
            //         'method'    => 'kwarn,email',
            //         'target'    => '{"user":[1]}',
            //         // 'cntMax' => 0,
            //         'cntMaxDay' => 0,
            //         'timeFreq'  => 60,
            //         'timeFrom'  => '00:00',
            //         'timeTo'    => '23:59',
            //     ),
            //     'toAll'     => 1,
            //     'taskFreq'  => 10,
            // ),
            array(
                'event'     => self::EVNT_SVR_DISK_SIZE_ERR,
                'title'     => LNG('NTC_EVNT_SVR_DISK_SIZE_ERR'),
                'level'     => self::EVNT_LEVEL_3,
                'class'     => self::EVNT_CLASS_SVR,
                'desc'      => LNG('NTC_EVNT_SVR_DISK_SIZE_ERR_DESC'),
                'message'   => LNG('msgWarning.svr.diskSizeErr'),
                'system'    => 1,
                'status'    => 1,
                'policy'    => array(
                    'sizeMin' => array(
                        'type'      => 'number',
                        'value'     => 10,
                        'display'   => LNG('admin.member.spaceSize'),
                        'titleRight' => 'GB',
                        // 'desc'      => '',
                        'require'   => 1,
                    ),
                ),
                'notice'    => array(
                    'method'    => 'ktips',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 0,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'taskFreq'  => 5,
            ),
            array(
                'event'     => self::EVNT_SVR_CPU_ERR,
                'title'     => LNG('NTC_EVNT_SVR_CPU_ERR'),
                'level'     => self::EVNT_LEVEL_2,
                'class'     => self::EVNT_CLASS_SVR,
                'desc'      => LNG('NTC_EVNT_SVR_CPU_ERR_DESC'),
                'message'   => LNG('msgWarning.svr.usageErr'),
                'system'    => 1,
                'status'    => 0,
                'policy'    => array(
                    'useRatio' => array(
                        'type'      => 'number',
                        'value'     => 80,
                        'display'   => LNG('msgWarning.evnt.useRatio'),
                        'titleRight' => '&nbsp;%&nbsp;',
                        'desc'      => LNG('msgWarning.evnt.useRatioDesc'),
                        'require'   => 1,
                    ),
                    'useTime' => array(
                        'type'      => 'number',
                        'value'     => 10,
                        'display'   => LNG('msgWarning.evnt.useTime'),
                        'desc'      => LNG('common.minute').'<br/>'.LNG('msgWarning.evnt.useTimeDesc'),
                        'require'   => 1,
                    )
                ),
                'notice' => array(
                    'method'    => 'ktips',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 0,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'taskFreq'  => 1,
            ),
            array(
                'event'     => self::EVNT_SVR_MEM_ERR,
                'title'     => LNG('NTC_EVNT_SVR_MEM_ERR'),
                'level'     => self::EVNT_LEVEL_2,
                'class'     => self::EVNT_CLASS_SVR,
                'desc'      => LNG('NTC_EVNT_SVR_MEM_ERR_DESC'),
                'message'   => LNG('msgWarning.svr.usageErr'),
                'system'    => 1,
                'status'    => 0,
                'policy'    => array(
                    'useRatio' => array(
                        'type'      => 'number',
                        'value'     => 80,
                        'display'   => LNG('msgWarning.evnt.useRatio'),
                        'titleRight' => '&nbsp;%&nbsp;',
                        'desc'      => LNG('msgWarning.evnt.useRatioDesc'),
                        'require'   => 1,
                    ),
                    'useTime' => array(
                        'type'      => 'number',
                        'value'     => 10,
                        'display'   => LNG('msgWarning.evnt.useTime'),
                        'desc'      => LNG('common.minute').'<br/>'.LNG('msgWarning.evnt.useTimeDesc'),
                        'require'   => 1,
                    )
                ),
                'notice' => array(
                    'method'    => 'ktips',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 0,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'taskFreq'  => 1,
            ),

            array(
                'event'     => self::EVNT_SYS_STORE_ERR,
                'title'     => LNG('NTC_EVNT_SYS_STORE_ERR'),
                'level'     => self::EVNT_LEVEL_4,
                'class'     => self::EVNT_CLASS_SYS,
                'desc'      => LNG('NTC_EVNT_SYS_STORE_ERR_DESC'),
                'message'   => LNG('msgWarning.sys.storeErr'),
                'system'    => 1,
                'status'    => 1,
                'notice'    => array(
                    'method'    => 'kwarn,email',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 0,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'toAll'     => 1,
                'taskFreq'  => 10,
            ),
            array(
                'event'     => self::EVNT_SYS_STORE_BAK_ERR,
                'title'     => LNG('NTC_EVNT_SYS_STORE_BAK_ERR'),
                'level'     => self::EVNT_LEVEL_3,
                'class'     => self::EVNT_CLASS_SYS,
                'desc'      => LNG('NTC_EVNT_SYS_STORE_BAK_ERR_DESC'),
                'message'   => LNG('msgWarning.sys.storeBakErr'),
                'system'    => 1,
                'status'    => 1,
                'notice'    => array(
                    'method'    => 'ktips,email',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 1,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'taskFreq'  => 10,
            ),
            array(
                'event'     => self::EVNT_SYS_STORE_SIZE_ERR,
                'title'     => LNG('NTC_EVNT_SYS_STORE_SIZE_ERR'),
                'level'     => self::EVNT_LEVEL_3,
                'class'     => self::EVNT_CLASS_SYS,
                'desc'      => LNG('NTC_EVNT_SYS_STORE_SIZE_ERR_DESC'),
                'message'   => LNG('msgWarning.sys.storeSizeErr'),
                'system'    => 1,
                'status'    => 1,
                'policy'    => array(
                    'sizeMin' => array(
                        'type'      => 'number',
                        'value'     => 10,
                        'display'   => LNG('admin.member.spaceSize'),
                        'titleRight' => 'GB',
                        'desc'      => '<br/>'.LNG('msgWarning.sys.storeSizeErrDesc'),
                        'require'   => 1,
                    ),
                ),
                'notice'    => array(
                    'method'    => 'ktips',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 1,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'taskFreq'  => 5,
            ),
            // // TODO 和cockpit重复，暂不启用
            // array(
            //     'event'     => self::EVNT_SYS_STORE_DEF_ERR,
            //     'title'     => LNG('NTC_EVNT_SYS_STORE_DEF_ERR'),
            //     'level'     => self::EVNT_LEVEL_3,  // 3
            //     'class'     => self::EVNT_CLASS_SYS,
            //     'desc'      => LNG('NTC_EVNT_SYS_STORE_DEF_ERR_DESC'),
            //     'message'   => LNG('msgWarning.sys.sysStoreDefErr'),
            //     'system'    => 1,
            //     'status'    => 1,
            //     'notice'    => array(
            //         'method'    => 'kwarn',
            //         'target'    => '{"user":[1]}',
            //         // 'cntMax' => 0,
            //         'cntMaxDay' => 1,
            //         'timeFreq'  => 60,
            //         'timeFrom'  => '00:00',
            //         'timeTo'    => '23:59',
            //     ),
            //     'toAll'     => 1,
            //     'taskFreq'  => 60,
            // ),
            array(
                'event'     => self::EVNT_SYS_BAKTASK_ERR,
                'title'     => LNG('NTC_EVNT_SYS_BAKTASK_ERR'),
                'level'     => self::EVNT_LEVEL_2,
                'class'     => self::EVNT_CLASS_SYS,
                'desc'      => LNG('NTC_EVNT_SYS_BAKTASK_ERR_DESC'),
                'message'   => LNG('msgWarning.sys.bakTaskErr'),
                'system'    => 1,
                'status'    => 0,
                'notice'    => array(
                    'method'    => 'ktips',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 1,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'taskFreq'  => 60,
            ),

            array(
                'event'     => self::EVNT_OPS_USER_ACC_ERR,
                'title'     => LNG('NTC_EVNT_OPS_USER_ACC_ERR'),
                'level'     => self::EVNT_LEVEL_3,
                'class'     => self::EVNT_CLASS_OPS,
                'desc'      => LNG('NTC_EVNT_OPS_USER_ACC_ERR_DESC'),
                'message'   => LNG('msgWarning.ops.admEmlErr'),
                'system'    => 1,
                'status'    => 1,
                'notice'    => array(
                    'method'    => 'ktips',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 0,
                    'timeFreq'  => 30,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'taskFreq'  => 10,
            ),

            array(
                'event'     => self::EVNT_DATA_FILE_DOWN_ERR,
                'title'     => LNG('NTC_EVNT_DATA_FILE_DOWN_ERR'),
                'level'     => self::EVNT_LEVEL_2,
                'class'     => self::EVNT_CLASS_DATA,
                'desc'      => LNG('NTC_EVNT_DATA_FILE_DOWN_ERR_DESC'),
                'message'   => LNG('msgWarning.data.downFileErr'),
                'system'    => 1,
                'status'    => 0,
                'policy'    => array(
                    'cntMax' => array(
                        'type'      => 'number',
                        'value'     => 100,
                        'display'   => LNG('admin.index.fileCnt'),
                        'desc'      => '<br/>'.LNG('msgWarning.evnt.downFileDesc'),
                        'require'   => 1,
                    ),
                    // 暂不开放
                    // 'sizeMax' => array(
                    //     'type'      => 'input',
                    //     'value'     => 10,
                    //     'display'   => '文件大小',
                    //     'desc'      => 'GB<br/>'.LNG('msgWarning.evnt.downFileDesc'),
                    //     'require'   => 1,
                    // )
                    'pol001'   => '<hr>',
                    'doAction' => array(
                        'type'      => 'switch',
                        'value'     => 0,
                        'display'   => LNG('msgWarning.evnt.downFileAct'),
                        'desc'      => LNG('msgWarning.evnt.downFileActDesc')
                    ),
                ),
                'notice' => array(
                    'method'    => 'ktips',
                    'target'    => '{"user":[1]}',
                    // 'cntMax' => 0,
                    'cntMaxDay' => 0,
                    'timeFreq'  => 60,
                    'timeFrom'  => '00:00',
                    'timeTo'    => '23:59',
                ),
                'taskFreq'  => 1,
            ),
            
        );
    }

}