<?php
//declare(strict_types = 1);

namespace BixevTest\LightHtmlTemplateTest;

class TplTest extends \PHPUnit\Framework\TestCase
{

    public function testFromString()
    {
        $template = "
Before first bloc {{var1 | unknownFunction}}
{{ unknownVar }}{{unknownVar | unknownFunction}}
{{bloc:myFirstBloc}}

    first bloc start {{var1}}

    {{bloc:mySecondBloc}}
        content of second bloc with {{var}}
        {{bloc:my5thBloc}}
            content of 5th bloc with {{ var }}
        {{endbloc:my5thBloc}}
    {{endbloc:mySecondBloc}}

    first bloc middle {{var3 }}

    {{ bloc:myThirdBloc }}
        content of third bloc with {{var}}
    {{endbloc:myThirdBloc}}

    {{bloc:my4thBloc}}
        content of 4th bloc with {{var}}
    {{ endbloc:my4thBloc }}

    first bloc end {{ var3}}

{{endbloc:myFirstBloc}}
After first bloc {{var1}}
";
        $tpl = \Bixev\LightHtmlTemplate\Factory::newTemplateFromString($template);
        $tpl->pB(
            [
                'var1'        => 'testVal1',
                'myFirstBloc' => [
                    'var1'         => 'testFirst1',
                    'var2'         => 'testFirst2',
                    'var3'         => 'testFirst3',
                    'mySecondBloc' => [
                        'var'       => 'testSecond',
                        'my5thBloc' => [
                            ['var' => 'test4th',],
                            ['var' => 'test5th',],
                        ],
                    ],
                    'myThirdBloc'  => [
                        ['var' => 'testThird1',],
                        ['var' => 'testThird2',],
                    ],
                ],
            ]
        );
        $result = $tpl->render();
        $expected = "
Before first bloc testVal1



    first bloc start testFirst1

    
        content of second bloc with testSecond
        
            content of 5th bloc with test4th
        
            content of 5th bloc with test5th
        
    

    first bloc middle testFirst3

    
        content of third bloc with testThird1
    
        content of third bloc with testThird2
    

    

    first bloc end testFirst3


After first bloc testVal1
";
        $this->assertEquals($expected, $result);
    }

    public function testFromFile()
    {
        \Bixev\LightHtmlTemplate\Factory::addDirectory(__DIR__ . DIRECTORY_SEPARATOR . 'html');
        $tpl = \Bixev\LightHtmlTemplate\Factory::newTemplateFromFile('test1');
        $tpl->pB();
        $result = $tpl->render();
        $expected = "tutu
";
        $this->assertEquals($expected, $result);
    }

    public function testImport()
    {
        \Bixev\LightHtmlTemplate\Factory::addDirectory(__DIR__ . DIRECTORY_SEPARATOR . 'html');
        $tpl = \Bixev\LightHtmlTemplate\Factory::newTemplateFromFile('test2');
        $tpl->pB(
            [
                'firstBloc'      => [],
                'test3FirstBLoc' => [],
            ]
        );
        $result = $tpl->render();
        $expected = "tutu2

test first bloc


tutu3

test3FirstBloc content


tutu4


";
        $this->assertEquals($expected, $result);
    }
}
