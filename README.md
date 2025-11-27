ernilambar/difftor-command
==========================

Diff helper.



Quick links: [Using](#using) | [Installing](#installing)

## Using

~~~
wp difftor <old_source> <new_source> [--porcelain]
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

	<old_source>
		Path to the old/original source. Can be:
		  - A URL pointing to a zip file
		  - A local directory path
		  - A local zip file path

	<new_source>
		Path to the new/modified source. Can be:
		  - A URL pointing to a zip file
		  - A local directory path
		  - A local zip file path

	[--porcelain]
		Output a single value.

**EXAMPLES**

    # Compare two URLs (zip files)
    $ wp difftor https://example.com/file1.zip https://example.com/file2.zip

    # Compare two local directories
    $ wp difftor /path/to/old-folder /path/to/new-folder

    # Compare two local zip files
    $ wp difftor /path/to/old.zip /path/to/new.zip

    # Mixed: URL and local directory
    $ wp difftor https://example.com/old.zip /path/to/new-folder

## Installing

Installing this package requires WP-CLI v2 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install ernilambar/difftor-command:@stable
```

To install the latest development version of this package, use the following command instead:

```bash
wp package install ernilambar/difftor-command:dev-main
```


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
