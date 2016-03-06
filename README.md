A simple html templating system with HTML-only templates, and only a few php commands.

# Installation

It's recommended that you use Composer to install InterventionSDK.

```bash
composer require bixev/light-html-template "~1.0"
```

This will install this library and all required dependencies.

so each of your php scripts need to require composer autoload file

```php
<?php

require 'vendor/autoload.php';
```

# Usage

## HTML template

Your `.html` templates (or `string`) can only contain HTML and `<bloc>` / `{var}` to work.

__Your php views will deal with conditions, loops and more__

HTML content (into a .html template  file, or into a string var from php) :

```html
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
```

php content (your php view) :

```php
<?php
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
```

This will result in

```
Before first bloc testVal1


    first bloc start testFirst1

    
        content of second bloc with testSecond
    

    first bloc middle testFirst3

    
        content of third bloc with testThird
    
        content of third bloc with testThird first bloc end testFirst3


After first bloc testVal1

```