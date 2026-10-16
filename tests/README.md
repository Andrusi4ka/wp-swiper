# Tests

PHPUnit tests with the WordPress test suite ([wp-phpunit](https://github.com/wp-phpunit/wp-phpunit)): the settings schema and sanitizing, the migrations of older saved sliders, the markup and config of every effect and mode, saving and the preview's checks.

GitHub Actions runs them on every push to `main` and pull request (`.github/workflows/tests.yml`: PHP 7.4 with WordPress 6.0 and 7.1, PHP 8.3 with WordPress 7.1), and before every release: a tag whose tests fail gets no release.

## Running them locally

Needs PHP 7.4+ with `mysqli`, Composer, WP-CLI and a MySQL database **for the tests only** (they drop and recreate its tables).

```
composer install
wp core download --version=7.1 --path=/tmp/wordpress --skip-content
WP_CORE_DIR=/tmp/wordpress WP_TESTS_DB_HOST=localhost tests/vendor/bin/phpunit
```

The environment variables `tests/wp-tests-config.php` reads:

| Variable | Default |
|---|---|
| `WP_CORE_DIR` | `/tmp/wordpress` |
| `WP_TESTS_DB_NAME` | `wp_swiper_tests` |
| `WP_TESTS_DB_USER` | `root` |
| `WP_TESTS_DB_PASSWORD` | (empty) |
| `WP_TESTS_DB_HOST` | `localhost` |

## Writing tests

Files named `test-*.php` in this folder. `WP_Swiper_TestCase` (`class-wp-swiper-testcase.php`) creates images, videos and sliders without real files and reads the config, classes and style of the markup a slider renders.
