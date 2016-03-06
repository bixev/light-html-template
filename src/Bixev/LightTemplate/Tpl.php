<?php
namespace Bixev\LightHtmlTemplate;

class Tpl
{

    /**
     * @var string path to the html templates
     */
    protected $tpl_location_dir;

    /**
     * @var string
     */
    protected $extension = '.html';

    /**
     * @var string
     */
    protected $bloc_limit = '.';

    /**
     * @var string
     */
    protected $bloc_pattern = "[a-zA-Z]+[a-zA-Z0-9_\-]*";

    /**
     * @var string
     */
    protected $var_pattern = "[a-zA-Z]+[a-zA-Z0-9_/\[\]\-]*";

    /**
     * @var string
     */
    protected $import_pattern = "#\[IMPORT\|([\/a-zA-z0-9_\-]+)\]#ixsm";


    /**
     * @var array local cache
     */
    private $blocs = [];

    /**
     * @param string $file
     * @param string $string
     */
    function __construct($file = null, $string = null)
    {
        // defining master template
        if ($file !== null) {
            $this->initFromFile($this->bloc_limit, $file);
        } elseif ($string !== null) {
            $this->initFromString($this->bloc_limit, $string);
        }
    }

    /**
     * @param string $path
     * @return string : absolute path
     */
    private function getPath($path)
    {
        $dir = $this->tpl_location_dir . '/' . $path;

        if (substr($dir, -5, 5) != '.html') {
            $dir .= $this->extension;
        }

        return $dir;
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
        $return = [
            'init' => true,
            'prof' => $prof,
            'tpl'  => '',
            'html' => '',
        ];

        return $return;
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
        $_tmp = [];
        $pattern = "#<bloc:" . $bloc_name . ">(.*)</bloc:" . $bloc_name . ">#ms";

        return preg_match($pattern, $tpl, $_tmp);
    }

    /**
     * @param $bloc_name
     * @param $tpl
     * @return mixed
     * @throws Exception
     */
    private function getBlocFromTpl($bloc_name, $tpl)
    {
        $_tmp = [];
        $pattern = "#<bloc:" . $bloc_name . ">(.*)</bloc:" . $bloc_name . ">#ms";
        preg_match_all($pattern, $tpl, $_tmp);

        $nb = count($_tmp[0]);

        if (!$nb) {
            throw new Exception('Bloc not found : "' . $bloc_name . '"');
        } elseif ($nb > 1) {
            throw new Exception('Multiple blocs found with same name : "' . $bloc_name . '"');
        } else {
            return $_tmp[1][0];
        }
    }

    /**
     * @param $tpl
     * @param string $bloc_path
     * @return mixed|string
     * @throws Exception
     */
    private function stripBlocs($tpl, $bloc_path = '')
    {
        // get all not parsed blocs
        preg_match_all("#<bloc:(" . $this->bloc_pattern . ")>#ixsm", $tpl, $todelete, PREG_PATTERN_ORDER);
        foreach ($todelete[1] as $v) {
            $pattern = '#\s*<bloc:' . $v . '>(.*)</bloc:' . $v . '>\s*#ixsm';
            $count = 0;
            $tpl1 = $this->pregReplace($pattern, " ", $tpl, $count);

            if ($count != 0) {
                $tpl = $tpl1;
                $this->log("Bloc deleted '" . $v . "'. Parent bloc: " . $bloc_path);
            }
        }

        return $tpl;
    }

    /**
     * @param $tpl
     * @param string $bloc_path
     * @return mixed
     */
    private function stripVars($tpl, $bloc_path = '')
    {
        preg_match_all("#{(" . $this->var_pattern . ")}#", $tpl, $todelete, PREG_PATTERN_ORDER);
        foreach ($todelete[0] as $v) {
            $this->log("var '" . $v . "' deleted. Parent bloc : " . $bloc_path);
        }
        $tpl = $this->pregReplace("#({(" . $this->var_pattern . ")})#", "", $tpl);

        return $tpl;
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
            foreach ($vars as $C => $V) {
                if (!is_array($V)) {
                    if (strpos($new_content, '{' . $C . '}') === false) {
                        $this->log("var not found {" . $C . "}");
                    }
                    $new_content = str_replace('{' . $C . '}', $V, $new_content);
                }
            }
        } else {
            throw new Exception('incorrect vars to parse : ' . print_r($vars, true));
        }

        return $new_content;
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
            } elseif ($this->blocs[$parent_bloc_path]['init'] == 0) {
                throw new Exception("Parent bloc is not initialized : " . $parent_bloc_path);
            } else {
                $parent_tpl = $this->blocs[$parent_bloc_path]['tpl'];
            }
            // get current bloc
            $bloc_name = $this->getBlocNameFromPath($bloc_path);
            if (!$this->isBlocInTpl($bloc_name, $parent_tpl)) {
                throw new Exception("Bloc not found : '" . $bloc_name . "' in parent bloc : '" . $parent_bloc_path . "'");
            }
        }

        return [$bloc_name, $parent_tpl];
    }

    public function initFromFile($bloc_path, $filePath)
    {
        $this->initBloc($bloc_path);

        $path = $this->getPath($filePath);
        if (!is_file($path) || ($_tmp = file_get_contents($path)) === false) {
            throw new Exception("File does not exist or is not readable : " . $path);
        } else {
            $bloc_tpl = $_tmp;
            // empty child blocs
            foreach ($this->blocs as $C => $V) {
                if ($this->isChildOf($bloc_path, $C)) {
                    unset($this->blocs[$C]);
                }
            }
        }

        // imports ?
        $bloc_tpl = $this->replaceImportedTemplate($bloc_tpl);
        $bloc['tpl'] = $bloc_tpl;
        $this->blocs[$bloc_path] = $bloc;
    }

    public function initFromString($bloc_path, $content)
    {
        $this->initBloc($bloc_path);

        $bloc_tpl = $content;
        // empty child blocs
        foreach ($this->blocs as $C => $V) {
            if ($this->isChildOf($bloc_path, $C)) {
                unset($this->blocs[$C]);
            }
        }

        // imports ?
        $bloc_tpl = $this->replaceImportedTemplate($bloc_tpl);
        $bloc['tpl'] = $bloc_tpl;
        $this->blocs[$bloc_path] = $bloc;
    }

    /**
     * @param $bloc_path
     * @param bool $from_file
     * @param null $fromString
     * @throws Exception
     */
    public function iB($bloc_path)
    {
        $initParams = $this->initBloc($bloc_path);

        if ($bloc_path == $this->bloc_limit) {
            // if on master and with no blocs to load
            throw new Exception("No master template given");
        } else {
            $bloc_name = $initParams[0];
            $parent_tpl = $initParams[1];
            $bloc_tpl = $this->getBlocFromTpl($bloc_name, $parent_tpl);
            // imports ?
            $bloc_tpl = $this->replaceImportedTemplate($bloc_tpl);
            $bloc['tpl'] = $bloc_tpl;
            $this->blocs[$bloc_path] = $bloc;
        }
    }

    /**
     * @param string $bloc_path
     * @param bool $vars
     * @throws Exception
     */
    public function pB($bloc_path = '', $vars = false)
    {
        if (is_array($vars) && !$this->isAssociativeArray($vars)) {
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
        $bloc_path = ($bloc_path == '') ? $this->bloc_limit : $bloc_path;
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
                if (is_array($v) && !empty($v)) {
                    $subPath = $bloc_path != '.' ? $bloc_path . $this->bloc_limit . $k : $k;
                    $this->iB($subPath);
                    $this->pB($subPath, $v);
                    unset($vars[$k]);
                }
            }
        }
        $bloc = $this->blocs[$bloc_path];
        $html = $bloc['tpl'];
        // process children
        foreach ($this->blocs as $child_path => $bloc_content) {
            if ($this->isChildOf($bloc_path, $child_path) && $this->blocs[$child_path]['prof'] == $this->blocs[$bloc_path]['prof'] + 1) {
                $child_bloc_name = $this->getBlocNameFromPath($child_path);
                if (intval($this->blocs[$child_path]['init']) != 2) {
                    $this->log("Child bloc initialized but not parsed : " . $child_bloc_name . " in parent bloc " . $parent_bloc_path);
                }
                $pattern = "#(\s*)<bloc:" . $child_bloc_name . ">(.*)</bloc:" . $child_bloc_name . ">(\s*)#ixsm";
                $out = $this->getPregmatch($pattern, $html);
                if (!count($out)) {
                    $this->log("Child bloc '" . $child_bloc_name . "' not found, cannot be included into '" . $bloc_path . "'");
                } else {
                    $html_child = $this->display($child_path, true);
                    $html = str_replace($out[0], $out[1] . $html_child . $out[3], $html);
                }
                $this->blocs[$child_path]['html'] = '';
            }
        }
        $html = $this->parseBloc($html, $vars);

        // strips remaining blocs/vars not used
        $html = $this->stripBlocs($html, $bloc_path);
        $html = $this->stripVars($html, $bloc_path);

        $this->blocs[$bloc_path]['html'] .= $html;
        $this->blocs[$bloc_path]['init'] = 2;

        return true;
    }

    /**
     * initialize AND parse a bloc in one operation
     *
     * @param $bloc_path
     * @param bool $vars
     * @param array $container
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

    protected function replaceImportedTemplate($html)
    {
        $out = $this->getPregmatch($this->import_pattern, $html);
        $nb = count($out[0]);
        if ($nb != 0) {
            for ($i = 0; $i < $nb; $i++) {
                $imported = $this->getImportedTemplate($out[1][$i]);
                $importedReplaced = $this->replaceImportedTemplate($imported);
                $html = str_replace($out[0][$i], $importedReplaced, $html);
            }
        }

        return $html;
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
        preg_match($pattern, $content, $out);
        while (preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR) {
            ini_set('pcre.backtrack_limit', (int)ini_get('pcre.backtrack_limit') + 100000);
            preg_match($pattern, $content, $out);
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

    protected function getImportedTemplate($path)
    {
        $path = $this->getPath($path);
        if (!is_file($path) || ($_tmp = file_get_contents($path)) === false) {
            throw new Exception("File does not exist or is not readable : " . $path);
        } else {
            return $_tmp;
        }
    }

    /**
     * @param string $bloc_path
     * @param bool $return_str
     * @return mixed
     * @throws Exception
     */
    public function display($bloc_path = '', $return_str = false)
    {
        $bloc_path = ($bloc_path == '') ? $this->bloc_limit : $bloc_path;
        if (!isset($this->blocs[$bloc_path])) {
            throw new Exception("Bloc does not exist : " . $bloc_path);
        }
        if ($this->blocs[$bloc_path]['init'] != 2) {
            throw new Exception("Bloc has not been parsed : " . $bloc_path);
        }
        $html = $this->blocs[$bloc_path]['html'];
        if ($return_str) {
            return $html;
        } else {
            echo $html;

            return true;
        }
    }

    /**
     * @param $tplContent
     * @return array
     */
    public function getTemplateErrors($tplContent)
    {

        $errors = [];

        if (preg_match("#<\?(?!xml)#ixm", $tplContent) != 0) {
            $errors[] = "Script opening (<?)";
        }

        $tab_init = [];
        preg_match_all("#<bloc:(" . $this->bloc_pattern . ")>#ixsm", $tplContent, $matches, PREG_PATTERN_ORDER);
        foreach ($matches[1] as $v) {
            if (array_search($v, $tab_init) !== false) {
                $errors[] = "Bloc already exists <" . $v . ">";
            } else {
                $tab_init[] = $v;
            }
        }

        preg_match_all("#<bloc:([^>.]*)>#ixsm", $tplContent, $matches, PREG_PATTERN_ORDER);
        foreach ($matches[1] as $v) {
            if (array_search($v, $tab_init) === false) {
                $errors[] = "Wrong name for opened bloc <" . $v . ">";
            }
        }

        $tab_close = [];
        preg_match_all("#</bloc:(" . $this->bloc_pattern . ")>#ixsm", $tplContent, $matches, PREG_PATTERN_ORDER);
        foreach ($matches[1] as $v) {
            if (array_search($v, $tab_close) !== false) {
                $errors[] = "Bloc is closed multiple times <" . $v . ">";
            } else {
                $tab_close[] = $v;
            }
            if (array_search($v, $tab_init) === false) {
                $errors[] = "Bloc closed but not opened <" . $v . ">";
            }
        }

        preg_match_all("#</bloc:([^>.]*)>#ixsm", $tplContent, $matches, PREG_PATTERN_ORDER);
        foreach ($matches[1] as $v) {
            if (array_search($v, $tab_close) === false) {
                $errors[] = "Wrong name for closed bloc <" . $v . ">";
            }
        }
        $tab_pos = [];
        foreach ($tab_init as $v) {
            if (array_search($v, $tab_close) === false) {
                $errors[] = "Not closed bloc <" . $v . ">";
            }
            $open = strpos($tplContent, '<bloc:' . $v . '>');
            $close = strpos($tplContent, '</bloc:' . $v . '>');
            if ($open > $close) {
                $errors[] = "Bloc closed before being opened <" . $v . ">";
            }
            $tab_pos[] = ['bloc' => $v, 'open' => $open, 'close' => $close];
        }
        $nb = count($tab_pos);
        for ($i = 0; $i < $nb; $i++) {
            $val = $tab_pos[$i];
            for ($j = 0; $j < $nb; $j++) {
                $val2 = $tab_pos[$j];
                if (($val['open'] > $val2['open'] && $val['open'] < $val2['close'] && $val['close'] > $val2['close']) || ($val2['open'] > $val['open'] && $val2['open'] < $val['close'] && $val2['close'] > $val['close'])) {
                    $errors[] = "Bloc inception error between " . $val['bloc'] . " and " . $val2['bloc'];
                }
            }
        }

        return $errors;
    }

    /**
     * @param $path
     * @param Tpl $tpl_temp
     * @return array
     */
    public static function checkTemplateDirectory($path, Tpl $tpl_temp)
    {
        $nb = 0;
        $errors = [];
        if ($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    $path1 = $path . '/' . $file;
                    if (is_dir($path1)) {
                        $result = static::checkTemplateDirectory($path1, $tpl_temp);
                        $nb += $result['count'];
                        $errors = array_merge($errors, $result['errors']);
                    } elseif (substr($path1, -5, 5) == '.html') {
                        $content = file_get_contents($path1);
                        $errors = $tpl_temp->getTemplateErrors($content);
                    }
                }
            }
        }

        return ['count' => $nb, 'errors' => $errors];
    }

    protected function log($log)
    {
        firelog($log);
    }
}
