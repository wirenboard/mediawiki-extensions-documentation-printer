<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$url = $_ENV['DOMAIN'].'/wiki';
$urlList = $url.'/api.php?action=query&format=json&prop=links&titles=Print_list';
$urlPageInfo = $url.'/api.php?action=query&format=json&prop=info&titles=';
$urlParse = $url.'/%D0%A1%D0%BB%D1%83%D0%B6%D0%B5%D0%B1%D0%BD%D0%B0%D1%8F:Print/?page=';

$cmd = 'chromium-browser --headless --disable-gpu --print-to-pdf={filepath} "{url}"';

$cacheDir = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..').DIRECTORY_SEPARATOR.$_ENV['CACHE_DIR'];
if (!file_exists($cacheDir)) {
    mkdir($cacheDir);
}

$data = json_decode(file_get_contents($urlList));
if (is_object($data)) {
    foreach ($data->query->pages as $pageId => $page) {
        foreach ($page->links as $pageLink) {
            $name = str_replace(' ', '_', $pageLink->title);

            $dataInfo = json_decode(file_get_contents($urlPageInfo.$name), true);

            $pageTime = strtotime($dataInfo['query']['pages'][current(array_keys($dataInfo['query']['pages']))]['touched']);

            $link = $urlParse.$name;
            $fileName = $name.'.pdf';
            $filePath = $cacheDir.DIRECTORY_SEPARATOR.$fileName;

            if (file_exists($filePath)) {
                $fileTime = filemtime($filePath);
                if ($pageTime == $fileTime) {
                    continue;
                }

                unlink($filePath);
            }

            $cmdRun = str_replace(['{filepath}', '{url}'], [$filePath, $link], $cmd);
            exec($cmdRun);
            chmod($filePath, 0644);
            touch($filePath, $pageTime);
        }
    }
}