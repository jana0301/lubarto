<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

include 'RecursiveDOMIterator.php';
use PhpContentParser\RecursiveDOMIterator;
use GuzzleHttp\Client;

const DELIMITER = '<b>';
const DELIMITER_CLOSING = '</b>';
const DELIMITER_CLOSING_REV = '<b/>';
const DELIMITER_END_OF_PARAGRAPH = '|';
const BLUEPRINT_PLACEHOLDER_FORMAT = '[%d]';
const GOOGLE_TRANSLATE_API_KEY = 'AIzaSyBtjj5CX1whbkPNOiwPvxNSDFfC5_FWS2s';
const GOOGLE_TRANSLATE_ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

$dom = new DomDocument();

if (isset($_POST["d"])) {
    $data = $_POST["d"];
    $targetLang = $_POST["target"];
    $dom->loadHTML($data);
    $rawTranslationInput = getRawTranslationInput($dom);
    $domBlueprint = createOutputBlueprint($dom);
    $translationElements = getDOMElementsArray($dom, true);
    $translationStr = prepareTranslationString($translationElements);
    $translated = translate($translationStr, 'en', $targetLang);
    $output = restoreTranslationContent($translated, $domBlueprint);
    $output['translation_input'] = $translationStr;
    $output['raw_translation_input'] = $rawTranslationInput;
    $output['raw_translation_input_text'] = strip_tags($rawTranslationInput);
    $output['stats'] = getStats($rawTranslationInput, $translationStr);
    header('Content-Type: application/json');
    echo json_encode($output);
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

/*echo "ORIGINAL<hr>";
echo $body->item(0)->ownerDocument->saveHTML();
echo "TRANSLATED<hr>";
print_r($output);*/

function printRawHTML($html) {
    print_r("<pre>" . htmlentities(print_r($html, true)) . "</pre>");
}

function getStats($rawInput, $translationInput) : array {
    $rawInputLength = strlen($rawInput);
    $rawInputNoHtmlLength = strlen(strip_tags($rawInput));
    $translationInputLength = strlen($translationInput);

    $rawInputTranslationInputDiff = round((($translationInputLength/$rawInputLength)*100)-100, 2);
    $rawInputNoHtmlTranslationInputDiff = round((($translationInputLength/$rawInputNoHtmlLength)*100)-100, 2);
    $rawInputNoHtmlRawInputDiff = round((($rawInputLength/$rawInputNoHtmlLength)*100)-100, 2);

    return [
        'raw_input_length'              => $rawInputLength,
        'raw_input_no_html_length'      => $rawInputNoHtmlLength,
        'translation_input_length'      => $translationInputLength,
        'raw_input_transl_diff'         => $rawInputTranslationInputDiff,
        'raw_input_no_html_transl_diff' => $rawInputNoHtmlTranslationInputDiff,
        'raw_input_no_html_raw_diff'    => $rawInputNoHtmlRawInputDiff
    ];
}

function translate($translationString, $source, $target) : string {
    //print_r($translationString . PHP_EOL);
    $data = [
        "q"             => $translationString,
        "source"        => $source,
        "target"        => $target,
        "format"        => "html",
        "model"         => "nmt"
    ];
    $httpClient = new Client(['verify' => false]);
    $headers = [
        'Content-Type'  => 'application/json'
    ];
    $response = $httpClient->request('POST', GOOGLE_TRANSLATE_ENDPOINT, [
        'query'         => ['key' => GOOGLE_TRANSLATE_API_KEY],
        'json'          => $data,
        'headers'       => $headers
    ]);
    
    $responseJson = json_decode($response->getBody());
    $translatedText = $responseJson->data->translations[0]->translatedText;
    
    return $translatedText;
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

function getRawTranslationInput(DOMDocument $dom) {
    $body = $dom;
    if ($dom->getElementsByTagName('body')->length != 0) {
        $body = $dom->getElementsByTagName('body')->item(0);
    }
    $html = '';
    foreach($body->childNodes as $node) {
        $html .= $dom->saveHTML($node);
    }
    return $html;
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

                    $translationStr .= $content . (($childKey == $lastChildKey) ? DELIMITER_END_OF_PARAGRAPH : '') . DELIMITER;

                    $blockLevelIndex++;
                }
            }
        }
    }
    //print_r($translationArray);
    return $translationStr . DELIMITER_END_OF_PARAGRAPH;
}

function refineTextContent($content) : string {
    return str_replace(DELIMITER, '\\' . DELIMITER, htmlspecialchars_decode($content));
}

function reverseTranslatedText($translatedText) {
    $translReversingReplacements = [
        '<' => '>',
        '>' => '<'
    ];

    $reverseTranslated = '';
    for($i = strlen($translatedText)-1 ; $i >= 0 ; $i--) {
        $reversedTxt = $translatedText[$i];
        if (array_key_exists($reversedTxt, $translReversingReplacements)) {
            $reversedTxt = $translReversingReplacements[$reversedTxt];
        }
        $reverseTranslated .= $reversedTxt;
    }
    $reverseTranslated = str_replace(DELIMITER_CLOSING_REV, DELIMITER_CLOSING, $reverseTranslated);

    return $reverseTranslated;
}

function restoreTranslationContent($translationString, DOMDocument $blueprint) : array {
    $clearedTranslatedText = substr($translationString, 0, strrpos($translationString, DELIMITER_END_OF_PARAGRAPH));

    $reverseTranslated = reverseTranslatedText($clearedTranslatedText);
    
    $translatedDelimitersFixed = preg_replace_callback('/<\/b\b[^<]*>((?:(?!<\/?b\b).)+|(?R))*<b>\s*/', function($matches) {
        return strip_tags($matches[0]);
    }, $reverseTranslated, -1);

    $translatedReverseRestored = reverseTranslatedText($translatedDelimitersFixed);

    $translationArray = preg_split('~(?<!\\\)' . preg_quote(DELIMITER, '~') . '~', $translatedReverseRestored);

    $outputArray = [];
    foreach($translationArray as $translationArrayItem) {
        $outputArray[] = rtrim($translationArrayItem, ' ' . DELIMITER_END_OF_PARAGRAPH . ' ') . ' ';
    }
    
    $output = $blueprint->saveHTML();
    $key = 0;
    foreach($outputArray as $value) {
        $output = str_replace(DELIMITER_CLOSING, '', str_replace("[$key]", $value, $output));
        $key++;
    }

    $blueprintData = $blueprint->saveHTML();

    $outputData = [
        'translation'               => $output,
        'raw_translation_output'    => $translationString,
        'blueprint_values'          => $outputArray,
        'blueprint'                 => $blueprintData
    ];

    return $outputData;
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