## Установка

#### Шаг 1 - Установка

```shell script
cd /path/to/wiki/extensions
git clone git@github.com:wirenboard/wiki-print.git WbPrint
cd WbPrint
composer install
cp .env.example .env
```

#### Шаг 2 - Настройка

Добавляем запись в файл конфигурации /path/to/wiki/LocalSettings.php в самый конец

```shell script
wfLoadExtension('WbPrint');
```

#### Шаг 3 - CRON

Добавляем запуск генерации PDF раз в сутки

```shell script
@daily /usr/bin/php /path/to/wiki/extensions/WbPrint/cron.php
```

#### Использование

1. Ссылки на генерацию PDF файлов нужно прописать по адрес https://domain/wiki/Print_list в формате [[link]]
2. Сгенеренные ссылки можно получить по адресу https://domain/wiki/Служебная:Print
3. Посмотреть отдельную страницу можно по адресу https://domain/wiki/Служебная:Print/?page=название, например, https://domain/wiki/Служебная:Print/?page=Wiren_Board_6