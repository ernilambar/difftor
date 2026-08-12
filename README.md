ernilambar/difftor
==================

Diff helper - Compare two sources (URLs, directories, or zip files) and generate an HTML diff file.

Quick links: [Using](#using) | [Installing](#installing) | [Development](#development)

## Using

```bash
difftor <old_source> <new_source> [--output-dir=DIR] [--porcelain]
```

Supports comparing:
- Two URLs pointing to zip files
- Two local directories
- Two local zip files
- Mixed combinations (e.g., URL and local directory)

The sources are extracted/prepared to temporary directories (if needed) and an HTML diff
file is generated showing the differences. The HTML file is saved in the system temp
directory (or specified output directory) and can be viewed in a browser.

**ARGUMENTS**

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

**OPTIONS**

	[--output-dir=DIR]
		Output directory for the HTML diff file. Defaults to system temp directory.

	[--porcelain]
		Output only the file path, suitable for parsing.

	[-h|--help]
		Display help for the command.

	[-V|--version]
		Display the application version.

**EXAMPLES**

    # Compare two URLs (zip files)
    $ difftor https://example.com/file1.zip https://example.com/file2.zip

    # Compare two local directories
    $ difftor /path/to/old-folder /path/to/new-folder

    # Compare two local zip files
    $ difftor /path/to/old.zip /path/to/new.zip

    # Mixed: URL and local directory
    $ difftor https://example.com/old.zip /path/to/new-folder

    # Specify output directory
    $ difftor /path/to/old /path/to/new --output-dir=/tmp/diffs

## Installing

Install via Composer globally:

```bash
composer global require ernilambar/difftor
```

Then use the `difftor` command:

```bash
~/.composer/vendor/bin/difftor <old_source> <new_source>
```

Or add `~/.composer/vendor/bin` to your PATH.

### Installing via Phar

Download the latest `difftor.phar` from the [releases page](https://github.com/ernilambar/difftor/releases):

```bash
curl -fLO https://github.com/ernilambar/difftor/releases/latest/download/difftor.phar
chmod +x difftor.phar
sudo mv difftor.phar /usr/local/bin/difftor
difftor --version
```

Then use the `difftor` command:

```bash
difftor <old_source> <new_source>
```

## Development

### Requirements

- PHP 8.3 or higher
- Composer

### Setup

```bash
git clone https://github.com/ernilambar/difftor.git
cd difftor
composer install
```

### Running Tests

```bash
# Run unit tests.
composer phpunit

# Run all tests.
composer test
```

## Copyright and License

This project is licensed under the [MIT](http://opensource.org/licenses/MIT).

2026 &copy; [Nilambar Sharma](https://www.nilambar.net).
