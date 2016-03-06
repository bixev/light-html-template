<?php

$template = "
Before first bloc {var1}
<bloc:myFirstBloc>

    first bloc start {var1}

    <bloc:mySecondBloc>
        content of second bloc with {var}
    </bloc:mySecondBloc>

    first bloc middle {var3}

    <bloc:myThirdBloc>
        content of third bloc with {var}
    </bloc:myThirdBloc>

    <bloc:my4thBloc>
        content of 4th bloc with {var}
    </bloc:my4thBloc>

    first bloc end {var3}

</bloc:myFirstBloc>
After first bloc {var1}
";
$tpl = \Bixev\LightHtmlTemplate\Factory::newTemplateFromString($template);
$tpl->pB([
    'var1'        => 'testVal1',
    'myFirstBloc' => [
        'var1'=>'testFirst1',
        'var2'=>'testFirst2',
        'var3'=>'testFirst3',
        'mySecondBloc' => [
            'var' => 'testSecond',
        ],
        'myThirdBloc'  => [
            ['var' => 'testThird',],
            ['var' => 'testThird',],
        ],
    ],
]);
$result = $tpl->render();

echo $result;
