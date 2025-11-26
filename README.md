ernilambar/foldiff-command
==========================

Diff helper.



Quick links: [Using](#using) | [Installing](#installing)

## Using

~~~
wp foldiff view <paths> [--porcelain]
~~~

Supports comparing:
- Two URLs pointing to zip files
- Two local directories
- Two local zip files
- Mixed combinations (e.g., URL and local directory)

The sources are extracted/prepared to temporary directories (if needed) and an HTML diff
file is generated showing the differences. The HTML file is saved in the WP-CLI cache
directory and can be viewed in a browser.

**OPTIONS**

	<paths>
		Two paths separated by pipe (|). Each path can be:
		  - A URL pointing to a zip file
		  - A local directory path
		  - A local zip file path
		  First path is the old/original source, second path is the new/modified source.

	[--porcelain]
		Output a single value.

**EXAMPLES**

    # Compare two URLs (zip files)
    $ wp foldiff view "https://example.com/file1.zip|https://example.com/file2.zip"

    # Compare two local directories
    $ wp foldiff view "/path/to/old-folder|/path/to/new-folder"

    # Compare two local zip files
    $ wp foldiff view "/path/to/old.zip|/path/to/new.zip"

    # Mixed: URL and local directory
    $ wp foldiff view "https://example.com/old.zip|/path/to/new-folder"

## Installing

Installing this package requires WP-CLI v2 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install ernilambar/foldiff-command:@stable
```

To install the latest development version of this package, use the following command instead:

```bash
wp package install ernilambar/foldiff-command:dev-main
```


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
