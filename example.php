<?php

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

echo $result;
