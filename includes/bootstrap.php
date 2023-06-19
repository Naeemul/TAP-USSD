<?php

if (!function_exists('prepend_path_separator')) {
    /**
     * Prepend path separator if needed
     */
    function prepend_path_separator(String $path, String $separator = '/') : String
    {
        return substr($path, 0, 1) != $separator ? $separator . $path : $path;
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path
     */
    function base_path(String $path = '') : String
    {
        $basePath = __DIR__ . '/../';
        
        return $path != '' ? $basePath . prepend_path_separator($path) : $basePath;
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the public path
     */
    function public_path(String $dir = '') : String
    {
        $publicPath = base_path('public');
        
        return $dir != '' ? $publicPath . prepend_path_separator($dir) : $publicPath;
    }
}

if (!function_exists('includes_path')) {
    /**
     * Get the includes path
     */
    function includes_path(String $dir = '') : String
    {
        $includesPath = base_path('includes');
        
        return $dir != '' ? $includesPath . prepend_path_separator($dir) : $includesPath;
    }
}

if (!function_exists('vendor_path')) {
    /**
     * Get the vendor path
     */
    function vendor_path(String $dir = '') : String
    {
        $vendorPath = base_path('vendor');
        
        return $dir != '' ? $vendorPath . prepend_path_separator($dir) : $vendorPath;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump & Die
     */
    function dd(...$args) : void
    {
        foreach ($args as $arg) {
            var_dump($arg);
        }
        
        exit;
    }
}

// Load Composer autoload file
require_once vendor_path('autoload.php'); // Helpers defined above this point CANNOT use Composer packages

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(base_path());
$dotenv->load();

// Include helpers
require_once includes_path('helpers.php');
require_once includes_path('report-helpers.php');

require_once includes_path('session.php');
require_once includes_path('ussdmenu.php');
