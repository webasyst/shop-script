<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set("display_errors", 1);
include_once 'YandexTranslate.class.php';
//include_once 'Yandex_TranslateBigText.class.php';

$translator = new YandexTranslate();

//Ниже для экспериментов раскомментируйте нужное

//Массив языков, с которых можно переводить
echo '<pre>';
$pairs = $translator->getLangsPairs();
//print_r($pairs);
echo '</pre>';

//Массив языков, на которые можно переводить
echo '<pre>';
$to = $translator->getFromLangs();
//print_r($to);
echo '</pre>';


//Перевод

$text = file_get_contents('text.txt');

//Это повторение значения свойства по умолчанию - см. код класса
$translator->eolSymbol = '<br />';

$translatedText = $translator->translate('en', 'ru', $text);

echo $translatedText;


//Работа с большими текстами
/*
$bigText = file_get_contents('text_big.txt');
$textArray = YandexTranslateBigText::toBigPieces($bigText);

$numberOfTextItems = count($textArray);

foreach ($textArray as $key=>$textItem){

    //Показываем прогресс перевода
    echo 'Переведен фрагмент '.$key.' из '.$numberOfTextItems.'<br />';
    flush();

    $translatedItem = $translator->translate('en', 'ru', $textItem);
    $translatedArray[$key] = $translatedItem;
}

$translatedBigText = YandexTranslateBigText::fromBigPieces($translatedArray);

echo $translatedBigText;
*/
