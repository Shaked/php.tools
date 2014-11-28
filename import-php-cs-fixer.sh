#!/bin/bash
echo "Resetting import..."
rm -f master.zip
rm -rf PHP-CS-Fixer-master
rm -rf php-cs-fixer/Contrib
rm -rf php-cs-fixer/Symfony
echo "Downloading PHP-CS-Fixer-master..."
wget https://github.com/FriendsOfPHP/PHP-CS-Fixer/archive/master.zip &> /dev/null
unzip master.zip &> /dev/null
rm -f master.zip
echo "Incorporating relevant parts..."
mv PHP-CS-Fixer-master/Symfony/CS/Fixer/Contrib php-cs-fixer/
mv PHP-CS-Fixer-master/Symfony/CS/Fixer/Symfony php-cs-fixer/
mv PHP-CS-Fixer-master/Symfony/CS/Tokenizer/Token.php php-cs-fixer/
rm -f php-cs-fixer/Contrib/AlignDoubleArrowFixer.php
rm -f php-cs-fixer/Contrib/AlignEqualsFixer.php
rm -f php-cs-fixer/Symfony/EmptyReturnFixer.php
rm -f php-cs-fixer/Symfony/ExtraEmptyLinesFixer.php
rm -f php-cs-fixer/Symfony/MultilineArrayTrailingCommaFixer.php
rm -f php-cs-fixer/Symfony/NewWithBracesFixer.php
rm -f php-cs-fixer/Symfony/OperatorsSpacesFixer.php
rm -f php-cs-fixer/Symfony/RemoveLeadingSlashUseFixer.php
rm -f php-cs-fixer/Symfony/SingleArrayNoTrailingCommaFixer.php
rm -f php-cs-fixer/Symfony/StandardizeNotEqualFixer.php
rm -f php-cs-fixer/Symfony/UnusedUseFixer.php
rm -f php-cs-fixer/Symfony/WhitespacyLinesFixer.php
echo "Executing transformations..."
cd php-cs-fixer/
php transform-fixers.php