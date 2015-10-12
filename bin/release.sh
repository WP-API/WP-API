# release.sh
#
# Takes a tag to release, and syncs it to WordPress.org

TAG=$1

PLUGIN="rest-api"
TMPDIR=/tmp/release-svn
PLUGINDIR="$PWD"
PLUGINSVN="https://plugins.svn.wordpress.org/$PLUGIN"

# Fail on any error
set -e

# Is the tag valid?
if [ -z "$TAG" ] || ! git rev-parse "$TAG" > /dev/null; then
	echo "Invalid tag. Make sure you tag before trying to release."
	exit 1
fi

if [[ $VERSION == "v*" ]]; then
	# Starts with an extra "v", strip for the version
	VERSION=${TAG:1}
else
	VERSION="$TAG"
fi

if [ -d "$TMPDIR" ]; then
	# Wipe it clean
	rm -r "$TMPDIR"
fi

# Ensure the directory exists first
mkdir "$TMPDIR"

# Grab an unadulterated copy of SVN
svn co "$PLUGINSVN/trunk" "$TMPDIR" > /dev/null

# Extract files from the Git tag to there
git archive --format="zip" -0 "$TAG" | tar -C "$TMPDIR" -xf -

# Switch to build dir
cd "$TMPDIR"

# Run build tasks
sed -e "s/{{TAG}}/$VERSION/g" < "$PLUGINDIR/bin/readme.txt" > readme.txt

# Remove special files
rm ".gitignore"
rm ".scrutinizer.yml"
rm ".travis.yml"
rm "composer.json"
rm "Gruntfile.js"
rm "package.json"
rm "phpcs.ruleset.xml"
rm "phpunit.xml.dist"
rm "multisite.xml"
rm "codecoverage.xml"
rm -r "assets"
rm -r "bin"
rm -r "tests"

# Add any new files
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add

# Pause to allow checking
echo "About to commit $VERSION. Double-check $TMPDIR to make sure everything looks fine."
read -p "Hit Enter to continue."

# Commit the changes
svn commit -m "Tag $VERSION"

# tag_ur_it
svn copy "$PLUGINSVN/trunk" "$PLUGINSVN/tags/$VERSION" -m "Tag $VERSION"
