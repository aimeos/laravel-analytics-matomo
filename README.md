# laravel-analytics-matomo

Matomo driver for Laravel Analytics bridge.

For API access, you need the **API token** from Matomo to authenticate API requests
and access data programmatically. Follow these steps to retrieve it.

## 1. Log in to Matomo

1. Open your Matomo instance in a web browser.
2. Enter your **username** and **password** to log in.

## 2. Access Your User Settings

1. Click on your **username** in the top-right corner of the dashboard.
2. Select **“Settings”** (or **“Personal”** depending on Matomo version).

## 3. Find the API Token

1. In the **Settings** page, look for the section labeled **“API”** or **“API Access”**.
2. You will see a field called **“Your API token”** or **`token_auth`**.
3. Copy the token. It typically looks like a long alphanumeric string.

## 4. Configure in Analytics Bridge

The `./config/analytics-bridge.php` file already contains:

```php
return [
    'default' => env('ANALYTICS_DRIVER'),

    'drivers' => [
        'matomo' => [
            'url' => env('MATOMO_URL'),
            'token' => env('MATOMO_TOKEN'),
            'siteid' => env('MATOMO_SITEID'),
        ],
        /* ... */
    ],
    /* ... */
];
```

Add the required key/value pairs to your `.env` file:

```
ANALYTICS_DRIVER="matomo"
MATOMO_URL="..." # including "https://"
MATOMO_TOKEN="..."
MATOMO_SITEID="1" # or other value if you have multiple sites
```
