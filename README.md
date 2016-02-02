php-htmldiff
=======
*A library for comparing two HTML files/snippets and highlighting the differences using simple HTML.*

This HTML Diff implementation is a PHP port of the ruby implementation found at https://github.com/myobie/htmldiff.

This is also available in C# at https://github.com/Rohland/htmldiff.net.

License
-------
php-htmldiff is available under [GNU General Public License, version 2] (http://www.gnu.org/licenses/gpl-2.0.html).

Differences from rashid2538/php-htmldiff
========================================
## Code Styling and Clean-up
* Added namespaces, split up classes to their own files, some code styling changes

## Enhancements
* Allow the specialCaseOpeningTags and specialCaseClosingTags properties to be modified by passing an array into the constructor or using set/add/remove functions
* Updated the demo to accept input and diff via AJAX
* Added static properties for the default config variables

## Bug Fixes
* Fixed an index out of range bug (may have been fixed on the original repo since): https://github.com/caxy/php-htmldiff/commit/c9ba1fab6777cd47427477f8d747293bb01ef1e8
* Check for empty oldText or newText before processing del or ins in processReplaceOperation function

## New Features
#### Isolated Diffing of certain HTML elements
This is the one of the largest changes from the original repository.

See the release notes for tag 0.0.6 for more information: https://github.com/caxy/php-htmldiff/releases/tag/0.0.6

#### List Diffing
This is the latest new feature (as of last week-ish), and may need some tweaks still. It is similar to the Isolated Diffing feature, but specifically for HTML lists.

More information is to come on this, and there will definitely be some tweaks and configuration options added for this feature. Currently there is no easy way to enable/disable the feature, so if you're having issues with it I suggest using the 0.0.6 or earlier release.

#### New option to group together diffed words by not matching on whitespace-only. Option is enabled by default.
This was a specific requirement for an application we use this library for. The original library would replace single words at a time, but enabling this feature will group replacements instead. See example below.

Old Text
> testing some text here and there

New Text
> testing other words here and there

With $groupDiffs = false (original functionality)
> testing <del>some</del><ins>other</ins> <del>text</del><ins>words</ins> here and there

With $groupDiffs = true (new feature)
> testing <del>some text</del><ins>other words</ins> here and there

#### Change diffing to strike through entire words/numbers if they contain periods or commas within the word
This change introduced a new property `$specialCaseChars`, which defaults to the following characters: `.` `,` `(` `)` `'`

This feature can be "disabled" by simply setting the $specialCaseChars to an empty array i.e. `$diff->setSpecialCaseChars(array())`

In the original library, special characters are treated as their own "words" even if they are in the middle of a word. This causes weird things to happen when diffing numbers that have a comma or a period in the middle of the number.

For example, diffing `10,000.50` against `11,100.75` gives you:

Original Functionality:
> <del class="diffmod">10</del><ins class="diffmod">11</ins>,<del class="diffmod">000</del><ins class="diffmod">100</ins>.<del class="diffmod">50</del><ins class="diffmod">75</ins>

This is very difficult to read, so the new feature allows you to add `.` and `,` to the $specialCaseChars array in order to get output that looks like:
> <del class="diffmod">10,000.50</del><ins class="diffmod">11,100.75</ins>

Note: It will *not* treat the specialCaseChars as part of the word if it is at the beginning or end of the word, so normal periods or commas at the end of words will still be diffed like the original.

#### Added option to insert a space between `<del>` and `<ins>` tags. Disabled by default.
This was a requirement for one our applications that uses this library.

New property `$insertSpaceInReplace` was added, and setting it to true will simply add a space between the `<del>` and `<ins>` tags in replace operations, which was requested for easier reading.

Enable it by calling `$diff->setInsertSpaceInReplace(true);`

Original Functionality
> <del>Old</del><ins>New</ins>

New Functionality
> <del>Old</del> <ins>New</ins>

## Upcoming Features (someday)
* Table Diffing (similar to the list diffing updates) - this feature was started a while back, but put on hold.
