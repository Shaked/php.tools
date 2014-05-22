#!/bin/bash
clear; for i in  `seq -f '%03g' 0 67`; do echo -n "$i " ; (php codeFormatter.php tests/$i-*.in | php -l); done;

