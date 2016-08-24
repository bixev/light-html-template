<?php
namespace Bixev\LightHtmlTemplate;

class Factory
{

    static protected $_directories = [];
    static protected $_functions = [];

    static public function newTemplateFromString($string)
    {
        $tpl = new Tpl(null, $string);
        $tpl->setDirectories(static::$_directories);
        $tpl->setVarFunctions(static::$_functions);

        return $tpl;
    }

    static public function newTemplateFromFile($path)
    {
        $tpl = new Tpl($path);
        $tpl->setDirectories(static::$_directories);
        $tpl->setVarFunctions(static::$_functions);

        return $tpl;
    }

    static public function addDirectory($path, $top = false)
    {
        if ($top) {
            array_unshift(static::$_directories, $path);
        } else {
            static::$_directories[] = $path;
        }
    }

    static public function emptyDirectories()
    {
        static::$_directories = [];
    }

    static public function setVarFunction(string $label = 'default', callable $callable)
    {
        static::$_functions[$label] = $callable;
    }
}