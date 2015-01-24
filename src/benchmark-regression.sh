#!/bin/bash

STEPS=$1
if [ "" == "$STEPS" ];
then
	STEPS=10
fi;

TESTCOUNT=$2
if [ "" == "$TESTCOUNT" ];
then
	TESTCOUNT=5
fi;

SEQ=`seq 1 $STEPS`

git checkout master &> /dev/null
git branch -D benchmark-regression &> /dev/null
git checkout -b benchmark-regression &> /dev/null
for i in $SEQ;
do
	MSG=`git log --pretty=oneline | head -n 1`
	TESTS=`find . -iwholename "./tests*.in" | wc -l | tr -d ' '`;
	echo -n "$MSG ::: $TESTS ";
	find . -iwholename "tests*/*.in"
	( for i in `seq 1 $TESTCOUNT`; do php -dshort_open_tag=On test.php | grep -i Took; done; ) | awk "{
		total += \$2;
		count++
	}
	END {
		print total/count \" \" total \" \" count \" \" total/($TESTS*$TESTCOUNT)
	}"
	git reset --hard HEAD^ &> /dev/null
done;
git checkout master &> /dev/null
git branch -D benchmark-regression &> /dev/null
