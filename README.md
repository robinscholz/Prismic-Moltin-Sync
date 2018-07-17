# 🌪 Prismic Moltin Sync

PHP helper to sync Prismic data (shop products) to Moltin.

## Setup

1. Install dependencies with composer:
```
$ composer install
```

2. Add a `config.php` file to the root folder
```
<?php
/*
 * Prismic
 */
define('PRISMIC_URL', 'https://your-repository.prismic.io/api/v2');
define('PRISMIC_TOKEN', 'token');
define('PRISMIC_SECRET', 'prismic_webhook_secret');
/*
 * Moltin
 */
define('MOLTIN_ID', 'moltin_client_id');
define('MOLTIN_SECRET', 'moltin_client_secret');
/*
 * Your site metadata
 */
define('SITE_TITLE', 'PrismicMoltinSync');
define('SITE_DESCRIPTION', '');
/*
 * Set to true to display error details
 */
define('DISPLAY_ERROR_DETAILS', true);
```

3. Launch local server (localhost:8000):
```
$ ./serve.sh
```