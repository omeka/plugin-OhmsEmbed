<?php

class OhmsEmbedPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array('initialize', 'install', 'uninstall', 'config_form', 'config', 'before_save_file');
    protected $_options = array(
        'ohms_embed_extract_metadata' => true,
        'ohms_embed_height' => 800,
    );

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

    public function hookInstall()
    {
        $this->_installOptions();
    }

    public function hookUninstall()
    {
        $this->_uninstallOptions();
    }

    public function hookConfigForm()
    {
        require dirname(__FILE__) . '/config_form.php';
    }

    public function hookConfig($args)
    {
        $post = $args['post'];

        set_option('ohms_embed_extract_metadata', (bool) $post['ohms_embed_extract_metadata']);
        set_option('ohms_embed_height', (int) $post['ohms_embed_height']);
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
        if (!get_option('ohms_embed_extract_metadata')) {
            return;
        }

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
            array(
                'element_set' => 'Item Type Metadata',
                'element' => 'Interviewer',
                'xpath' => 'interviewer',
                'multiple' => true,
            ),
            array(
                'element_set' => 'Item Type Metadata',
                'element' => 'Interviewee',
                'xpath' => 'interviewee',
                'multiple' => true,
            ),
            array(
                'element_set' => 'Item Type Metadata',
                'element' => 'Duration',
                'xpath' => 'duration',
                'multiple' => true,
            ),
        );

        $item = $file->getItem();
        $allElements = $item->getAllElements();
        $textsToAdd = array();
        foreach ($xpaths as $xpathSpec) {
            $xpathQuery = $xpathSpec['xpath'];
            $elementSet = $xpathSpec['element_set'];
            $element = $xpathSpec['element'];
            $multiple = $xpathSpec['multiple'];

            // Skip missing elements
            if (!isset($allElements[$elementSet][$element])) {
                continue;
            }

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

        $item = $file->getItem();
        $changedItem = false;
        if ($textsToAdd) {
            $item->addElementTextsByArray($textsToAdd);
            $changedItem = true;
        }

        if ($item->item_type_id === null) {
            $itemType = get_db()->getTable('ItemType')->findByName('Oral History');

            if ($itemType) {
                $item->item_type_id = $itemType->id;
                $changedItem = true;
            }
        }

        if ($changedItem) {
            $item->save();
        }
    }

    public static function embed($file, $options)
    {
        $viewer = web_path_to('javascripts/vendor/ohmsjs/ohms.html');
        $query['cachefile'] = $file->getWebPath('original');

        $attrs['src'] = $viewer . '?' . http_build_query($query);
        $height = (int) get_option('ohms_embed_height');
        if (!$height) {
            $height = 800;
        }
        $attrs['style'] = "width: 100%; height: {$height}px";
        $attrString = tag_attributes($attrs);

        return "<iframe {$attrString}></iframe>";
    }
}
