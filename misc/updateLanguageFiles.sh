#!/bin/bash
PIWIKPATH=$PWD/..

################################
# Configuration
#
GitBranchName="translationupdates"
OTranceUser="downloaduser"
#
################################

################################
#  Perform initial git actions
#
# update master branch
git checkout master > /dev/null 2>&1
git pull > /dev/null 2>&1

# check if branch exists local (assume its the correct one if so)
git branch | grep $GitBranchName > /dev/null 2>&1

if [ $? -eq 1 ]; then
    git checkout -b $GitBranchName origin/$GitBranchName > /dev/null 2>&1
fi

# switch to branch and merge with master
git checkout $GitBranchName > /dev/null 2>&1
git merge master > /dev/null 2>&1
git push origin $GitBranchName > /dev/null 2>&1
#
################################

################################
# Fetch package from oTrance
#
read -s -p "Please provide the OTrance password for 'downloaduser'? " OTrancePassword

# download package
echo "Starting to fetch latest language pack"
wget --save-cookies $PIWIKPATH/tmp/cookies.txt \
     --quiet \
     -O - \
     --keep-session-cookies \
     --post-data "user=$OTranceUser&pass=$OTrancePassword&autologin=1" \
     http://translations.piwik.org/public/index/login \
     > /dev/null 2>&1

# create a new download package if wanted
while true; do
    read -p "Shall we create a new language pack? " yn
    case $yn in
        [Yy]* ) wget --load-cookies $PIWIKPATH/tmp/cookies.txt \
                     --quiet \
                     -O - \
                     http://translations.piwik.org/public/export/update.all \
                     > /dev/null 2>&1; break;;
        [Nn]* ) echo "Searching for existing packs instead"; break;;
        * ) echo "Please answer yes or no. ";;
    esac
done

# Search for package and download it
downloadfile=(`wget --load-cookies $PIWIKPATH/tmp/cookies.txt \
                    --quiet \
                    -O - \
                    http://translations.piwik.org/public/downloads/ \
                    | grep -Pom 1 '(language\_pack\-[0-9]{8}\-[0-9]{6}\.tar\.gz)'`)

while true; do
    read -p "Found language pack $downloadfile. Proceed? " yn
    case $yn in
        [Yy]* ) break;;
        [Nn]* ) exit;;
        * ) echo "Please answer yes or no. ";;
    esac
done

wget --load-cookies $PIWIKPATH/tmp/cookies.txt \
     -O $PIWIKPATH/tmp/language_pack.tar.gz \
     http://translations.piwik.org/public/downloads/download/file/$downloadfile

# remove cookie file
rm -f $PIWIKPATH/tmp/cookies.txt

# extract package
echo "Extracting package..."
cd $PIWIKPATH/tmp
gunzip -q -c $PIWIKPATH/tmp/language_pack.tar.gz | tar xvf - > /dev/null 2>&1

if [ $? -gt 0 ]; then
    echo "Error: Unable to extract download package. Aborting..."
    exit;
fi

# remove downloaded file
rm -f $PIWIKPATH/tmp/language_pack.tar.gz

#
################################

################################
# Check extracted files
#
# remove some useless files from package data
rm -f en.php
rm -f pt_BR.php
rm -f zh_CN.php
rm -f zh_TW.php

# copy all files to /lang (except en.php)
mv *.php ../lang/

# get untracked files and ask if each file should be removed or added
cd $PIWIKPATH
list=(`git ls-files --other --exclude-standard lang`)

echo "Untracked language files detected:
    Please choose the files that should be added to git.
    Other files will be removed.";

for file in ${list[@]}
do
    while true; do
        read -p "Add new file $file to git? " yn
        case $yn in
            [Yy]* ) git add $file; break;;
            [Nn]* ) rm $file; break;;
            * ) echo "Please answer yes or no. ";;
        esac
    done
done
#
################################

################################
# Cleanup / Test new files
#
# run tests to cleanup files as long as tests are failing
counter=0
while true; do

    # if tests fail 3 times, abort as they won't pass
    if [ $counter -eq 3 ]; then
        echo "Tests have failed 3 times. Aborting..."
        exit;
    fi

    # run tests to cleanup files
    echo "Running unittests to cleanup language files. This may take some time..."
    cd $PIWIKPATH/tests/PHPUnit
    phpunit --group LanguagesManager > /dev/null 2>&1

    # If all tests pass, break loop
    if [ $? -eq 0 ]; then
        echo "All files OK."
        break;
    fi

    echo "Files cleaned."

    # move cleaned files from /tmp to /lang
    echo "Moving cleaned language files from /tmp to /lang"
    cd $PIWIKPATH/tmp
    mv *.php ../lang/

    counter=$(($counter + 1))
done
#
################################

################################
# Perform finish git actions & create pull request
#
# commit files
cd $PIWIKPATH
git add lang/. > /dev/null 2>&1
git commit -m "language update refs #3430"
git push

while true; do
    read -p "Please provide your github username (to create a pull request using Github API): " username

    returnCode=(`curl \
         -X POST \
         -k \
         --silent \
         --write-out %{http_code} \
         --stderr /dev/null \
         -o /dev/null \
         -u $username \
         --data "{\"title\":\"automatic translation update\",\"body\":\"autogenerated translation update out of $downloadfile\",\"head\":\"$GitBranchName\",\"base\":\"master\"}" \
         -H 'Accept: application/json' \
         https://api.github.com/repos/piwik/piwik/pulls`);

    if [ $returnCode -eq 401 ]; then
        echo "Pull request failed. Bad credentials... Please try again"
        continue;
    fi

    if [ $returnCode -eq 422 ]; then
        echo "Pull request failed. Unprocessable Entity. Maybe a pull request was already created before."
        break;
    fi

    if [ $returnCode -eq 201 ]; then
        echo "Pull request successfully created."
        break;
    fi

    if [ $returnCode -eq 200 ]; then
        echo "Pull request successfully created."
        break;
    fi

    echo "Pull request failed."

done;

git checkout master > /dev/null 2>&1
#
################################