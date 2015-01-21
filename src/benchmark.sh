#!/bin/bash

echo "Stop all other activities while benchmarking to avoid noise."

TARGET=""
if [ "$1" == "" ];
then
	TARGET="HEAD^";
fi;

git branch -D benchmark &> /dev/null
git checkout master &> /dev/null
git checkout -b benchmark &> /dev/null
echo -n master:
(for i in `seq 1 10`; do php test.php -v | grep -i Took; done; ) | awk '{ total += $2; count++ } END { print total/count }'

git reset --hard $TARGET &> /dev/null
echo -n "$TARGET:"
(for i in `seq 1 10`; do php test.php -v | grep -i Took; done; ) | awk '{ total += $2; count++ } END { print total/count }'

git checkout master &> /dev/null
git branch -D benchmark &> /dev/null
