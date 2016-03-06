<?php
namespace Bixev\LightHtmlTemplate;

class Factory
{
    static public function newTemplateFromString($string)
    {
        $tpl = new Tpl(null, $string);

        return $tpl;
    }

    static public function newTemplateFromFile($path)
    {
        $tpl = new Tpl($path);

        return $tpl;
    }
}