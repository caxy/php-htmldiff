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

```php
use Caxy\HtmlDiff\HtmlDiff;

$htmlDiff = new HtmlDiff($oldHtml, $newHtml);
$content = $htmlDiff->build();
```

## Configuration

The configuration for HtmlDiff is contained in the `Caxy\HtmlDiff\HtmlDiffConfig` class.

There are two ways to set the configuration:

1. [Configure an Existing HtmlDiff Object](#configure-an-existing-htmldiff-object)
2. [Create and Use a HtmlDiffConfig Object](#create-and-use-a-htmldiffconfig-object)

#### Configure an Existing HtmlDiff Object

When a new `HtmlDiff` object is created, it creates a `HtmlDiffConfig` object with the default configuration.
You can change the configuration using setters on the object:

```php
use Caxy\HtmlDiff\HtmlDiff;

// ...

$htmlDiff = new HtmlDiff($oldHtml, $newHtml);

// Set some of the configuration options.
$htmlDiff->getConfig()
    ->setMatchThreshold(80)
    ->setInsertSpaceInReplace(true)
;

// Calculate the differences using the configuration and get the html diff.
$content = $htmlDiff->build();

// ...

```

#### Create and Use a HtmlDiffConfig Object

You can also set the configuration by creating an instance of
`Caxy\HtmlDiff\HtmlDiffConfig` and using it when creating a new `HtmlDiff`
object using `HtmlDiff::create`.

This is useful when creating more than one instance of `HtmlDiff`:

```php
use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\HtmlDiffConfig;

// ...

$config = new HtmlDiffConfig();
$config
    ->setMatchThreshold(95)
    ->setInsertSpaceInReplace(true)
;

// Create an HtmlDiff object with the custom configuration.
$firstHtmlDiff = HtmlDiff::create($oldHtml, $newHtml, $config);
$firstContent = $firstHtmlDiff->build();

$secondHtmlDiff = HtmlDiff::create($oldHtml2, $newHtml2, $config);
$secondHtmlDiff->getConfig()->setMatchThreshold(50);

$secondContent = $secondHtmlDiff->build();

// ...
```

#### Full Configuration with Defaults:

```php

$config = new HtmlDiffConfig();
$config
    // Percentage required for list items to be considered a match.
    ->setMatchThreshold(80)
    
    // Set the encoding of the text to be diffed.
    ->setEncoding('UTF-8')
    
    // If true, a space will be added between the <del> and <ins> tags of text that was replaced.
    ->setInsertSpaceInReplace(false)
    
    // Option to disable the new Table Diffing feature and treat tables as regular text.
    ->setUseTableDiffing(true)
    
    // Pass an instance of \Doctrine\Common\Cache\Cache to cache the calculated diffs.
    ->setCacheProvider(null)
    
    // Set the cache directory that HTMLPurifier should use.
    ->setPurifierCacheLocation(null)
    
    // Group consecutive deletions and insertions instead of showing a deletion and insertion for each word individually. 
    ->setGroupDiffs(true)
    
    // List of characters to consider part of a single word when in the middle of text.
    ->setSpecialCaseChars(array('.', ',', '(', ')', '\''))
    
    // List of tags to treat as special case tags.
    ->setSpecialCaseTags(array('strong', 'b', 'i', 'big', 'small', 'u', 'sub', 'sup', 'strike', 's', 'p'))
    
    // List of tags (and their replacement strings) to be diffed in isolation.
    ->setIsolatedDiffTags(array(
        'ol'     => '[[REPLACE_ORDERED_LIST]]',
        'ul'     => '[[REPLACE_UNORDERED_LIST]]',
        'sub'    => '[[REPLACE_SUB_SCRIPT]]',
        'sup'    => '[[REPLACE_SUPER_SCRIPT]]',
        'dl'     => '[[REPLACE_DEFINITION_LIST]]',
        'table'  => '[[REPLACE_TABLE]]',
        'strong' => '[[REPLACE_STRONG]]',
        'b'      => '[[REPLACE_B]]',
        'em'     => '[[REPLACE_EM]]',
        'i'      => '[[REPLACE_I]]',
        'a'      => '[[REPLACE_A]]',
    ))
;

```

## Contributing

See [CONTRIBUTING][contributing] file.

## Contributor Code of Conduct

Please note that this project is released with a [Contributor Code of
Conduct][contributor_covenant]. By participating in this project
you agree to abide by its terms. See [CODE_OF_CONDUCT][code_of_conduct] file.

## Credits

* [SavageTiger][] for contributing many improvements and fixes to caxy/php-htmldiff! 
* [rashid2538][] for the port to PHP and the base for our project: [rashid2538/php-htmldiff][upstream]
* [willdurand][] for an excellent post on [open sourcing libraries][].
Much of this documentation is based off of the examples in the post.

Did we miss anyone? If we did, let us know or put in a pull request!

## License

php-htmldiff is available under [GNU General Public License, version 2][gnu]. See the [LICENSE][license] file for details.

## TODO

* Tests, tests, and more tests! (mostly unit tests) - need more tests before we can major refactoring / cleanup for a v1 release
* Add documentation for setting up a cache provider (doctrine cache)
    * Maybe add abstraction layer for cache + adapter for doctrine cache
* Make HTML Purifier an optional dependency - possibly use abstraction layer for purifiers so alternatives could be used (or none at all for performance)
* Expose configuration for HTML Purifier (used in table diffing) - currently only cache dir is configurable through HtmlDiffConfig object
* Add option to enable using HTML Purifier to purify all input
* Performance improvements (we have 1 benchmark test, we should probably get more)
    * Algorithm improvements - trimming alike text at start and ends, store nested diff results in memory to re-use (like we do w/ caching)
    * Benchmark using DOMDocument vs. alternatives vs. string parsing
* Benchmarking
* Look into removing dependency on php-simple-html-dom-parser library - possibly find alternative or no library at all. Consider how this affects performance.
* Refactoring (but... tests first)
    * Overall design/architecture improvements
    * API improvements so a new HtmlDiff isn't required for each new diff (especially so that configuration can be re-used)
* Split demo application to separate repository
* Add documentation on alternative htmldiff engines and perhaps some comparisons


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
[SavageTiger]: https://github.com/SavageTiger
[rashid2538]: https://github.com/rashid2538
[willdurand]: https://github.com/willdurand
[open sourcing libraries]: http://williamdurand.fr/2013/07/04/on-open-sourcing-libraries/
