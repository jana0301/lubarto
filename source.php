<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

include 'RecursiveDOMIterator.php';
use PhpContentParser\RecursiveDOMIterator;
use GuzzleHttp\Client;

const DELIMITER = '<i>';
const DELIMITER_CLOSING = '</i>';
const BLUEPRINT_PLACEHOLDER_FORMAT = '[%d]';
const GOOGLE_TRANSLATE_API_KEY = 'AIzaSyBtjj5CX1whbkPNOiwPvxNSDFfC5_FWS2s';
const GOOGLE_TRANSLATE_ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

$dom = new DomDocument();

$htmlPath = 'sample_data/message1.html';

$dom->loadHTMLFile($htmlPath);

$elements = [];
$outputBlueprint = '';
$body = $dom->getElementsByTagName('body');

$domBlueprint = createOutputBlueprint($dom);
$translationStr = prepareTranslationString(getDOMElementsArray($dom, true));
$translated = translate($translationStr, 'hr', 'en');
$output = restoreTranslationContent($translated, $domBlueprint);
var_dump($output);

function translate($translationString, $source, $target) : string {
    $data = [
        "q"             => $translationString,
        "source"        => "en",
        "target"        => "hr",
        "format"        => "html"
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
    $translated = str_replace(DELIMITER_CLOSING, '', $responseJson->data->translations[0]->translatedText);
    return $translated;
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

    foreach($elements as $element) {
        if(isset($element->childs)) {
            foreach($element->childs as $childElement) {
                if ($childElement->nodeName == "#text") {
                    $translationArray[] = refineTextContent($childElement->textContent);
                }
            }
            $blockLevelIndex++;
        }
    }
    
    $translationStr = implode(DELIMITER, $translationArray);
    return $translationStr;
}

function refineTextContent($content) : string {
    return str_replace(DELIMITER, '\\' . DELIMITER, htmlspecialchars_decode($content));
}

function restoreTranslationContent($translationString, DOMDocument $blueprint) {
    $test = preg_split('~(?<!\\\)' . preg_quote(DELIMITER, '~') . '~', $translationString);
    $output = $blueprint->saveHTML();
    $key = 0;
    foreach($test as $value) {
        // istestirati (dodano zbog slucaja gdje google translate sam dodaje uzastopne tagove/delimitere)
        if (!empty($value)) {
            $output = str_replace("[$key]", $value, $output);
            $key++;
        }
    }

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