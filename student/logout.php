
<?php
// student/logout.php

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

// Clear student session
unset($_SESSION['student_id']);
unset($_SESSION['student_name']);
unset($_SESSION['student_email']);
unset($_SESSION['student_logged_in']);

foreach (array_keys($_SESSION) as $key) {
    if (strpos($key, 'has_seen_rejected_') === 0) {
        unset($_SESSION[$key]);
    }
}

// If no admin session, destroy completely
if (!isset($_SESSION['admin_id'])) {
    session_regenerate_id(true);

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
} else {
    session_regenerate_id(true);
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

header("Location: index.php?logged_out=1");
exit();
