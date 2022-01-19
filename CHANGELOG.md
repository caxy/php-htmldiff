# Change Log

## [v0.1.14](https://github.com/caxy/php-htmldiff/tree/v0.1.13) (2022-01-19)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.13...v0.1.14)

### Changes:

This release mainly removed everything that is related to the concept of special-case-tags. This was adopted from the
original library by rashid2538 that this fork is based on. 

The feature tried to wrap the special tags in an extra `<ins / del class='mod'>` tag.
However, this never really worked properly (the closing tag was not always added to the diff output) and usually ended up crippling the HTML.

Given the feature never really worked, and there is no clear use-case for it, I decided to remove it and fix
issue 106 and issue 69 in the process where it was sometimes impossible to diff html that contained these special tags or unexpected extra tags got added to the output.

In case you really needed this feature, please open an issue explaining your use-case, in that case this decision can be revisited.

- Deprecated all setSpecialCaseTags() config calls. There is no replacement, but the current expectation is that nobody ever used these calls anyway.
- Fixed Issue 106 / 69 by removing special-case-tags from the codebase
- Reduced the CRAP score of insertTag() by allot


## [v0.1.13](https://github.com/caxy/php-htmldiff/tree/v0.1.13) (2021-09-27)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.12...v0.1.13)

### Changes:

- Add `.gitattributes` file to exclude demo and tests from the exported zip package to reduce package size when installed via composer (#86 - @danepowell). This means `demo/` and `tests/` will no longer be included in the installed package files. In theory this shouldn't be a breaking change, but it could be if you are depending on or referencing any files from those directories.

## [v0.1.12](https://github.com/caxy/php-htmldiff/tree/v0.1.12) (2021-04-05)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.11...v0.1.12)

### Changes:

- Word parser is rebuild to improve performance by 98% (according to xhprof profiler) and reducing code complexity.
- Whitespace checking improvements in match finding algorithm to improve performance by allot, up to 50% in some of my testing


## [v0.1.11](https://github.com/caxy/php-htmldiff/tree/v0.1.11) (2021-02-02)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.10...v0.1.11)

### Changes:

- Fixed a bug where self-closing tags got crippled in HtmlDiff
- Ported ListDiff from SimpleXML to DOMDocument 
- Cleanup of old list diff algorithm
- Possibility to disable html-purifier using a config flag
- Removed dependency php-simple-html-dom-parser

## [v0.1.10](https://github.com/caxy/php-htmldiff/tree/v0.1.10) (2021-01-05)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.9...v0.1.10)

### Important:

In order to be compatible with PHP8 we had to upgrade some vendor packages.

Since these vendor packages have dropped support for older versions of PHP we had todo the same, therefore this version is not compatible anymore with PHP versions prior to 7.3.

In case you are not able to upgrade your PHP version, please pin version v0.1.9 of php-htmldiff in your composer config.

### Changes:

- Fixed the keywords that made this version incompatible with PHP8
- Upgraded PHPUnit dependencies with a PHP8 compatible version

## [v0.1.9](https://github.com/caxy/php-htmldiff/tree/v0.1.9) (2019-02-20)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.8...v0.1.9)

**Merged pull requests:**

- Issue \#77: Performance Fixes [\#81](https://github.com/caxy/php-htmldiff/pull/81) ([SavageTiger](https://github.com/SavageTiger))

## [v0.1.8](https://github.com/caxy/php-htmldiff/tree/v0.1.8) (2019-01-15)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.7...v0.1.8)

**Implemented enhancements:**

- Possible diff algorithm improvement [\#46](https://github.com/caxy/php-htmldiff/issues/46)
- encoding [\#22](https://github.com/caxy/php-htmldiff/issues/22)
- Resolve PHP 7.3 compatibility issue to fix \#79 [\#80](https://github.com/caxy/php-htmldiff/pull/80) ([irkallacz](https://github.com/irkallacz))

**Closed issues:**

- Does not work on PHP 7.3 [\#79](https://github.com/caxy/php-htmldiff/issues/79)
- Latest release can cause segmentation faults [\#74](https://github.com/caxy/php-htmldiff/issues/74)
- Different results [\#73](https://github.com/caxy/php-htmldiff/issues/73)

## [v0.1.7](https://github.com/caxy/php-htmldiff/tree/v0.1.7) (2018-03-15)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.6...v0.1.7)

**Fixed bugs:**

- Fix issues with unicode characters - fixes \#71 [\#72](https://github.com/caxy/php-htmldiff/pull/72) ([iluuu1994](https://github.com/iluuu1994))

**Closed issues:**

- Encoding issues with umlauts [\#71](https://github.com/caxy/php-htmldiff/issues/71)
- Slow diff even on small text input [\#70](https://github.com/caxy/php-htmldiff/issues/70)

## [v0.1.6](https://github.com/caxy/php-htmldiff/tree/v0.1.6) (2018-01-06)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.5...v0.1.6)

**Closed issues:**

- Bug - bad comparison between words containing accented characters [\#66](https://github.com/caxy/php-htmldiff/issues/66)

**Merged pull requests:**

- Fixed warnings "count\(\): Parameter must be an array... [\#65](https://github.com/caxy/php-htmldiff/pull/65) ([yojick](https://github.com/yojick))

## [v0.1.5](https://github.com/caxy/php-htmldiff/tree/v0.1.5) (2017-06-12)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.4...v0.1.5)

**Fixed bugs:**

- Crashes if string contains "Р" character \(uppercase cyrillic Р letter\) [\#58](https://github.com/caxy/php-htmldiff/issues/58)

**Closed issues:**

- Does not work on PHP 5.3.10 [\#61](https://github.com/caxy/php-htmldiff/issues/61)

**Merged pull requests:**

- HTMLPurifier Permission Fix [\#63](https://github.com/caxy/php-htmldiff/pull/63) ([snebes](https://github.com/snebes))

## [v0.1.4](https://github.com/caxy/php-htmldiff/tree/v0.1.4) (2017-05-02)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.3...v0.1.4)

**Merged pull requests:**

- changes the \[\] arrays to array\(\) as it does not work in php 5.3 [\#62](https://github.com/caxy/php-htmldiff/pull/62) ([myfriend12](https://github.com/myfriend12))

## [v0.1.3](https://github.com/caxy/php-htmldiff/tree/v0.1.3) (2016-07-21)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.2...v0.1.3)

**Implemented enhancements:**

- HTMLDiff Performance inhancement [\#54](https://github.com/caxy/php-htmldiff/pull/54) ([SavageTiger](https://github.com/SavageTiger))

**Closed issues:**

- Performance [\#38](https://github.com/caxy/php-htmldiff/issues/38)

**Merged pull requests:**

- Differ crashed when comparing regular space character in table column. [\#55](https://github.com/caxy/php-htmldiff/pull/55) ([SavageTiger](https://github.com/SavageTiger))

## [v0.1.2](https://github.com/caxy/php-htmldiff/tree/v0.1.2) (2016-05-25)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/v0.1.1...v0.1.2)

**Implemented enhancements:**

- Diff styling for the demo [\#45](https://github.com/caxy/php-htmldiff/issues/45)
- Implement new List Diffing algorithm for matching [\#49](https://github.com/caxy/php-htmldiff/pull/49) ([jschroed91](https://github.com/jschroed91))
- Improve list diffing for lists with removed list items [\#48](https://github.com/caxy/php-htmldiff/pull/48) ([jschroed91](https://github.com/jschroed91))
- Cleanup CSS and add styles for highlighting differences \(\#45\) [\#47](https://github.com/caxy/php-htmldiff/pull/47) ([jschroed91](https://github.com/jschroed91))
- Add method to clear content on AbstractDiff objects [\#44](https://github.com/caxy/php-htmldiff/pull/44) ([jschroed91](https://github.com/jschroed91))
- Add support for diffing img elements [\#36](https://github.com/caxy/php-htmldiff/pull/36) ([jschroed91](https://github.com/jschroed91))

**Fixed bugs:**

- Fixed HTMLPurifier not using the cache directory [\#53](https://github.com/caxy/php-htmldiff/pull/53) ([SavageTiger](https://github.com/SavageTiger))
- Fix issues with type hints [\#52](https://github.com/caxy/php-htmldiff/pull/52) ([jschroed91](https://github.com/jschroed91))
- Fix issues with unencoded html chars breaking diffing [\#50](https://github.com/caxy/php-htmldiff/pull/50) ([jschroed91](https://github.com/jschroed91))
- Fix issues with spaces being removed in isolated diff tags [\#41](https://github.com/caxy/php-htmldiff/pull/41) ([jschroed91](https://github.com/jschroed91))
- Fixed issue with create call on TableDiff object [\#37](https://github.com/caxy/php-htmldiff/pull/37) ([dbergunder](https://github.com/dbergunder))

**Closed issues:**

- Config object not properly used when using the HtmlDiffBundle service [\#51](https://github.com/caxy/php-htmldiff/issues/51)

**Merged pull requests:**

- Remove unused ListDiff class and rename ListDiffNew to ListDiff [\#43](https://github.com/caxy/php-htmldiff/pull/43) ([jschroed91](https://github.com/jschroed91))
- Run php-cs-fixer on lib directory [\#42](https://github.com/caxy/php-htmldiff/pull/42) ([jschroed91](https://github.com/jschroed91))

## [v0.1.1](https://github.com/caxy/php-htmldiff/tree/v0.1.1) (2016-03-16)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.1.0...v0.1.1)

**Implemented enhancements:**

- Update TableDiff HTMLPurifier Initialization. [\#35](https://github.com/caxy/php-htmldiff/pull/35) ([dbergunder](https://github.com/dbergunder))

**Merged pull requests:**

- Update the README and add additional documentation [\#34](https://github.com/caxy/php-htmldiff/pull/34) ([jschroed91](https://github.com/jschroed91))

## [0.1.0](https://github.com/caxy/php-htmldiff/tree/0.1.0) (2016-03-10)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.1.0-beta.1...0.1.0)

**Implemented enhancements:**

- Detecting link changes [\#28](https://github.com/caxy/php-htmldiff/issues/28)
- Allow the list match threshold percentage \(ListDiff::$listMatchThreshold\) to be configured [\#18](https://github.com/caxy/php-htmldiff/issues/18)
- Allow $isolatedDiffTags to be configured on HtmlDiff [\#13](https://github.com/caxy/php-htmldiff/issues/13)
- Create configuration class for HtmlDiff config options [\#32](https://github.com/caxy/php-htmldiff/pull/32) ([jschroed91](https://github.com/jschroed91))

**Merged pull requests:**

- Allow caching of the calculated diffs using a doctrine cache provider [\#33](https://github.com/caxy/php-htmldiff/pull/33) ([jschroed91](https://github.com/jschroed91))

## [0.1.0-beta.1](https://github.com/caxy/php-htmldiff/tree/0.1.0-beta.1) (2016-02-26)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.14...0.1.0-beta.1)

**Merged pull requests:**

- New Feature: Table Diffing [\#31](https://github.com/caxy/php-htmldiff/pull/31) ([jschroed91](https://github.com/jschroed91))
- Detect link changes to resolve \#28 [\#30](https://github.com/caxy/php-htmldiff/pull/30) ([jschroed91](https://github.com/jschroed91))
- Setup PHPUnit testsuite with basic functional test and a few test cases [\#26](https://github.com/caxy/php-htmldiff/pull/26) ([jschroed91](https://github.com/jschroed91))

## [0.0.14](https://github.com/caxy/php-htmldiff/tree/0.0.14) (2016-02-03)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.13...0.0.14)

**Fixed bugs:**

- Fix HtmlDiff matching logic skipping over single word matches [\#25](https://github.com/caxy/php-htmldiff/pull/25) ([jschroed91](https://github.com/jschroed91))

## [0.0.13](https://github.com/caxy/php-htmldiff/tree/0.0.13) (2016-01-12)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.12...0.0.13)

**Fixed bugs:**

- Misc. list diffing updates and fixes [\#24](https://github.com/caxy/php-htmldiff/pull/24) ([jschroed91](https://github.com/jschroed91))
- Updated list diff class to maintain the tags on lists. [\#23](https://github.com/caxy/php-htmldiff/pull/23) ([adamCaxy](https://github.com/adamCaxy))

## [0.0.12](https://github.com/caxy/php-htmldiff/tree/0.0.12) (2015-11-11)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.11...0.0.12)

**Fixed bugs:**

- feature-list\_diffing-new [\#20](https://github.com/caxy/php-htmldiff/pull/20) ([adamCaxy](https://github.com/adamCaxy))

## [0.0.11](https://github.com/caxy/php-htmldiff/tree/0.0.11) (2015-11-06)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.10...0.0.11)

**Merged pull requests:**

- Feature list diffing new [\#19](https://github.com/caxy/php-htmldiff/pull/19) ([adamCaxy](https://github.com/adamCaxy))

## [0.0.10](https://github.com/caxy/php-htmldiff/tree/0.0.10) (2015-10-21)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.9...0.0.10)

**Fixed bugs:**

- Fix: Updated code so that null is not given in list formatting. [\#17](https://github.com/caxy/php-htmldiff/pull/17) ([adamCaxy](https://github.com/adamCaxy))

## [0.0.9](https://github.com/caxy/php-htmldiff/tree/0.0.9) (2015-10-20)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.8...0.0.9)

**Fixed bugs:**

- Missed an array\_column in ListDiff. Updated to use ArrayColumn function. [\#16](https://github.com/caxy/php-htmldiff/pull/16) ([jschroed91](https://github.com/jschroed91))

## [0.0.8](https://github.com/caxy/php-htmldiff/tree/0.0.8) (2015-10-20)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.7...0.0.8)

**Fixed bugs:**

- Added update for php versions that do not have array\_column as a function. [\#15](https://github.com/caxy/php-htmldiff/pull/15) ([jschroed91](https://github.com/jschroed91))

## [0.0.7](https://github.com/caxy/php-htmldiff/tree/0.0.7) (2015-10-20)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.6...0.0.7)

**Implemented enhancements:**

- Created ListDiff class to handle diffing of lists. [\#14](https://github.com/caxy/php-htmldiff/pull/14) ([adamCaxy](https://github.com/adamCaxy))

## [0.0.6](https://github.com/caxy/php-htmldiff/tree/0.0.6) (2015-09-11)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.5...0.0.6)

**Implemented enhancements:**

- ICC-4313 | ICC-4314 | Replace Special HTML Elements with placeholder tokens and update diffing logic [\#11](https://github.com/caxy/php-htmldiff/pull/11) ([usaqlein](https://github.com/usaqlein))

**Merged pull requests:**

- Feature - html tag isolation [\#12](https://github.com/caxy/php-htmldiff/pull/12) ([jschroed91](https://github.com/jschroed91))

## [0.0.5](https://github.com/caxy/php-htmldiff/tree/0.0.5) (2015-03-03)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.4...0.0.5)

**Implemented enhancements:**

- Support derived classes [\#10](https://github.com/caxy/php-htmldiff/pull/10) ([mkalkbrenner](https://github.com/mkalkbrenner))

## [0.0.4](https://github.com/caxy/php-htmldiff/tree/0.0.4) (2015-01-09)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.3...0.0.4)

**Fixed bugs:**

- Check for empty oldText or newText before processing del or ins in processReplaceOperation [\#9](https://github.com/caxy/php-htmldiff/pull/9) ([jschroed91](https://github.com/jschroed91))

## [0.0.3](https://github.com/caxy/php-htmldiff/tree/0.0.3) (2015-01-08)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.2...0.0.3)

**Implemented enhancements:**

- Add option to insert a space between del and ins tags [\#8](https://github.com/caxy/php-htmldiff/pull/8) ([jschroed91](https://github.com/jschroed91))
- Updated demo to accept input and diff on the fly [\#5](https://github.com/caxy/php-htmldiff/pull/5) ([jschroed91](https://github.com/jschroed91))

## [0.0.2](https://github.com/caxy/php-htmldiff/tree/0.0.2) (2014-08-12)
[Full Changelog](https://github.com/caxy/php-htmldiff/compare/0.0.1...0.0.2)

**Implemented enhancements:**

- Break out HTML content to individual HTML, CSS, JS files [\#6](https://github.com/caxy/php-htmldiff/pull/6) ([mgersten-caxy](https://github.com/mgersten-caxy))

**Fixed bugs:**

- Fix error caused when passing empty array into setSpecialCaseTags [\#7](https://github.com/caxy/php-htmldiff/pull/7) ([jschroed91](https://github.com/jschroed91))

## [0.0.1](https://github.com/caxy/php-htmldiff/tree/0.0.1) (2014-07-31)
**Implemented enhancements:**

- Added static properties for the default config variables [\#4](https://github.com/caxy/php-htmldiff/pull/4) ([jschroed91](https://github.com/jschroed91))
- Added option to group together diffed words in output [\#2](https://github.com/caxy/php-htmldiff/pull/2) ([jschroed91](https://github.com/jschroed91))

**Merged pull requests:**

- Feature nonpartial word diffing [\#3](https://github.com/caxy/php-htmldiff/pull/3) ([jschroed91](https://github.com/jschroed91))



\* *This Change Log was automatically generated by [github_changelog_generator](https://github.com/skywinder/Github-Changelog-Generator)*
