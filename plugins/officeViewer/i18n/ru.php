<?php
return array(
    "officeViewer.meta.name"         => "Средство просмотра Office",
    "officeViewer.meta.title"        => "Просмотр Office онлайн",
    "officeViewer.meta.desc"         => "Просматривайте файлы Office прямо в браузере. Приложение объединяет возможности WebOffice, LibreOffice, Office Live, Yongzhong Office и других служб для корректного отображения ваших документов.",
    "officeViewer.meta.netwrokDesc"  => "<h4>Примечание:</h4> Для работы некоторых функций требуется подключение к сторонним сервисам. WebOffice и LibreOffice работают локально без внешних запросов. Остальные службы используют следующие API:<br/>
                                         Office Live:<br/>
                                         <span class='blue-6'>https://owa-box.vips100.com</span><br/>
                                         <span class='blue-6'>https://docview.mingdao.com</span><br/>
                                         <span class='blue-6'>https://preview.tita.com</span><br/>
                                         <span class='blue-6'>https://view.officeapps.live.com</span><br/>
                                         Yozo Office:<br/>
                                         <span class='blue-6'>http://dcs.yozosoft.com</span>",
    "officeViewer.meta.netwrokUrl"   => "URL интерфейса",
    "officeViewer.meta.service"      => "Настройки служб",
    "officeViewer.meta.openType"     => "Способ открытия",
    "officeViewer.meta.instruction"  => "Инструкции",
    "officeViewer.meta.svcOpen"      => "Включить службы",

    "officeViewer.main.error"        => "Ошибка выполнения операции.",
    "officeViewer.main.invalidType"  => "Недопустимый способ открытия. Свяжитесь с администратором.",
    "officeViewer.main.invalidUrl"   => "Недопустимый адрес запроса. Свяжитесь с администратором.",
    "officeViewer.main.notNetwork"   => "Ошибка подключения. Проверьте настройки сети на сервере.",
    "officeViewer.main.needNetwork"  => "Сервер должен иметь доступ к интернету",
    "officeViewer.main.needDomain"   => " и быть доступен по доменному имени.",
    "officeViewer.main.tryAgain"     => " Попробуйте еще раз.",
    "officeViewer.main.invalidExt"   => "Формат файла не поддерживается.",

    "officeViewer.webOffice.name"    => "Автоматический выбор",
    "officeViewer.webOffice.desc"    => "При выборе [Автоматический выбор] система сначала попытается использовать <code style='color:#c7254e'>локальный парсинг</code> (кроме doc и ppt). Если формат не поддерживается, будет выбран другой метод.<br>Локальный парсинг работает быстрее и не требует внешних подключений, однако некоторые элементы могут отображаться некорректно.<br><br>Если вам важно точное отображение форматирования, выберите другой метод.",
    "officeViewer.webOffice.parsing" => "Идет обработка...",
    "officeViewer.webOffice.reqErrPath" => "Ошибка запроса. Проверьте целостность файла.",
    "officeViewer.webOffice.reqErrNet" => "Время ожидания истекло. Проверьте соединение с интернетом.",
    "officeViewer.webOffice.reqErrUrl" => "Ошибка доступа к файлу. Проверьте корректность адреса.",
    "officeViewer.webOffice.noEditTips" => "Редактирование в этом режиме недоступно. Пожалуйста, выберите другой способ.",
    "officeViewer.webOffice.warning" => "⚠️ В этом режиме формулы, графики и сложное форматирование могут отображаться некорректно. Используйте другие методы для просмотра полной версии документа.",
    "officeViewer.libreOffice.desc"  => "<div style='margin-top:3px;'>Использует установленный на сервере LibreOffice для конвертации файлов в PDF.</div>",
    "officeViewer.libreOffice.checkError" => "Не удалось запустить LibreOffice. Убедитесь, что программа установлена и у сервера есть права на выполнение.",
    "officeViewer.libreOffice.sofficeError" => "Ошибка службы LibreOffice. Проверьте настройки и попробуйте снова.",
    "officeViewer.libreOffice.convertError" => "Ошибка конвертации. Проверьте доступность файла и работу службы Office.",
    "officeViewer.libreOffice.execDisabled" => "Функция [shell_exec] отключена в PHP. Включите её для работы этого модуля.",
    "officeViewer.libreOffice.path"  => "Путь к LibreOffice",
    "officeViewer.libreOffice.pathDesc" => "<br/>
        <span style='margin: 5px 0px;margin-bottom:10px;display: inline-block;'>Укажите путь к исполняемому файлу soffice в директории установки LibreOffice.</span> <br/>
        <button class='btn btn-success check-libreoffice' style='padding: 5px 12px;border-radius: 3px;font-size: 13px;'>Проверить путь</button>
        <a style='padding: 6px 12px; vertical-align: middle;' target='_blank' href='https://zh-cn.libreoffice.org/get-help/install-howto/'>Руководство по установке</a>",
    "officeViewer.libreOffice.check" => "Проверка сервера",
    "officeViewer.libreOffice.checkTitle" => "Диагностика LibreOffice",
    "officeViewer.libreOffice.checkIng" => "Выполняется проверка...",
    "officeViewer.libreOffice.checkDesc" => "Проверьте конфигурацию сервера и повторите попытку.",
    "officeViewer.libreOffice.checkOk" => "Все системы работают нормально.",
    "officeViewer.libreOffice.checkErr" => "Ошибка окружения.",

    "officeViewer.officeLive.desc"   => "<div style='margin-top:3px;'>Использует службы Microsoft Office для онлайн-просмотра.<br/><code style='color:#c7254e'>Сервер должен иметь доступ к интернету и доменное имя.</code><br>Пользователи локальной сети могут развернуть свой сервер; <a href='https://kodcloud.com/help/show-5.html' target='_blank'>подробнее</a></div>",
    "officeViewer.officeLive.apiServer" => "Сервер API",
    "officeViewer.officeLive.apiServerDesc" => "<div class='can-select'>Выберите один из официальных или сторонних сервисов:<br/>
                                               <div class='mt-5'> https://view.officeapps.live.com/op/embed.aspx?src=</div>
                                               <div class='grey-8'>https://owa-box.vips100.com/op/view.aspx?src=</div>
                                               <div class='grey-8'>https://docview.mingdao.com/op/view.aspx?src=</div>
                                               <div class='grey-8'>https://preview.tita.com/op/view.aspx?src=</div>
                                               <div class='grey-8'>https://view.officeapps.live.com/op/view.aspx?src=</div></div>",
    "officeViewer.yzOffice.name"     => "Yozo Office",
    "officeViewer.yzOffice.desc"     => "<div style='margin-top:3px;'>Использует службы Yozo Office для просмотра (файл временно загружается на их сервер).<br><code style='color:#c7254e'>Сервер должен иметь доступ к интернету.</code></div>",
    "officeViewer.yzOffice.transfer" => "1. Передача данных...",
    "officeViewer.yzOffice.converting" => "2. Конвертация файла, подождите...",
    "officeViewer.yzOffice.uploadError" => "Ошибка загрузки. Проверьте лимит времени исполнения PHP (max_execution_time).",
    "officeViewer.yzOffice.convert"  => "Преобразование...",
    "officeViewer.yzOffice.transferAgain" => "Повторить",
    "officeViewer.yzOffice.linkExpired" => "Срок действия ссылки истек.",
    "officeViewer.main.fileSizeErr"  => "Файл поврежден (размер 0) или недоступен.",
    "officeViewer.main.typeErr"      => "Нет доступных способов для просмотра данного файла."
);