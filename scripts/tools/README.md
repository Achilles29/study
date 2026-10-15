# PHP_CodeSniffer project tool

- Tool: PHP_CodeSniffer 4.0.4 PHAR
- Upstream release: <https://github.com/PHPCSStandards/PHP_CodeSniffer/releases/tag/4.0.4>
- License: BSD-3-Clause (included by the upstream distribution)
- SHA-256: `4b010cd21d8bc8a17e2504792e3c77ef8259126f24caf7eafe7eddef1fa871e9`

The PHAR is run with PHP 8.1 by `scripts/qa-local.ps1`. Source hygiene rules are
defined in the root `phpcs.xml.dist`; tab-based scope indentation for PHP-only
source plus Allman function braces are checked separately by
`phpcs-indent.xml.dist`. CI3-style class/interface/trait names and snake_case
function, method, property, and variable identifiers are checked by
`scripts/check-php-ci3-names.php` on PHP 8.0 and 8.1. The checker has positive
and negative self-tests.
Mixed PHP/HTML views
stay in the source-hygiene scan because scope-indent reports false positives in
three existing views. Do not replace these rules with the PSR-12 default without
checking compatibility with the CodeIgniter 3 conventions and `.editorconfig`.
