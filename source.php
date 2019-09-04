<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'RecursiveDOMIterator.php';
use PhpContentParser\RecursiveDOMIterator;

const DELIMITER = '<w>';

$dom = new DomDocument();

$htmlPath = 'sample_data/message1.html';

$dom->loadHTMLFile($htmlPath);

$elements = [];
$outputBlueprint = '';
$body = $dom->getElementsByTagName('body');
$xpath = new DOMXpath($dom);

/*foreach ($xpath->evaluate('//text()') as $node) {
    $node->nodeValue = "a";
}
var_dump($dom->saveHtml($dom->getElementsByTagName('body')[0]));
die();*/

$domClone = new DOMDocument();
$domClone = $dom->cloneNode(true);

/*if ($body && $body->length > 0) {
    foreach($body[0]->childNodes as $key => $element) {
        $domClone->appendChild($domClone->importNode($element, true));
    }
}*/

$bodyClone = $domClone->getElementsByTagName('body');

$testDoc = new DOMDocument();
$testI = 0;
if ($bodyClone && $bodyClone->length > 0) {
    foreach($bodyClone[0]->childNodes as $key => $element) {
        if ($element->hasChildNodes()) {
            $domIterator = new RecursiveIteratorIterator(
                new RecursiveDOMIterator($element), RecursiveIteratorIterator::SELF_FIRST
            );
            foreach($domIterator as $iteratorKey => $iteratorNode) {
                if ($iteratorNode->nodeName == '#text') {
                    $iteratorNode->nodeValue = $testI;
                    $testI++;
                }
            }

            //$testDoc->appendChild($testDoc->importNode($element, true));
        }
    }
    foreach($bodyClone[0]->childNodes as $key => $element) {
        $testDoc->appendChild($testDoc->importNode($element, true));
    }
}
print_r($testDoc->saveHTML());

/*$testDoc->appendChild($testDoc->importNode($body->item(0), true));
print_r($testDoc->getElementsByTagName('body')->item(0)->nodeValue);
die();*/


$testIndex = 0;
if ($body && $body->length > 0) {
    foreach($body[0]->childNodes as $key => $element) {
        if ($element->hasChildNodes()) {
            $domIterator = new RecursiveIteratorIterator(
                new RecursiveDOMIterator($element), RecursiveIteratorIterator::SELF_FIRST
            );
            foreach($domIterator as $iteratorKey => $iteratorNode) {
                $elements[$key]['child_nodes'][] = [
                    'name' => $iteratorNode->nodeName,
                    'attrs' => getElementAttributes($iteratorNode),
                    'content' => $iteratorNode->textContent
                ];
            }
        }

        $elements[$key]['name'] = $element->nodeName;
        $elements[$key]['attrs'] = getElementAttributes($element);
        $elements[$key]['child_nodes_count'] = ($element->childNodes) ? count($element->childNodes) : 0;
        $elements[$key]['content'] = $element->textContent;

        $element->nodeValue = $testIndex; 
        $testIndex++;
    }

    $markup = prepareTranslationMarkup($elements);
    $translationStr = prepareTranslationString($elements);
    var_dump($translationStr);
    print_r($elements);
    print_r($markup);
    $restored = restoreTranslationContent($translationStr, $markup);
}

function prepareTranslationComponents($elements) {
    $translationComponents = [
        'markup' => [],
        'content_parts' => [],
        'output_blueprint' => ''
    ];
    $blockLevelIndex = 0;

    foreach($elements as $element) {
        if(isset($element['child_nodes'])) {
            $translationComponents['markup'][$blockLevelIndex]['name'] = $element['name'];
            $translationComponents['markup'][$blockLevelIndex]['attrs'] = $element['attrs'];
            foreach($element['child_nodes'] as $childElement) {
                if ($childElement['name'] != "#text") {
                    $translationComponents['markup'][$blockLevelIndex]['child_nodes'][] = [
                        'name' => $childElement['name'],
                        'attrs' => $childElement['attrs']
                    ];
                }
                else {
                    $translationComponents['content_parts'][] = refineTextContent($childElement['content']);
                }
            }
            $blockLevelIndex++;
        }
    }

    return $translationComponents;
}

function prepareTranslationMarkup($elements) {
    $markupArray = prepareTranslationComponents($elements)['markup'];
    
    return $markupArray;
}

function prepareTranslationString($elements) {
    $translationArray = prepareTranslationComponents($elements)['content_parts'];
    print_r($translationArray);
    
    $translationStr = implode(DELIMITER, $translationArray);
    return $translationStr;
}

function refineTextContent($content) {
    return str_replace(DELIMITER, '\\' . DELIMITER, htmlspecialchars_decode($content));
}

function restoreTranslationContent($translationString, $markup) {
    $test = preg_split('~(?<!\\\)' . preg_quote(DELIMITER, '~') . '~', $translationString);
    print_r($test);
}

function getElementAttributes(DOMNode $element) {
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