A simple html templating system with HTML-only templates, and only a few php commands.

It is `scripting language agnostic` and `display language agnostic`. You will have to know `only 3 structure` :

* block : used to display none, one or many times a piece of code
* var : used to display a value (optionnaly filtered)
* import : used to import template from another file

# Installation

It's recommended that you use Composer to install this lib.

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
```

php content (your php view) :

```php
<?php
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
```

This will result in

```
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
```

# Test

```php
vendor/bin/phpunit --bootstrap vendor/autoload.php ./test/BixevTest/LightHtmlTemplateTest/TplTest.php
```
