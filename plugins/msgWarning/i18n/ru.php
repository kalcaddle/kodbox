<?php
return array(
    "msgWarning.meta.name"           => "Предупреждение о сообщении",
    "msgWarning.meta.title"          => "Предупреждение о ненормальном системном сообщении",
    "msgWarning.meta.desc"           => "Когда система находится в ненормальном состоянии, администратору будет отправлено предупреждение для своевременной обработки с целью обеспечения нормальной работы системы.",
    "msgWarning.config.setDesc"      => "<div class='info-alert info-alert-blue p-10 align-left can-select can-right-menu'>[бр]<li> Этот плагин используется для напоминания о системных сообщениях, вызывающих аномальные события. Конкретную настройку можно выполнить в разделе <a href='./#admin/tools/warning' target='_blank'>«Управление безопасностью» — «Предупреждение о сообщениях».</a></li> [бр]</div>",
    "msgWarning.config.sysNtc"       => "Системные сообщения",
    "msgWarning.config.sysNtcDesc"   => "<div class=\"desc mt-10 mb-10\">Этот элемент отслеживает использование учетных записей администратора, дискового пространства и т. д. Если обнаружатся какие-либо отклонения, устраните их своевременно, чтобы обеспечить нормальную работу системы.</div>",
    "msgWarning.config.setNtc"       => "Настройки уведомлений",
    "msgWarning.config.openNtc"      => "Включить раннее предупреждение",
    "msgWarning.config.openNtcDesc"  => "Этот элемент отслеживает использование процессора и памяти. В случае возникновения аномалий информация будет отправлена указанному получателю вместе с сообщением об аномалии системы (если таковое имеется).",
    "msgWarning.config.warnType"     => "Тип предупреждения",
    "msgWarning.config.warnTypeCpu"  => "Использование ЦП",
    "msgWarning.config.warnTypeMem"  => "Использование памяти",
    "msgWarning.config.useRatio"     => "Коэффициент использования",
    "msgWarning.config.useRatioDesc" => "Коэффициент использования превышает M%",
    "msgWarning.config.useTime"      => "Продолжительность",
    "msgWarning.config.useTimeTips"  => "Продолжительность не может быть меньше 10 минут!",
    "msgWarning.config.useTimeDesc"  => "Если процент использования превышает M%, а продолжительность превышает N минут, сработает напоминание.",
    "msgWarning.config.sendType"     => "Способ отправки",
    "msgWarning.config.dingTalk"     => "DingTalk",
    "msgWarning.config.weChat"       => "WeChat Бизнес",
    "msgWarning.config.email"        => "почта",
    "msgWarning.config.target"       => "Отправить цель",
    "msgWarning.config.targetDesc"   => "Выбранный целевой пользователь должен быть привязан к допустимому назначенному методу отправки.",
    "msgWarning.main.tipsTitle"      => "Предупреждение во время выполнения",
    "msgWarning.main.msgSysOK"       => "Система нормальная!",
    "msgWarning.main.msgPwdErr"      => "Вы используете исходный пароль. В целях безопасности, пожалуйста, смените пароль как можно скорее.",
    "msgWarning.main.msgEmlErr"      => "Вы ещё не привязали свой адрес электронной почты. Чтобы обеспечить нормальную работу уведомлений и восстановления пароля, пожалуйста, привяжите свой адрес электронной почты как можно скорее.",
    "msgWarning.main.msgSysPathErr"  => "Системный путь сервера неверен (корневой каталог «%s» должен иметь разрешение на чтение или включать функцию exec)",
    "msgWarning.main.msgSysSizeErr"  => "Недостаточно свободного места на системном диске сервера (%s)",
    "msgWarning.main.msgDefPathErr"  => "Системное <a href=\"%s\" style=\"padding:0px;text-decoration:none;\">хранилище по умолчанию</a> неисправно. Проверьте соответствующую конфигурацию и разрешения на чтение и запись.",
    "msgWarning.main.msgDefSizeErr"  => "Системное <a href=\"%s\" style=\"padding:0px;text-decoration:none;\">хранилище по умолчанию</a> недостаточно (%s)",
    "msgWarning.main.setNow"         => "Настройте сейчас",
    "msgWarning.main.msgSysErr"      => "Уровень использования сервера %s превысил %s (текущее значение %s) за последние %s минут. Чтобы не нарушить нормальную работу системы, проверьте и оптимизируйте соответствующие конфигурации.",
    "msgWarning.main.msgEmpty"       => "Пустой!",
    "msgWarning.main.msgFmtErr"      => "Неправильный формат!",
    "msgWarning.main.ignoreTips"     => "Не напоминать",
    "msgWarning.main.taskTitle"      => "Предупреждение о сообщении",
    "msgWarning.main.taskDesc"       => "Предупреждение об использовании системы. Эта задача выполняется плагином [Message Warning].",
    "msgWarning.main.memory"         => "Память",
    "msgWarning.main.ntcTitle"       => "Напоминание об аномалии сервера"
);