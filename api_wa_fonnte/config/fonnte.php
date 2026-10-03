<?php
declare(strict_types=1);

// Set FONNTE_API_TOKEN in the PHP/Apache environment; never expose it to the browser.
if (!defined('FONNTE_API_TOKEN')) {
    define('FONNTE_API_TOKEN', getenv('FONNTE_API_TOKEN') ?: 'ZJVBgg6MD6JsM8FLoyid');
}

if (!defined('FONNTE_API_ENDPOINT')) {
    define('FONNTE_API_ENDPOINT', 'https://api.fonnte.com/send');
}
