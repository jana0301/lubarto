<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

include 'RecursiveDOMIterator.php';
use PhpContentParser\RecursiveDOMIterator;
use GuzzleHttp\Client;

const DELIMITER = '<b>';
const DELIMITER2 = '<i>';
const DELIMITER_CLOSING = '</b>';
const DELIMITER2_CLOSING = '</i>';
const BLUEPRINT_PLACEHOLDER_FORMAT = '[%d]';
const GOOGLE_TRANSLATE_API_KEY = 'AIzaSyBtjj5CX1whbkPNOiwPvxNSDFfC5_FWS2s';
const GOOGLE_TRANSLATE_ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

$dom = new DomDocument();

$htmlPath = 'sample_data/message3.html';

$dom->loadHTMLFile($htmlPath);

$elements = [];
$outputBlueprint = '';
$body = $dom->getElementsByTagName('body');

$domBlueprint = createOutputBlueprint($dom);
$translationStr = prepareTranslationString(getDOMElementsArray($dom, true));
$translated = translate($translationStr, 'hr', 'en');
$output = restoreTranslationContent($translated, $domBlueprint);
//print_r($domBlueprint);
/*printRawHTML($translationStr);
printRawHTML($translated);
printRawHTML($output);*/
echo $output;

function printRawHTML($html) {
    print_r("<pre>" . htmlentities(print_r($html, true)) . "</pre>");
}

function translate($translationString, $source, $target) : string {
    //print_r($translationString);
    $data = [
        "q"             => $translationString,
        "source"        => "en",
        "target"        => "es",
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
    //var_dump($responseJson);
    $translated = str_replace(DELIMITER_CLOSING, '', str_replace(DELIMITER2_CLOSING, '', $responseJson->data->translations[0]->translatedText));
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
    $translationStr = '';

    foreach($elements as $element) {
        if(isset($element->childs)) {
            foreach($element->childs as $childKey => $childElement) {
                if ($childElement->nodeName == "#text") {
                    $content = refineTextContent($childElement->textContent);
                    $translationArray[$blockLevelIndex] = $content;

                    if ($blockLevelIndex % 2 == 0) $translationStr .= $content . DELIMITER;
                    else $translationStr .= $content. DELIMITER2;

                    $blockLevelIndex++;
                }
            }
        }
    }

    //print_r($translationArray);
    
    //$translationStr = implode(DELIMITER, $translationArray);
    //print_r($translationStr.PHP_EOL);
    return $translationStr;
}

function refineTextContent($content) : string {
    return str_replace(DELIMITER, '\\' . DELIMITER, htmlspecialchars_decode($content));
}

function restoreTranslationContent($translationString, DOMDocument $blueprint) {
    //print_r($translationString);
    $test = explode(DELIMITER, $translationString);
    //print_r($test);
    $sameDividerCounter = 0;
    $prevKey = null;
    foreach($test as $key => $value) {
        if (strpos($value, DELIMITER2) === false) {
            $sameDividerCounter++;
            if ($sameDividerCounter > 1) {
                $test[$prevKey] .= $value;
                unset($test[$key]);
            }
        }
        else {
            $sameDividerCounter = 0;
        }
        if ($prevKey != null && isset($test[$prevKey])) {
            $startingSubstr = substr($value, 0, strlen(DELIMITER2));
            $prevEndingSubstr = substr($test[$prevKey], 0 - strlen(DELIMITER2));
            if ($prevEndingSubstr) {
                if ($startingSubstr === DELIMITER2 && 
                    $prevEndingSubstr === DELIMITER2
                ) {
                    $value = substr($value, strlen(DELIMITER2));
                    $test[$key] = $value;
                }
            }
        }
        $prevKey = $key;
    }
    //print_r($test);

    $outputArray = [];
    foreach($test as $value) {
        $exploded = explode(DELIMITER2, $value);
        foreach($exploded as $explodedItem) {
            $outputArray[] = $explodedItem;
        }
    }
    //print_r($outputArray);

    //$test = preg_split('~(?<!\\\)' . preg_quote(DELIMITER, '~') . '~', $translationString);
    $output = $blueprint->saveHTML();
    $splitValues = [];
    $replaceValues = [];
    $key = 0;
    foreach($outputArray as $value) {
        $splitValues[] = $value;
        // istestirati (dodano zbog slucaja gdje google translate sam dodaje uzastopne tagove/delimitere)
        //if (!empty($value)) {
            $output = str_replace("[$key]", $value, $output);
            $replaceValues[] = $value;
            $key++;
        //}
    }
    //print_r($translationString);
    //print_r($outputArray);
    /*print_r($splitValues);
    print_r($replaceValues);*/

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