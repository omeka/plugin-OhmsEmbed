<?php

class OhmsEmbedPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array('initialize');

    public function hookInitialize()
    {
        add_file_display_callback(
            array(
                'mimeTypes' => array('application/xml', 'text/xml'),
                'fileExtensions' => array('xml'),
            ),
            'OhmsEmbedPlugin::embed'
        );
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
