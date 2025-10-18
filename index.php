<?php
namespace Exodus4D\Pathfinder;

use Exodus4D\Pathfinder\Lib;

session_name('pathfinder_session');

$composerAutoloader = 'vendor/autoload.php';
if(file_exists($composerAutoloader)){
    require_once($composerAutoloader);
}else{
    die("Couldn't find '$composerAutoloader`. Did you run `composer install`?");
}

$f3 = \Base::instance();
$f3->set('NAMESPACE', __NAMESPACE__);

// load main config
$f3->config('app/config.ini', true);

// load environment dependent config
Lib\Config::instance($f3);

// PHP 8.4 compatibility: Override F3's error handler to suppress baselined warnings
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // PHP 8.4 warnings that are in PHPStan baseline - log but don't crash
    if ($errno === E_WARNING && (
        str_contains($errstr, 'Undefined array key') ||
        str_contains($errstr, 'Undefined variable') ||
        str_contains($errstr, 'Trying to access array offset on') ||
        str_contains($errstr, 'array offset on null')
    )) {
        // Log to PHP-FPM error log but don't crash
        error_log("PHP 8.4 Warning (suppressed): {$errstr} in {$errfile}:{$errline}");
        return true; // Suppress the error
    }

    // Let all other errors go through normal F3 handling
    return false;
});

// initiate cron-jobs
Lib\Cron::instance();

$f3->run();