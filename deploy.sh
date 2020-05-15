#! /bin/bash

# main config
PLUGINSLUG="woocommerce-gateway-paypal-express-checkout"
CURRENTDIR=`pwd`
MAINFILE="woocommerce-gateway-paypal-express-checkout.php" # this should be the name of your main php file in the wordpress plugin

# git config
GITPATH="$CURRENTDIR/" # this file should be in the base of your git repository

# svn config
SVNPATH="/tmp/$PLUGINSLUG" # path to a temp SVN repo. No trailing slash required and don't add trunk.
SVNURL="https://plugins.svn.wordpress.org/woocommerce-gateway-paypal-express-checkout" # Remote SVN repo on wordpress.org, with no trailing slash
SVNUSER="woothemes" # your svn username


# Let's begin...
echo ".........................................."
echo
echo "Preparing to deploy plugin"
echo
echo ".........................................."
echo

# Check version in readme.txt is the same as plugin file
NEWVERSION1=`grep "^Stable tag" $GITPATH/readme.txt | awk -F' ' '{print $3}'`
echo "readme version: $NEWVERSION1"
NEWVERSION2=`grep "^\s\*\sVersion" $GITPATH/$MAINFILE | awk -F' ' '{print $3}'`
echo "$MAINFILE version: $NEWVERSION2"

if [ "$NEWVERSION1" != "$NEWVERSION2" ]; then echo "Version in readme.txt & $MAINFILE don't match. Exiting...."; exit 1; fi

echo "Versions match in readme.txt and $MAINFILE. Let's proceed..."

cd $GITPATH
if git show-ref --tags --quiet --verify -- "refs/tags/$NEWVERSION1"
	then
		echo "Version $NEWVERSION1 already exists as git tag. Exiting....";
		exit 1;
	else
		echo "Git version does not exist. Let's proceed..."
fi


git checkout -q -b "release/$NEWVERSION1"
echo -e "Enter a commit message for this new version: \c"
read COMMITMSG
git commit -am "$COMMITMSG"

echo "Tagging new version in git"
git tag -a "$NEWVERSION1" -m "Tagging version $NEWVERSION1"

echo "Pushing latest commit to origin, with tags"
git push origin "release/$NEWVERSION1"
git push origin --tags

echo
echo "Creating local copy of SVN repo ..."
svn co $SVNURL $SVNPATH

echo "Exporting the HEAD of current branch from git to the trunk of SVN"
git checkout-index -a -f --prefix=$SVNPATH/trunk/

echo "Ignoring github specific & deployment script"
svn propset svn:ignore "deploy.sh
README.md
*.psd
*.svg
tests
wordpress_org_assets
.git
.github
.travis.yml
phpcs.xml
phpunit.xml
DEVELOPER.md
package-lock.json
package.json
deploy.sh
composer.lock
composer.json
vendor
.gitattributes
.editorconfig
.gitignore" $SVNPATH/trunk/

echo "Moving assets-wp-repo"
mkdir $SVNPATH/assets/
mv -v $SVNPATH/trunk/wordpress_org_assets/* $SVNPATH/assets
svn delete --force $SVNPATH/trunk/wordpress_org_assets

echo "Changing directory to SVN"
cd $SVNPATH/trunk/

# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add
echo "committing to trunk"
svn commit --username=$SVNUSER -m "$COMMITMSG"

echo "Updating WP plugin repo assets & committing"
cd $SVNPATH/assets/

# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add
svn commit --username=$SVNUSER -m "Updating wordpress_org_assets"

echo "Creating new SVN tag & committing it"
cd $SVNPATH
svn copy trunk/ tags/$NEWVERSION1/
cd $SVNPATH/tags/$NEWVERSION1
svn commit --username=$SVNUSER -m "Tagging version $NEWVERSION1"

echo "Removing temporary directory $SVNPATH"
rm -fr $SVNPATH/

echo "*** FIN ***"
