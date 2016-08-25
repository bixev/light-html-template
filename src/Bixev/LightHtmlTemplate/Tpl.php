<?php
namespace Bixev\LightHtmlTemplate;

class Tpl
{

    use \Bixev\LightLogger\LoggerTrait;

    /**
     * @var array path to the html templates
     */
    protected $_directories = [];

    /**
     * @var bool if set to true, no exception will be thrown when trying to parse a non-existing bloc
     */
    protected $_ignoreNotFoundBlocs = false;

    protected $_isInit = false;
    protected $_fromFile;
    protected $_fromString;

    /**
     * @var string
     */
    protected $extension = '.html';

    /**
     * @var string
     */
    protected $bloc_limit = '.';

    /**
     * Bloc pattern
     *
     * first parenthesis catches the bloc name
     * second parenthesis catches the bloc content
     * eg : {{ bloc : test }} block content {{ endbloc : test }}
     * @var string
     */
    protected $bloc_pattern = '#
                               {{
                                   \s*
                                   bloc\s*:\s*([a-zA-Z]+[a-zA-Z0-9_\-]*)
                                   \s*
                               }}
                               (.*)
                               {{
                                   \s*
                                   endbloc\s*:\s*\1
                               \s*
                               }}
                               #ixsm';

    /**
     * import pattern
     *
     * first parenthesis catches the callback function name
     * eg : {{ import : test/test }}
     * @var string
     */
    protected $import_pattern = '#
                                  {{
                                      \s*
                                      import\s*\:\s*([a-zA-Z]+[a-zA-Z0-9_\-\/]*)
                                      \s*
                                  }}
                                 #ixsm';

    /**
     * var pattern
     *
     * first parenthesis catches the var name
     * third parenthesis catches the callback function name
     * eg : {{ toto }}, {{ toto | function }}
     * @var string
     */
    protected $var_pattern = '#
                              {{
                                  \s*
                                  ([a-zA-Z]+[a-zA-Z0-9_/\[\]\-]*)
                                  (\s*\|\s*([a-zA-Z0-9_]+))?
                                  \s*
                              }}
                              #ixsm';

    /**
     * @var array local cache
     */
    private $blocs = [];

    protected $_functions = [];

    /**
     * @param string $file
     * @param string $string
     * @param \Bixev\LightLogger\LoggerInterface $logger
     */
    function __construct($file = null, $string = null, \Bixev\LightLogger\LoggerInterface $logger = null)
    {
        $this->setLogger($logger);
        if ($file !== null) {
            $this->_fromFile = $file;
        } elseif ($string !== null) {
            $this->_fromString = $string;
        }

        $this->_functions['default'] = function ($value, $valueGiven) {
            return $valueGiven ? '' : $value;
        };
    }

    protected function init()
    {
        if ($this->_isInit) {
            return;
        }
        $this->blocs = [];
        // defining master template
        if ($this->_fromFile !== null) {
            $this->initFromFile($this->bloc_limit, $this->_fromFile);
        } elseif ($this->_fromString !== null) {
            $this->initFromString($this->bloc_limit, $this->_fromString);
        }
        $this->_isInit = true;
    }

    public function setDirectories(array $directories)
    {
        $this->_directories = $directories;
    }

    public function setIgnoreNotFoundBlocs($ignore)
    {
        $this->_ignoreNotFoundBlocs = $ignore == true;
    }

    public function setVarFunctions(array $functions)
    {
        $this->_functions = $functions;
    }

    /**
     * @param string $path
     * @return string : absolute path
     */
    private function getPath($path)
    {
        if (strpos($path, DIRECTORY_SEPARATOR) === 0) {
            $filePath = $path;
            if (substr($path, -5, 5) != '.html') {
                $filePath .= $this->extension;
            }
            if (file_exists($filePath)) {
                return $filePath;
            }
        }
        foreach ($this->_directories as $directory) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $path;
            if (substr($filePath, -5, 5) != '.html') {
                $filePath .= $this->extension;
            }
            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        throw new Exception("File does not exist : " . $path);
    }

    /**
     * @param $path
     * @return string
     * @throws Exception
     */
    protected function getPathContent($path)
    {
        $path = $this->getPath($path);
        $content = file_get_contents($path);
        if ($content === false) {
            throw new Exception("File is not readable : " . $path);
        }

        return $content;
    }

    /**
     * initialize a bloc
     *
     * @param string $bloc_path : complete position of bloc parent.child
     * @return array $return
     */
    private function createBloc($bloc_path)
    {
        // master
        if ($bloc_path == $this->bloc_limit) {
            $prof = 0;
        } // level
        else {
            $prof = count(explode($this->bloc_limit, $bloc_path));
        }
        // initialize content
        $this->blocs[$bloc_path] = [
            'init' => 1,
            'prof' => $prof,
            'tpl'  => '',
            'html' => '',
        ];
    }

    /**
     * gets bloc name from its position
     *
     * @param string $bloc_path : complete position of bloc parent.child
     * @return string bloc name
     */
    private function getBlocNameFromPath($bloc_path)
    {
        // if master or level #1
        if ($bloc_path == $this->bloc_limit || strpos($bloc_path, $this->bloc_limit) === false) {
            return $bloc_path;
        } else {
            // else explode on delimiter
            $_tmp = explode($this->bloc_limit, $bloc_path);

            // return last item
            return array_pop($_tmp);
        }
    }

    /**
     * returns parent bloc name from child bloc position
     *
     * @param string $bloc_path : complete position of bloc parent.parent.child
     * @return string : complete position of bloc parent.parent
     */
    private function getParentBlocPath($bloc_path)
    {
        if ($bloc_path == $this->bloc_limit) {
            // bloc is master
            return false;
        } elseif (strpos($bloc_path, $this->bloc_limit) === false) {
            // parent is master
            return $this->bloc_limit;
        } else {
            // else get level up
            $_tmp = explode($this->bloc_limit, $bloc_path);
            array_pop($_tmp);
            $parent_path = implode($this->bloc_limit, $_tmp);

            return $parent_path;
        }
    }

    /**
     * @param string $bloc_name
     * @param string $tpl
     * @return bool
     */
    private function isBlocInTpl($bloc_name, $tpl)
    {
        preg_match_all($this->bloc_pattern, $tpl, $matches);

        return in_array($bloc_name, $matches[1]);
    }

    /**
     * @param $bloc_name
     * @param $tpl
     * @return mixed
     * @throws Exception
     */
    private function getBlocFromTpl($bloc_name, $tpl)
    {
        preg_match_all($this->bloc_pattern, $tpl, $matches);

        $content = null;
        for ($i = 0; $i < count($matches[0]); $i++) {
            if ($matches[1][$i] == $bloc_name) {
                if ($content !== null) {
                    throw new Exception('Multiple blocs found with same name : "' . $bloc_name . '"');
                }
                $content = $matches[2][$i];
            }
        }
        if ($content === null) {
            throw new Exception('Bloc not found : "' . $bloc_name . '"');
        }

        return $content;
    }

    /**
     * @param $tpl
     * @param string $bloc_path
     * @return mixed|string
     * @throws Exception
     */
    private function stripBlocs($tpl)
    {
        return $this->pregReplace($this->bloc_pattern, '', $tpl);
    }

    /**
     * @param $tpl
     * @return mixed
     */
    private function processImports($tpl)
    {
        $out = $this->getPregmatchAll($this->import_pattern, $tpl);
        for ($i = 0; $i < count($out[0]); $i++) {
            $imported = $this->getPathContent($out[1][$i]);
            $importedReplaced = $this->processImports($imported);
            $tpl = str_replace($out[0][$i], $importedReplaced, $tpl);
        }

        return $tpl;
    }

    /**
     * @param $tpl
     * @return mixed
     */
    private function stripVars($tpl)
    {
        return $this->pregReplace($this->var_pattern, '', $tpl);
    }

    /**
     * @param $tpl
     * @param bool $vars
     * @return mixed|string
     * @throws Exception
     */
    private function parseBloc($tpl, $vars = false)
    {
        if ($vars === false) {
            $new_content = $tpl;
        } elseif (is_string($vars)) {
            $new_content = $vars;
        } elseif (is_array($vars)) {
            $new_content = $tpl;
            foreach ($vars as $name => $value) {
                if (!is_array($value)) {
                    preg_match_all($this->var_pattern, $tpl, $matches, PREG_PATTERN_ORDER);
                    for ($i = 0; $i < count($matches[0]); $i++) {
                        if ($matches[1][$i] == $name) {
                            $value = $this->getVarValue($value, $matches[3][$i]);
                            $new_content = str_replace($matches[0][$i], $value, $new_content);
                        }
                    }
                }
            }
        } else {
            throw new Exception('incorrect vars to parse : ' . print_r($vars, true));
        }

        return $new_content;
    }

    protected function getVarValue($value, $function = null, $valueGiven = false)
    {
        if (empty($function)) {
            $function = 'default';
        }

        if (isset($this->_functions[$function])) {
            return $this->_functions[$function]($value, $valueGiven);
        }
        if (isset($this->_functions['default'])) {
            return $this->_functions['default']($value, $valueGiven);
        }

        return $value;
    }

    /**
     * @param $parent
     * @param $child
     * @return bool
     */
    private function isChildOf($parent, $child)
    {
        if ($parent == $child) {
            return false;
        }
        if ($parent == $this->bloc_limit) {
            return true;
        }
        if ($parent != $child && strpos($child, $parent) === 0) {
            return true;
        } else {
            return false;
        }
    }

    protected function initBloc($bloc_path)
    {
        if (isset($this->blocs[$bloc_path])) {
            $this->blocs[$bloc_path]['init'] = 1;
        } else {
            $this->createBloc($bloc_path);
        }

        $bloc_name = $parent_tpl = '';
        if ($bloc_path != $this->bloc_limit) {
            // if not in master
            $parent_bloc_path = $this->getParentBlocPath($bloc_path);
            if (!isset($this->blocs[$parent_bloc_path])) {
                throw new Exception("Parent bloc does not exist : " . $parent_bloc_path);
            } elseif (!isset($this->blocs[$parent_bloc_path]['init']) || $this->blocs[$parent_bloc_path]['init'] == 0) {
                throw new Exception("Parent bloc is not initialized : " . $parent_bloc_path);
            } else {
                $parent_tpl = $this->blocs[$parent_bloc_path]['tpl'];
            }
            // get current bloc
            $bloc_name = $this->getBlocNameFromPath($bloc_path);
            if (!$this->isBlocInTpl($bloc_name, $parent_tpl)) {
                throw new Exception(
                    "Bloc not found : '" . $bloc_name . "' in parent bloc : '" . $parent_bloc_path . "'"
                );
            }
        }

        return [$bloc_name, $parent_tpl];
    }

    protected function initFromFile($bloc_path, $filePath)
    {
        $this->initBloc($bloc_path);

        $tplContent = $this->getPathContent($filePath);
        // empty child blocs
        foreach ($this->blocs as $C => $V) {
            if ($this->isChildOf($bloc_path, $C)) {
                unset($this->blocs[$C]);
            }
        }

        $tplContent = $this->processImports($tplContent);

        $this->blocs[$bloc_path]['tpl'] = $tplContent;
    }

    protected function initFromString($bloc_path, $tplContent)
    {
        $this->initBloc($bloc_path);

        // empty child blocs
        foreach ($this->blocs as $C => $V) {
            if ($this->isChildOf($bloc_path, $C)) {
                unset($this->blocs[$C]);
            }
        }

        $tplContent = $this->processImports($tplContent);

        $this->blocs[$bloc_path]['tpl'] = $tplContent;
    }

    /**
     * @param $bloc_path
     * @throws Exception
     */
    public function iB($bloc_path)
    {
        $this->init();
        $initParams = $this->initBloc($bloc_path);

        if ($bloc_path == $this->bloc_limit) {
            // if on master and with no blocs to load
            throw new Exception("No master template given");
        } else {
            $bloc_name = $initParams[0];
            $parent_tpl = $initParams[1];
            $tplContent = $this->getBlocFromTpl($bloc_name, $parent_tpl);
            $tplContent = $this->processImports($tplContent);
            $this->blocs[$bloc_path]['tpl'] = $tplContent;
        }
    }

    /**
     * @param string $bloc_path
     * @param bool $vars
     * @throws Exception
     */
    public function pB()
    {
        $this->init();

        $bloc_path = '';
        $vars = false;
        $args = func_get_args();
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (is_string($arg)) {
                $bloc_path = $arg;
            } elseif (is_array($arg)) {
                $vars = $arg;
            }
        }

        if (!is_string($bloc_path)) {
            throw new Exception('Given bloc_path is not a string !');
        }
        if (is_array($vars) && empty($vars)) {
            $this->doParse($bloc_path, []);
        } elseif (is_array($vars) && !$this->isAssociativeArray($vars)) {
            foreach ($vars as $vars1) {
                $this->pB($bloc_path, $vars1);
            }
        } else {
            $this->doParse($bloc_path, $vars);
        }
    }

    protected function isAssociativeArray(array $arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param string $bloc_path
     * @param bool $vars
     * @return bool
     * @throws Exception
     */
    protected function doParse($bloc_path = '', $vars = false)
    {
        // if empty => master
        $bloc_path = empty($bloc_path) ? $this->bloc_limit : $bloc_path;
        if (!isset($this->blocs[$bloc_path])) {
            throw new Exception("Bloc does not exist : " . $bloc_path);
        }
        $parent_bloc_path = $this->getParentBlocPath($bloc_path);
        if ($parent_bloc_path !== false) {
            if (!isset($this->blocs[$parent_bloc_path])) {
                throw new Exception("Parent bloc does not exist : " . $parent_bloc_path);
            } elseif ($this->blocs[$parent_bloc_path]['init'] == 0) {
                throw new Exception("Parent bloc is not initialized : " . $parent_bloc_path);
            }
        }
        if (is_array($vars)) {
            foreach ($vars as $k => $v) {
                if (is_array($v)) {
                    $subPath = $bloc_path != '.' ? $bloc_path . $this->bloc_limit . $k : $k;
                    try {
                        if (!empty($v)) {
                            $this->iB($subPath);
                            $this->pB($subPath, $v);
                        } else {
                            $this->iB($subPath);
                            $this->pB($subPath);
                        }
                    } catch (\Exception $e) {
                        if (!$this->_ignoreNotFoundBlocs) {
                            throw $e;
                        }
                    }
                    unset($vars[$k]);
                }
            }
        }
        $bloc = $this->blocs[$bloc_path];
        $html = $bloc['tpl'];
        // process children
        foreach ($this->blocs as $child_path => $bloc_content) {
            if ($this->isChildOf(
                    $bloc_path,
                    $child_path
                ) && $this->blocs[$child_path]['prof'] == $this->blocs[$bloc_path]['prof'] + 1
            ) {
                $child_bloc_name = $this->getBlocNameFromPath($child_path);
                if (intval($this->blocs[$child_path]['init']) != 2) {
                    $this->log(
                        "Child bloc initialized but not parsed : " . $child_bloc_name . " in parent bloc " . $parent_bloc_path
                    );
                }
                $out = $this->getPregmatch($this->bloc_pattern, $html);
                $index = array_search($child_bloc_name, $out[1]);
                if ($index === false) {
                    $this->log(
                        "Child bloc '" . $child_bloc_name . "' not found, cannot be included into '" . $bloc_path . "'"
                    );
                } else {
                    $html_child = $this->render($child_path);
                    $html = str_replace($out[0][$index], $html_child, $html);
                }
                $this->blocs[$child_path]['html'] = '';
            }
        }
        $html = $this->parseBloc($html, $vars);

        // strips remaining blocs/vars not used
        $html = $this->stripBlocs($html);
        $html = $this->stripVars($html);

        if (!isset($this->blocs[$bloc_path]['html'])) {
            $this->blocs[$bloc_path]['html'] = '';
        }
        $this->blocs[$bloc_path]['html'] .= $html;
        $this->blocs[$bloc_path]['init'] = 2;

        return true;
    }

    /**
     * initialize AND parse a bloc in one operation
     *
     * @param $bloc_path
     * @param bool $vars
     * @throws Exception
     */
    public function B($bloc_path, $vars = false)
    {
        $this->iB($bloc_path);
        $this->pB($bloc_path, $vars);
    }

    /**
     * empty bloc
     *
     * @param string $bloc_path
     * @return bool
     */
    public function rzB($bloc_path = '')
    {
        $bloc_path = ($bloc_path == '') ? $this->bloc_limit : $bloc_path;
        $this->blocs[$bloc_path]['html'] = '';

        return true;
    }

    /**
     * handle with pcre limitations into preg_match
     *
     * @param $pattern
     * @param $content
     * @return mixed
     * @throws Exception
     */
    protected function getPregmatch($pattern, $content)
    {
        $i = 0;
        preg_match_all($pattern, $content, $out);
        while (preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR) {
            ini_set('pcre.backtrack_limit', (int)ini_get('pcre.backtrack_limit') + 100000);
            preg_match_all($pattern, $content, $out);
            $i++;
            if ($i > 100) {
                throw new Exception("pcre.backtrack_limit increased to " . ((int)ini_get('pcre.backtrack_limit')));
            }
        }

        return $out;
    }

    /**
     * handle with pcre limitations into preg_match_all
     *
     * @param $pattern
     * @param $content
     * @return mixed
     * @throws Exception
     */
    protected function getPregmatchAll($pattern, $content)
    {
        $i = 0;
        preg_match_all($pattern, $content, $out);
        while (preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR) {
            ini_set('pcre.backtrack_limit', (int)ini_get('pcre.backtrack_limit') + 100000);
            preg_match_all($pattern, $content, $out);
            $i++;
            if ($i > 100) {
                throw new Exception("pcre.backtrack_limit increased to " . ((int)ini_get('pcre.backtrack_limit')));
            }
        }

        return $out;
    }

    /**
     * handle with pcre limitations into preg_replace
     *
     * @param $pattern
     * @param $replacement
     * @param $content
     * @param int $count
     * @return mixed
     * @throws Exception
     */
    protected function pregReplace($pattern, $replacement, $content, &$count = 0)
    {
        $content1 = preg_replace($pattern, $replacement, $content, -1, $count);
        $i = 0;
        while (preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR) {
            ini_set('pcre.backtrack_limit', (int)ini_get('pcre.backtrack_limit') + 100000);
            $i++;
            $content1 = preg_replace($pattern, $replacement, $content);
            if ($i > 100) {
                throw new Exception("pcre.backtrack_limit increased to " . ((int)ini_get('pcre.backtrack_limit')));
            }
        }

        return $content1;
    }

    /**
     * @param string $bloc_path
     * @return string
     * @throws Exception
     */
    public function render($bloc_path = '')
    {
        $bloc_path = ($bloc_path == '') ? $this->bloc_limit : $bloc_path;
        if (!isset($this->blocs[$bloc_path])) {
            throw new Exception("Bloc does not exist : " . $bloc_path);
        }
        if (!isset($this->blocs[$bloc_path]['init'])) {
            throw new Exception("Bloc has not been initialized : " . $bloc_path);
        }
        if ($this->blocs[$bloc_path]['init'] != 2) {
            throw new Exception("Bloc has not been parsed : " . $bloc_path);
        }
        $html = $this->blocs[$bloc_path]['html'];

        return $html;
    }
}
