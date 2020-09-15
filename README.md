## Установка

#### Шаг 1 - Установка

```shell script
cd /path/to/wiki/extensions
git clone git@github.com:wirenboard/wiki-print.git WbPrint
```

#### Шаг 2 - Настройка

Добавляем запись в файл конфигурации /path/to/wiki/LocalSettings.php в самый конец

```shell script
wfLoadExtension('WbPrint');
```