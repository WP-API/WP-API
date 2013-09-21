Release Process
===============
Here's the process used to generate new releases for the API plugin.

* Write changelog items for this release. Generate these automatically with the
  following script:

	SINCE=$1
	if [[ -z $SINCE ]]; then
		echo "usage: changelog <since>"
		exit 1
	fi

	OUTPUT=$(git log $SINCE... --reverse --format="format:- %w(80,0,4)%B")
	OUTPUT=$(echo "$OUTPUT" | awk -v nlines=2 "/^ *git-svn-id:/ {for (i=0; i<nlines; i++) {getline}; next} 1")
	OUTPUT=$(echo "$OUTPUT" | grep -v "^$")

	echo "$OUTPUT"

* Bump version in plugin.php
* Bump version in lib/class-wp-json-server.php
* `gsocpush` (`alias gsocpush='git svn dcommit && git push'`)
* `git tag -a 0.x` - Tag with a message like:

	Version 0.x: Major Feature

	Summary of the big features in this release.

* Sync to plugin repo
