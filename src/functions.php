<?php
/**
 * Helper functions for the application
 */

require_once __DIR__ . '/../auth.php';

/**
 * Escape output for HTML
 * @param string $string
 * @return string
 */
function escape(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Redirect to a URL
 * @param string $url
 * @return void
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}
