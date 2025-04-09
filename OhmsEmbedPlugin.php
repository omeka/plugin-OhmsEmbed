<?php

class OhmsEmbedPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array('initialize', 'before_save_file');

    protected $_ohmsMimeTypes = array('application/xml', 'text/xml');
    protected $_ohmsExtensions = array('xml');

    public function hookInitialize()
    {
        add_file_display_callback(
            array(
                'mimeTypes' => $this->_ohmsMimeTypes,
                'fileExtensions' => $this->_ohmsExtensions,
            ),
            'OhmsEmbedPlugin::embed'
        );
    }

    public function hookBeforeSaveFile($args)
    {
        if (!$args['insert']) {
            return;
        }
        $file = $args['record'];
        if (!(in_array($file->mime_type, $this->_ohmsMimeTypes))) {
            return;
        }

        $this->_extractMetadata($file);
    }

    protected function _extractMetadata($file)
    {
        $doc = new DomDocument;
        $doc->load($file->getPath());

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('o', 'https://www.weareavp.com/nunncenter/ohms');


        $namespaced = true;
        $recordQuery = $xpath->query('//o:ROOT/o:record');
        if (!$recordQuery->count()) {
            $recordQuery = $xpath->query('//ROOT/record');
            if (!$recordQuery->count()) {
                // couldn't find root record element; can't extract anything
                return;
            }
            $namespaced = false;
        }
        $record = $recordQuery->item(0);

        $xpaths = array(
            array(
                'element_set' => 'Dublin Core',
                'element' => 'Title',
                'xpath' => 'title',
                'multiple' => false,
            ),
            array(
                'element_set' => 'Dublin Core',
                'element' => 'Description',
                'xpath' => 'description',
                'multiple' => false,
            ),
            array(
                'element_set' => 'Dublin Core',
                'element' => 'Identifier',
                'xpath' => 'accession',
                'multiple' => true,
            ),
            array(
                'element_set' => 'Dublin Core',
                'element' => 'Date',
                'xpath' => 'date/@value',
                'multiple' => true,
            ),
            array(
                'element_set' => 'Dublin Core',
                'element' => 'Subject',
                'xpath' => 'subject',
                'multiple' => true,
            ),
        );
        $textsToAdd = array();
        foreach ($xpaths as $xpathSpec) {
            $xpathQuery = $xpathSpec['xpath'];
            $elementSet = $xpathSpec['element_set'];
            $element = $xpathSpec['element'];
            $multiple = $xpathSpec['multiple'];

            if ($namespaced) {
                $xpathQuery = "o:$xpathQuery";
            }
            if ($multiple) {
                $result = $xpath->query($xpathQuery, $record);
                foreach ($result as $matchedElement) {
                    $text = $matchedElement->textContent;
                    if ($text === '') {
                        continue;
                    }
                    $textsToAdd[$elementSet][$element][] = array('text' => $text, 'html' => false);
                }
            } else {
                $result = $xpath->evaluate("string($xpathQuery)", $record);
                if (!is_string($result) || $result === '') {
                    continue;
                }
                $textsToAdd[$elementSet][$element][] = array('text' => $result, 'html' => false);
            }
        }
        if ($textsToAdd) {
            $file->addElementTextsByArray($textsToAdd);
        }
    }

    public static function embed($file, $options)
    {
        $viewer = web_path_to('javascripts/vendor/ohmsjs/ohms.html');
        $query['cachefile'] = $file->getWebPath('original');

        $attrs['src'] = $viewer . '?' . http_build_query($query);
        $attrs['style'] = 'width: 100%; height: 800px';
        $attrString = tag_attributes($attrs);

        return "<iframe {$attrString}></iframe>";
    }
}
