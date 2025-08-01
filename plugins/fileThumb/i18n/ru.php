<?php
return array(
    "fileThumb.meta.name"            => "Генерация обложки документа",
    "fileThumb.meta.title"           => "Предварительный просмотр PSD-файлов, PDF-файлов, инструмент для создания миниатюр видео, перекодирование видеофайлов",
    "fileThumb.meta.desc"            => "<b>Предварительный просмотр PSD&AI и других файлов:</b> создание изображений предварительного просмотра и поддержка прямого открытия<br/> \n <b>Обложка файла видео pdf:</b> pdf, генерация миниатюр видео, получение EXIF-изображения; автоматическая коррекция направления снимков, сделанных мобильным телефоном; требуется расширение PHP imagick;<br/> \n <b>Транскодирование видео:</b> при воспроизведении видео оно автоматически перекодируется, генерируется сглаженная версия и кэшируется. При последующем воспроизведении по умолчанию будет воспроизводиться сглаженная версия. Вы можете переключиться на исходный режим изображения.",
    "fileThumb.Config.missLib"       => "Отсутствует расширение PHP imagick. Установите его и повторите попытку.",
    "fileThumb.Config.fileThumbExt"  => "Миниатюры файлов",
    "fileThumb.Config.fileThumbExtDesc" => "Типы файлов, которые imagick поддерживает для создания миниатюр",
    "fileThumb.config.file"          => "Настройки ассоциации файлов",
    "fileThumb.config.use"           => "и т. д. Генерация миниатюр файлов",
    "fileThumb.config.test"          => "Тест соединения",
    "fileThumb.config.help"          => "Руководство по развертыванию услуг",
    "fileThumb.config.convertTips"   => "Автоматически перекодировать при воспроизведении видео",
    "fileThumb.config.videoOpen"     => "Включить перекодирование видео",
    "fileThumb.config.videoOpenDesc" => "Автоматически перекодировать при воспроизведении видео после включения",
    "fileThumb.config.videoSizeLimit" => "Минимальный размер перекодированного файла",
    "fileThumb.config.videoSizeLimitDesc" => "Перекодирование не производится, если значение меньше этого значения.",
    "fileThumb.config.videoSizeLimitTo" => "Максимальный перекодированный файл",
    "fileThumb.config.videoSizeLimitToDesc" => "Если значение больше, перекодирование не производится. Если равно 0, ограничения не налагаются.",
    "fileThumb.config.videoTaskLimit" => "Ограничения параллелизма",
    "fileThumb.config.videoTaskLimitDesc" => "Максимально допустимое количество одновременных задач перекодирования",
    "fileThumb.config.videoTypeLimit" => "Формат файла",
    "fileThumb.config.videoTypeLimitDesc" => "Укажите формат файла для перекодирования",
    "fileThumb.config.playType"      => "Качество видео по умолчанию",
    "fileThumb.config.playTypeDesc"  => "При выборе плавного режима видео необходимо перекодировать.",
    "fileThumb.config.imageSizeLimit" => "Максимальный поддерживаемый размер изображения",
    "fileThumb.config.imageSizeLimitDesc" => "Миниатюры не будут созданы для файлов изображений, размер которых превышает это значение (создание миниатюр потребует много ресурсов сервера, поэтому рекомендуется вручную добавлять изображения обложек для больших изображений).",
    "fileThumb.config.svcType"       => "Метод обслуживания",
    "fileThumb.config.igryOpen"      => "Включить службы",
    "fileThumb.config.igryOpenDesc"  => "Включить воображаемую услугу",
    "fileThumb.config.igryDesc"      => "1. imaginary — высокопроизводительный сервис обработки изображений на базе HTTP, который позволяет значительно повысить эффективность генерации миниатюр и снизить риск переполнения памяти сервера;<br/> \n 2. imaginary поддерживает обработку только некоторых форматов изображений. Для генерации большего количества форматов и изображений обложек файлов (например, Office) он также использует сервисы ImageMagick/FFmpeg;<br/> \n 3. При установке сервиса необходимо включить обработку URL (-enable-url-source)",
    "fileThumb.config.igryHost"      => "Адрес обслуживания",
    "fileThumb.config.igryApiKey"    => "API-ключ",
    "fileThumb.config.igryApiKeyDesc" => "API-ключ (-ключ)",
    "fileThumb.config.igryUrlKey"    => "URL-ключ",
    "fileThumb.config.igryUrlKeyDesc" => "Ключ подписи URL (-url-signature-key), не менее 32 символов",
    "fileThumb.config.igryNotMust"   => "Несущественные",
    "fileThumb.check.title"          => "Обнаружение обслуживания",
    "fileThumb.check.ing"            => "Экологические испытания",
    "fileThumb.check.tips"           => "Проверьте информацию об услуге и повторите попытку после правильной настройки!",
    "fileThumb.check.ok"             => "Поздравляю, все сработало!",
    "fileThumb.check.faild"          => "Условия эксплуатации ненормальные!",
    "fileThumb.check.notFound"       => "Программное обеспечение не найдено. Установите его и повторите попытку.",
    "fileThumb.check.error"          => "Вызов не удался. Проверьте, установлено ли программное обеспечение и есть ли разрешение на его выполнение.",
    "fileThumb.check.svcOk"          => "Обслуживание нормальное.",
    "fileThumb.check.svcErr"         => "Аномалия обслуживания",
    "fileThumb.video.normal"         => "Гладкий",
    "fileThumb.video.before"         => "оригинальная картина",
    "fileThumb.video.title"          => "Транскодирование видео",
    "fileThumb.video.STATUS_SUCCESS" => "Перекодирование прошло успешно, переключился в плавный режим",
    "fileThumb.video.STATUS_IGNORE"  => "Текущее видео не требует перекодирования.",
    "fileThumb.video.STATUS_ERROR"   => "Ошибка выполнения, проверьте, установлено ли программное обеспечение или есть ли у него разрешение на выполнение (ffmpeg, shell_exec, proc_open)",
    "fileThumb.video.STATUS_RUNNING" => "Транскодирование",
    "fileThumb.video.STATUS_LIMIT"   => "Количество текущих задач превысило лимит. Повторите попытку позже.",
    "fileThumb.config.debug"         => "Режим отладки",
    "fileThumb.config.debugDesc"     => "Создавайте соответствующие журналы. <button class='btn btn-sm btn-default ml-20 view-log'>Просмотр журналов</button> ."
);