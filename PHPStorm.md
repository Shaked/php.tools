Configuration for PHPStorm
==========================

1 - Install php.tools, either through git or composer.

* Git

```
git clone https://github.com/phpfmt/php.tools.git
```

* Composer

```
php composer.phar global require dericofilho/fmt
```

2 - Add php.tools as an External tool in PhpStorm : Open Settings (or “Preferences” on OS X) > External Tools and setup a new tool :

![phpstorm-configuration](https://raw.githubusercontent.com/phpfmt/php.tools/master/phpstorm-configuration.png)

Fill the fields with the following:

Program: `/full/path/to/php /full/path/to/fmt.php`

Parameters: `--no-backup “$FileDir$/$FileName$”`

(Note `--no-backup` prevents the creation of backup files `file~`, therefore not letting pollute the working directory).

Working directory: `$ProjectFileDir$`

Tested with PhpStorm 8 stable with `php.tools 5.30.2`, [consider putting a keyboard shortcut to make it easier](https://www.jetbrains.com/phpstorm/help/configuring-keyboard-shortcuts.html).
