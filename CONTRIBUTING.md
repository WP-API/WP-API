# Contributing
Hi, and thanks for considering contributing! Before you do though, here's a few
notes on how best to contribute. Don't worry, I'll keep it short!

## Getting Started

### Submitting a pull request

Contributions to WP-API always follow a feature branch pull request workflow.

First, create a new branch for your changes. As far as branch naming goes, a
good rule of thumb is to use the issue number followed by a short slug that
represents the feature (e.g. `2392-contributing-docs`).

Then, submit a pull request early to get a round of feedback on your proposed
changes. It's better to get quick feedback to ensure you're on the right track.

Next, make sure you have adequate test coverage around your changes. With a
project of this magnitude, it's better to have too much test coverage than not
enough.

Last, leave a #reviewmerge comment on your pull request for a final review by
the WP-API team.

### Running tests

The WP-API project uses two continuous integration (CI) services, Travis and
Scrutinizer, to automatically run a series of tests against its codebase on
every push. If you're submitting a pull request, it's expected that all tests
will pass — and that you add more tests for your change.

You can install the necessary libraries in your local environment with:

    npm install -g grunt-cli
    npm install
    composer install

Then, run PHPUnit tests with `phpunit`, or WPCS tests with `grunt phpcs`.

## Best Practices

### Commit Messages
Commit messages should follow the standard laid out in the git manual; that is,
a one-line summary ()

	Short (50 chars or less) summary of changes

	More detailed explanatory text, if necessary.  Wrap it to about 72
	characters or so.  In some contexts, the first line is treated as the
	subject of an email and the rest of the text as the body.  The blank
	line separating the summary from the body is critical (unless you omit
	the body entirely); tools like rebase can get confused if you run the
	two together.

	Further paragraphs come after blank lines.

	 - Bullet points are okay, too

	 - Typically a hyphen or asterisk is used for the bullet, preceded by a
	   single space, with blank lines in between, but conventions vary here

## Commit Process
Changes are proposed in the form of pull requests by you, the contributor! After
submitting your proposed changes, a member of the API team will review your
commits and mark them for merge by assigning it to themselves. Your pull request
will then be merged after final review by another member.
