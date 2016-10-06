# LeanPackageValidator
[![Build Status](https://secure.travis-ci.org/raphaelstolt/lean-package-validator.png)](http://travis-ci.org/raphaelstolt/lean-package-validator)
[![Version](http://img.shields.io/packagist/v/stolt/lean-package-validator.svg?style=flat)](https://packagist.org/packages/stolt/lean-package-validator)
![PHP Version](http://img.shields.io/badge/php-5.6+-ff69b4.svg)
[![composer.lock available](https://poser.pugx.org/stolt/lean-package-validator/composerlock)](https://packagist.org/packages/stolt/lean-package-validator)

The LeanPackageValidator is an utility tool that validates a project/micro-package for it's `leanness`. A project/micro-package is considered `lean` when it's common repository artifacts won’t be included in release/dist archive files.

## Installation
The LeanPackageValidator CLI should be installed globally through composer.

``` bash
composer global require stolt/lean-package-validator
```

Since the default name of the CLI is quite a mouthful, an alias which can be placed in `~/.aliases`, `~/.zshrc` or the like might come in handy.

```bash
alias lpv='$HOME/.composer/vendor/bin/lean-package-validator $@'
```

The LeanPackageValidator also can be installed locally to a project which allows further utilisation via [Composer scripts](https://getcomposer.org/doc/articles/scripts.md).

``` bash
composer require --dev stolt/lean-package-validator
```

## Usage
Just run the LeanPackageValidator CLI within or against a project/micro-package directory and it will validate the [export-ignore](https://git-scm.com/book/en/v2/Customizing-Git-Git-Attributes#Exporting-Your-Repository) entries present in a `.gitattributes` file against a set of common repository artifacts. If no `.gitattributes` file is present it will suggest to create one.

``` bash
lean-package-validator validate [<directory>]
```

#### Available options
The `--enforce-strict-order` option will enforce a strict order comparison of export-ignores in the .gitattributes file and fail validation if the order differs. Per __default__ the order comparison is done in a non strict fashion.

``` bash
lean-package-validator validate [<directory>] --enforce-strict-order
```

The `--create|-c` option creates an `.gitattributes` file if nonexistent.

``` bash
lean-package-validator validate [<directory>] --create
```

The `--overwrite|-o` option overwrites an existing `.gitattributes` file when there are any `export-ignore` entries missing. Using this option on a directory with a nonexistent `.gitattributes` file implicates the `--create` option.

``` bash
lean-package-validator validate [<directory>] --overwrite
```

The `--glob-pattern` option allows you to overwrite the default pattern\* used to match common repository artifacts. The amount of pattern in the grouping braces is expected to be `>1`. As shown next this utility could thereby also be used for projects (i.e. Python) outside of the PHP ecosystem.

``` bash
lean-package-validator validate [<directory>] --glob-pattern '{.*,*.rst,*.py[cod],dist/}'
```
\* The default pattern is `{.*,*.lock,*.txt,*.rst,*.{md,MD},*.xml,*.yml,box.json,captainhook.json,*.dist.*,{B,b}uild*,{D,d}oc*,{T,t}ool*,{T,t}est*,{S,s}pec*,{E,e}xample*,LICENSE,{{M,m}ake,{B,b}ox,{V,v}agrant,{P,p}hulp}file,RMT}*`.

The `--glob-pattern-file` option allows you to load patterns, which should be used to match the common repository artifacts, from a given file. You can put a `.lpv` file in the repository which will be used per default and overwrite the default pattern\*. The structure of such a glob pattern file can be taken from the [example](example/.lpv) directory.

``` bash
lean-package-validator validate [<directory>] --glob-pattern-file /path/to/glob-pattern-file
```

The `--keep-license` option will allow a license file in the release/dist archive file which is per default ommitted.

``` bash
lean-package-validator validate [<directory>] --keep-license
```

The `--validate-git-archive` option will validate that no common repository artifacts slip into the release/dist archive file. It will do so by creating a `temporary archive` from the current Git `HEAD` and inspecting it's content. With a set `--keep-license` option a license file becomes mandatory and will fail the archive validation if not present.

``` bash
lean-package-validator validate [<directory>] --validate-git-archive
```

## Utilisation via Composer scripts
To avoid that changes coming from contributions or own modifications slip into release/dist archives it might be helpful to use a guarding [Composer script](https://getcomposer.org/doc/articles/scripts.md), which will be available at everyones fingertips.

By adding the following to the project/micro-package its `composer.json` the ` .gitattributes` file can now be easily validated via `composer validate-gitattributes`.

``` json
{
    "scripts": {
        "validate-gitattributes": "lean-package-validator validate"
    },
}
```
Further this Composer script could also be utilised in Travis CI [builds](.travis.yml) similar to the `composer test` script call.

#### Running tests
``` bash
composer lpv:test
```

#### License
This library and it's CLI are licensed under the MIT license. Please see [LICENSE](LICENSE.md) for more details.

#### Changelog
Please see [CHANGELOG](CHANGELOG.md) for more details.

#### Contributing
Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for more details.
