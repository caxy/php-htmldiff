php-htmldiff
============

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/caxy/php-htmldiff/badges/quality-score.png?b=master)][badge_score]
[![Build Status](https://scrutinizer-ci.com/g/caxy/php-htmldiff/badges/build.png?b=master)][badge_status]
[![Code Coverage](https://scrutinizer-ci.com/g/caxy/php-htmldiff/badges/coverage.png?b=master)][badge_coverage]
[![Packagist](https://img.shields.io/packagist/dt/caxy/php-htmldiff.svg)][badge_packagist]
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/caxy/php-htmldiff.svg)][badge_resolve]
[![Percentage of issues still open](http://isitmaintained.com/badge/open/caxy/php-htmldiff.svg)][badge_issues]

php-htmldiff is a library for comparing two HTML files/snippets and highlighting the differences using simple HTML.

This HTML Diff implementation was forked from [rashid2538/php-htmldiff][upstream] and has been modified with new features,
bug fixes, and enhancements to the original code.

For more information on these modifications, read the [differences from rashid2538/php-htmldiff][differences] or view the [CHANGELOG][changelog].

## Installation

The recommended way to install php-htmldiff is through [Composer][composer].
Require the [caxy/php-htmldiff][badge_packagist] package by running following command:

```sh
composer require caxy/php-htmldiff
```

This will resolve the latest stable version.

Otherwise, install the library and setup the autoloader yourself.

### Working with Symfony

If you are using Symfony, you can use the [caxy/HtmlDiffBundle][htmldiffbundle] to make life easy!

## Usage

(WIP)

```php
use Caxy\HtmlDiff\HtmlDiff;

$htmlDiff = new HtmlDiff($oldHtml, $newHtml);
$content = $htmlDiff->build();
```

## Configuration

WIP

## Contributing

See [CONTRIBUTING][contributing] file.

## Contributor Code of Conduct

Please note that this project is released with a [Contributor Code of
Conduct][contributor_covenant]. By participating in this project
you agree to abide by its terms. See [CODE_OF_CONDUCT][code_of_conduct] file.

## Credits

* [rashid2538][] for the port to PHP and the base for our project: [rashid2538/php-htmldiff][upstream]
* [willdurand][] for an excellent post on [open sourcing libraries][].
Much of this documentation is based off of the examples in the post.

Did we miss anyone? If we did, let us know or put in a pull request!

## License

php-htmldiff is available under [GNU General Public License, version 2][gnu]. See the [LICENSE][license] file for details.

[badge_score]: https://scrutinizer-ci.com/g/caxy/php-htmldiff/?branch=master
[badge_status]: https://scrutinizer-ci.com/g/caxy/php-htmldiff/build-status/master
[badge_coverage]: https://scrutinizer-ci.com/g/caxy/php-htmldiff/?branch=master
[badge_packagist]: https://packagist.org/packages/caxy/php-htmldiff
[badge_resolve]: http://isitmaintained.com/project/caxy/php-htmldiff "Average time to resolve an issue"
[badge_issues]: http://isitmaintained.com/project/caxy/php-htmldiff "Percentage of issues still open"
[upstream]: https://github.com/rashid2538/php-htmldiff
[htmldiffbundle]: https://github.com/caxy/HtmlDiffBundle
[differences]: https://github.com/caxy/php-htmldiff/blob/master/doc/differences.rst
[changelog]: https://github.com/caxy/php-htmldiff/blob/master/CHANGELOG.md
[contributing]: https://github.com/caxy/php-htmldiff/blob/master/CONTRIBUTING.md
[gnu]: http://www.gnu.org/licenses/gpl-2.0.html
[license]: https://github.com/caxy/php-htmldiff/blob/master/LICENSE
[code_of_conduct]: https://github.com/caxy/php-htmldiff/blob/master/CODE_OF_CONDUCT.md
[composer]: http://getcomposer.org/
[contributor_covenant]: http://contributor-covenant.org/
[rashid2538]: https://github.com/rashid2538
[willdurand]: https://github.com/willdurand
[open sourcing libraries]: http://williamdurand.fr/2013/07/04/on-open-sourcing-libraries/
