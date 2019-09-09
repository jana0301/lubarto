<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

include 'RecursiveDOMIterator.php';
use PhpContentParser\RecursiveDOMIterator;
use GuzzleHttp\Client;

const DELIMITER = '<b>';
const DELIMITER_CLOSING = '</b>';
const BLUEPRINT_PLACEHOLDER_FORMAT = '[%d]';
const GOOGLE_TRANSLATE_API_KEY = 'AIzaSyBtjj5CX1whbkPNOiwPvxNSDFfC5_FWS2s';
const GOOGLE_TRANSLATE_ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

$dom = new DomDocument();



if (isset($_POST["d"])) {
    $data = $_POST["d"];
    $dom->loadHTML($data);
    $domBlueprint = createOutputBlueprint($dom);
    $translationStr = prepareTranslationString(getDOMElementsArray($dom, true));
    $translated = translate($translationStr, 'hr', 'en');
    $output = restoreTranslationContent($translated, $domBlueprint);
    echo $output;
    die();
}

$htmlPath = 'sample_data/message2_errors.html';

$dom->loadHTMLFile($htmlPath);

$elements = [];
$outputBlueprint = '';
$body = $dom->getElementsByTagName('body');

$domBlueprint = createOutputBlueprint($dom);
$translationStr = prepareTranslationString(getDOMElementsArray($dom, true));
$translated = translate($translationStr, 'hr', 'en');
$output = restoreTranslationContent($translated, $domBlueprint);

echo "ORIGINAL<hr>";
echo $body->item(0)->ownerDocument->saveHTML();
echo "TRANSLATED<hr>";
echo $output;

function printRawHTML($html) {
    print_r("<pre>" . htmlentities(print_r($html, true)) . "</pre>");
}

function translate($translationString, $source, $target) : string {
    //print_r($translationString . PHP_EOL);
    $data = [
        "q"             => $translationString,
        "source"        => "en",
        "target"        => "es",
        "format"        => "html",
        "model"         => "nmt"
    ];
    $httpClient = new Client();
    $headers = [
        'Content-Type'  => 'application/json'
    ];
    $response = $httpClient->request('POST', GOOGLE_TRANSLATE_ENDPOINT, [
        'query'         => ['key' => GOOGLE_TRANSLATE_API_KEY],
        'json'          => $data,
        'headers'       => $headers
    ]);
    
    $responseJson = json_decode($response->getBody());
    //print_r(PHP_EOL . $responseJson->data->translations[0]->translatedText . PHP_EOL);
    $translatedText = $responseJson->data->translations[0]->translatedText;
    $clearedTranslatedText = substr($translatedText, 0, strrpos($translatedText, '|'));
    $reversed = strrev($clearedTranslatedText);
    //print_r(PHP_EOL . $reversed . PHP_EOL);
    $reversedFixedHTML = '';
    for ($i = 0; $i < strlen($reversed); $i++) {
        $character = $reversed[$i];
        if ($character == '<') $character = '>';
        else if ($character == '>') $character = '<';
        $reversedFixedHTML .= $character;
    }
    $reversedFixedHTML = str_replace('<b/>', '</b>', str_replace('<i/>', '</i>', $reversedFixedHTML));
    //print_r(PHP_EOL . 'reversedFixed: ' . $reversedFixedHTML . PHP_EOL);
    //print_r(PHP_EOL . strrev((strrev($responseJson->data->translations[0]->translatedText))) . PHP_EOL);
    $translated = preg_replace_callback('/<\/b\b[^<]*>((?:(?!<\/?b\b).)+|(?R))*<b>\s*/', function($matches) {
        return strip_tags($matches[0]);
    }, $reversedFixedHTML, -1);
    //print_r('translated:' . $translated);

    $translatedReverseRestored = strrev($translated);
    $translatedFixedReverseRestored = '';
    for ($i = 0; $i < strlen($translatedReverseRestored); $i++) {
        $character = $translatedReverseRestored[$i];
        if ($character == '<') $character = '>';
        else if ($character == '>') $character = '<';
        $translatedFixedReverseRestored .= $character;
    }

    //print_r(PHP_EOL . $translatedFixedReverseRestored);
    return $translatedFixedReverseRestored;
}

function createOutputBlueprint(DOMDocument $dom) : DOMDocument {
    $domClone = $dom->cloneNode(true);
    $bodyClone = $domClone->getElementsByTagName('body');
    $domBlueprint = new DOMDocument();
    $placeholderIndex = 0;
    $placeholderFormat = BLUEPRINT_PLACEHOLDER_FORMAT;
    
    if ($bodyClone && $bodyClone->length > 0) {
        foreach($bodyClone[0]->childNodes as $key => $element) {
            if ($element->hasChildNodes()) {
                $domIterator = new RecursiveIteratorIterator(
                    new RecursiveDOMIterator($element), RecursiveIteratorIterator::SELF_FIRST
                );
                foreach($domIterator as $iteratorNode) {
                    if ($iteratorNode->nodeName == '#text') {
                        $iteratorNode->nodeValue = sprintf($placeholderFormat, $placeholderIndex);
                        $placeholderIndex++;
                    }
                }
            }
        }
        foreach($bodyClone[0]->childNodes as $key => $element) {
            $domBlueprint->appendChild($domBlueprint->importNode($element, true));
        }
    }

    return $domBlueprint;
}

function getDOMElementsArray(DOMDocument $dom, $extractBody = false) : array {
    if ($extractBody == true) $dom = $dom->getElementsByTagName('body')[0];
    $elements = [];
    foreach($dom->childNodes as $key => $element) {
        $elements[$key] = $element;
        if ($element->hasChildNodes()) {
            $domIterator = new RecursiveIteratorIterator(
                new RecursiveDOMIterator($element), RecursiveIteratorIterator::SELF_FIRST
            );
            foreach($domIterator as $iteratorNode) {
                $elements[$key]->childs[] = $iteratorNode;
            }
        }
    }

    return $elements;
}

function prepareTranslationString($elements) : string {
    $translationArray = [];
    $blockLevelIndex = 0;
    $translationStr = '';

    foreach($elements as $element) {
        if(isset($element->childs)) {
            $lastChildKey = key($element->childs);
            foreach($element->childs as $childKey => $childElement) {
                if ($childElement->nodeName == "#text") {
                    $content = refineTextContent($childElement->textContent);
                    $translationArray[$blockLevelIndex] = $content;

                    $translationStr .= $content . (($childKey == $lastChildKey) ? '|' : '') . DELIMITER;

                    $blockLevelIndex++;
                }
            }
        }
    }
    //print_r($translationArray);
    return $translationStr . '|';
}

function refineTextContent($content) : string {
    return str_replace(DELIMITER, '\\' . DELIMITER, htmlspecialchars_decode($content));
}

function restoreTranslationContent($translationString, DOMDocument $blueprint) {
    $translationArray = preg_split('~(?<!\\\)' . preg_quote(DELIMITER, '~') . '~', $translationString);
    print_r($translationArray);

    $outputArray = [];
    foreach($translationArray as $translationArrayItem) {
        $outputArray[] = rtrim($translationArrayItem, ' | ') . ' ';
    }
    
    $output = $blueprint->saveHTML();
    $key = 0;
    foreach($outputArray as $value) {
        $output = str_replace(DELIMITER_CLOSING, '', str_replace("[$key]", $value, $output));
        $key++;
    }

    print_r($blueprint->saveHTML());

    return $output;
}

function getElementAttributes(DOMNode $element) : array {
    $attributes = [];
    if ($element->attributes) {
        for($i = 0; $i < $element->attributes->length; $i++) {
            $item = $element->attributes->item($i);
            $attributes[] = [
                'name' => $item->name,
                'value' => $item->value
            ];
        }
    }
    return $attributes;
}

?>