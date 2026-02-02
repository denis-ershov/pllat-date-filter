# Компиляция файлов переводов

Файлы `.po` нужно скомпилировать в `.mo` файлы, чтобы WordPress мог их использовать.

## Способ 1: Использование msgfmt (рекомендуется)

### Windows:
1. Установите MinGW с компонентом `mingw32-gettext`
2. Добавьте `C:\MinGW\bin` в PATH
3. Выполните в папке плагина:
   ```bash
   msgfmt -o languages/pllat-date-filter-ru_RU.mo languages/pllat-date-filter-ru_RU.po
   msgfmt -o languages/pllat-date-filter-en_US.mo languages/pllat-date-filter-en_US.po
   ```

### Mac:
```bash
brew install gettext
export PATH="/usr/local/opt/gettext/bin:$PATH"
msgfmt -o languages/pllat-date-filter-ru_RU.mo languages/pllat-date-filter-ru_RU.po
msgfmt -o languages/pllat-date-filter-en_US.mo languages/pllat-date-filter-en_US.po
```

### Linux:
```bash
sudo apt-get install gettext  # или эквивалент для вашего дистрибутива
msgfmt -o languages/pllat-date-filter-ru_RU.mo languages/pllat-date-filter-ru_RU.po
msgfmt -o languages/pllat-date-filter-en_US.mo languages/pllat-date-filter-en_US.po
```

## Способ 2: Использование WP-CLI

Если у вас установлен WP-CLI:
```bash
wp i18n make-mo languages/
```

## Способ 3: Онлайн-инструменты

Вы можете использовать онлайн-конвертеры:
- https://po2mo.net/
- https://www.easytranslation.com/po-to-mo-converter

Загрузите `.po` файл и скачайте скомпилированный `.mo` файл.

## Способ 4: Использование скрипта compile-translations.php

Если у вас установлен PHP с расширением gettext:
```bash
php compile-translations.php
```

## После компиляции

1. Убедитесь, что файлы `.mo` созданы в папке `languages/`
2. Очистите кэш WordPress (если используется плагин кэширования)
3. Перезагрузите страницу настроек плагина в админке WordPress
4. Если переводы все еще не отображаются, проверьте:
   - Правильно ли установлен язык WordPress в настройках
   - Правильно ли указан `Domain Path: /languages` в заголовке плагина
   - Загружается ли textdomain через `load_plugin_textdomain()`

## Проверка

После компиляции в папке `languages/` должны быть файлы:
- `pllat-date-filter-ru_RU.mo` (обновлен)
- `pllat-date-filter-en_US.mo` (обновлен)
