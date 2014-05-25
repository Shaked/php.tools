#!/bin/bash
clear; for i in  `seq -f '%03g' 0 70`; do php codeFormatter.php tests/$i-*.in | cat -n | more ; (php codeFormatter.php tests/$i-*.in | php -l); echo $i; read -s -n 1; clear; done;
