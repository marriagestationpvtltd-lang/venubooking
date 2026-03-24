<?php
/**
 * Core Functions for Venue Booking System
 */

require_once __DIR__ . '/db.php';

/**
 * Filename validation pattern for image uploads
 * Requires: alphanumeric name, single separators (._-), and file extension
 * Blocks: consecutive separators, leading/trailing separators, special chars
 */
define('SAFE_FILENAME_PATTERN', '/^[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z0-9]+$/');

/**
 * Default service quantity for user-selected services
 */
define('DEFAULT_SERVICE_QUANTITY', 1);

/**
 * Service type constants
 */
define('USER_SERVICE_TYPE', 'user');
define('ADMIN_SERVICE_TYPE', 'admin');

/**
 * Admin service defaults
 * Admin services don't reference the master services table, so service_id is 0
 */
define('ADMIN_SERVICE_NO_REF_ID', 0);
define('ADMIN_SERVICE_DEFAULT_CATEGORY', '');

/**
 * Category value used when a predefined service package is added to a booking
 */
define('PACKAGE_SERVICE_CATEGORY', 'package');

/**
 * Database column names for admin services feature
 * These constants ensure consistency across the codebase
 */
define('BOOKING_SERVICE_ADDED_BY_COLUMN', 'added_by');
define('BOOKING_SERVICE_QUANTITY_COLUMN', 'quantity');

/**
 * Sanitize input to prevent XSS
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Format file size in human-readable format
 * @param int $bytes File size in bytes
 * @return string Formatted file size (e.g., "1.5 GB", "250 MB")
 */
function formatFileSize($bytes) {
    if ($bytes === null || $bytes === 0) {
        return '0 B';
    }
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get a Font Awesome icon class for a file based on its extension.
 * Returns a string like "fa-file-pdf text-danger" suitable for use in:
 *   <i class="fas <?= getFileTypeIcon($ext) ?>"></i>
 *
 * @param string $ext  Lowercase file extension (e.g. 'pdf', 'zip')
 * @return string      Icon class string
 */
function getFileTypeIcon($ext) {
    $map = [
        'pdf'  => 'fa-file-pdf text-danger',
        'doc'  => 'fa-file-word text-primary',
        'docx' => 'fa-file-word text-primary',
        'xls'  => 'fa-file-excel text-success',
        'xlsx' => 'fa-file-excel text-success',
        'ppt'  => 'fa-file-powerpoint text-warning',
        'pptx' => 'fa-file-powerpoint text-warning',
        'zip'  => 'fa-file-archive text-secondary',
        'rar'  => 'fa-file-archive text-secondary',
        '7z'   => 'fa-file-archive text-secondary',
        'tar'  => 'fa-file-archive text-secondary',
        'gz'   => 'fa-file-archive text-secondary',
        'mp4'  => 'fa-file-video text-info',
        'mov'  => 'fa-file-video text-info',
        'avi'  => 'fa-file-video text-info',
        'webm' => 'fa-file-video text-info',
        'mkv'  => 'fa-file-video text-info',
        'mp3'  => 'fa-file-audio text-info',
        'wav'  => 'fa-file-audio text-info',
        'txt'  => 'fa-file-alt text-muted',
        'csv'  => 'fa-file-csv text-success',
    ];
    return isset($map[$ext]) ? $map[$ext] : 'fa-file text-secondary';
}


/**
 * Detect the MIME type of a file without throwing exceptions.
 *
 * Tries, in order:
 *  1. PHP's finfo extension (most accurate).
 *  2. mime_content_type() built-in (available on most hosts).
 *  3. Extension-based lookup table (last-resort fallback).
 *
 * Error-suppression operators (@) are used on all calls so that a
 * misconfigured extension or an unreadable magic database never causes a
 * PHP warning or a TypeError (e.g. finfo_file() receiving false in PHP 8).
 *
 * Always returns a non-empty string — never throws and never returns false —
 * so callers can safely pass the result directly into a Content-Type header.
 *
 * @param string $file_path Absolute path to the file.
 * @return string MIME type string.
 */
function detectMimeType($file_path) {
    // 1. finfo (preferred – most accurate)
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $file_path);
            @finfo_close($finfo);
            if ($mime !== false && $mime !== '') {
                return $mime;
            }
        }
    }

    // 2. mime_content_type()
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($file_path);
        if ($mime !== false && $mime !== '') {
            return $mime;
        }
    }

    // 3. Extension-based fallback
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_map = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
        'tiff' => 'image/tiff',
        'tif'  => 'image/tiff',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        'svg'  => 'image/svg+xml',
        'mp4'  => 'video/mp4',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'wmv'  => 'video/x-ms-wmv',
        'webm' => 'video/webm',
        'mkv'  => 'video/x-matroska',
        'mpg'  => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        '3gp'  => 'video/3gpp',
        'm4v'  => 'video/x-m4v',
        'ogg'  => 'video/ogg',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'aac'  => 'audio/aac',
        'flac' => 'audio/flac',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
        'rar'  => 'application/x-rar-compressed',
        'tar'  => 'application/x-tar',
        'gz'   => 'application/gzip',
        '7z'   => 'application/x-7z-compressed',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
    ];
    return isset($mime_map[$ext]) ? $mime_map[$ext] : 'application/octet-stream';
}


function getValueOrDefault($value, $default = 'N/A') {
    // Check for null or empty string
    if (is_null($value) || $value === '') {
        return $default;
    }
    // Check for whitespace-only strings
    if (is_string($value) && trim($value) === '') {
        return $default;
    }
    return $value;
}

/**
 * Convert a Gregorian (AD) date to Bikram Sambat (BS) Nepali date string.
 *
 * Returns a string in the format "Falgun 21, 2082 BS". Falls back to the
 * standard English date format when the date is outside the supported BS
 * year range (2000–2090).
 *
 * @param string $gregorian_date Date string (YYYY-MM-DD or any strtotime-compatible format)
 * @return string Nepali BS date (e.g., "Falgun 21, 2082 BS") or English date as fallback
 */
function convertToNepaliDate($gregorian_date) {
    $timestamp = strtotime($gregorian_date);
    if ($timestamp === false) {
        return (string)$gregorian_date;
    }

    $ad_year  = (int) date('Y', $timestamp);
    $ad_month = (int) date('n', $timestamp);
    $ad_day   = (int) date('j', $timestamp);

    // Number of days in each month for BS years 2000–2090.
    // Index 0 = Baishakh (month 1) … index 11 = Chaitra (month 12).
    $bs_month_data = [
        2000 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2001 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2002 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2003 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2004 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2005 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2006 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2007 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2008 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2009 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2010 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2011 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2012 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2013 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2014 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2015 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2016 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2017 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2018 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2019 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2020 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2021 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2022 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2023 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2024 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2025 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2026 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2027 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2028 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2029 => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        2030 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2031 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2032 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2033 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2034 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2035 => [30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2036 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2037 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2038 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2039 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2040 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2041 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2042 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2043 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2044 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2045 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2046 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2047 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2048 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2049 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2050 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2051 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2052 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2053 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2054 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2055 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 28, 30, 30],
        2056 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2057 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2058 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2059 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2060 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2061 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2062 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2063 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2064 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2065 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2066 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2067 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2068 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2069 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2070 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2071 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2072 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2073 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2074 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2075 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2076 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2077 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2078 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2079 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 30],
        2080 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2081 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 31],
        2082 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2083 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2084 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2085 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2086 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2087 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2088 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2089 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2090 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    ];

    // Reference point: BS 2000 Baishakh 1 = AD 1943 April 16.
    // This offset was derived by back-calculating from five verified Nepal new-year
    // dates (BS 2078–2082) using the lookup table above and then correcting the
    // BS 2079 and BS 2081 Chaitra lengths to match the published Rashtriya Panchanga.
    $ref   = new DateTime('1943-04-16');
    $today = new DateTime(sprintf('%04d-%02d-%02d', $ad_year, $ad_month, $ad_day));
    $diff_days = (int) $ref->diff($today)->days;
    if ($today < $ref) {
        // Date is before the supported range; fall back to English
        return date('F d, Y', $timestamp);
    }

    $bs_year  = 2000;
    $bs_month = 1;
    $bs_day   = 1;

    while ($diff_days > 0) {
        if (!isset($bs_month_data[$bs_year])) {
            // Date is beyond the supported BS year range; fall back to English
            return date('F d, Y', $timestamp);
        }
        $days_in_current_month = $bs_month_data[$bs_year][$bs_month - 1];
        $remaining_in_month    = $days_in_current_month - $bs_day;

        if ($diff_days <= $remaining_in_month) {
            $bs_day   += $diff_days;
            $diff_days = 0;
        } else {
            $diff_days -= ($remaining_in_month + 1);
            $bs_day    = 1;
            $bs_month++;
            if ($bs_month > 12) {
                $bs_month = 1;
                $bs_year++;
            }
        }
    }

    $month_names = [
        1  => 'Baishakh',
        2  => 'Jestha',
        3  => 'Ashadh',
        4  => 'Shrawan',
        5  => 'Bhadra',
        6  => 'Ashwin',
        7  => 'Kartik',
        8  => 'Mangsir',
        9  => 'Poush',
        10 => 'Magh',
        11 => 'Falgun',
        12 => 'Chaitra',
    ];

    return sprintf('%s %d, %d BS', $month_names[$bs_month], $bs_day, $bs_year);
}

/**
 * Format number with safe default handling
 */
function formatNumber($value, $decimals = 2, $default = 0) {
    $num = floatval($value);
    if ($num === 0.0 && $value !== '0' && $value !== 0) {
        return number_format(floatval($default), $decimals);
    }
    return number_format($num, $decimals);
}

/**
 * Validate required field
 */
function validateRequired($value, $fieldName = 'Field') {
    if (is_null($value) || trim($value) === '') {
        return ['valid' => false, 'error' => "$fieldName is required"];
    }
    return ['valid' => true];
}

/**
 * Validate email format
 */
function validateEmailFormat($email) {
    if (empty($email)) {
        return ['valid' => true]; // Empty is ok if not required
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => 'Invalid email format'];
    }
    return ['valid' => true];
}

/**
 * Validate phone number
 */
function validatePhoneNumber($phone) {
    if (empty($phone)) {
        return ['valid' => false, 'error' => 'Phone number is required'];
    }
    // Remove spaces and special characters
    $cleaned = preg_replace('/[\s().\-]/', '', $phone);
    // Accept 7-15 digits (supports Nepali landlines from 7 digits and mobiles/international up to 15)
    if (!preg_match('/^\+?\d{7,15}$/', $cleaned)) {
        return ['valid' => false, 'error' => 'Please enter a valid phone number (7 or more digits)'];
    }
    return ['valid' => true];
}

/**
 * Return the default start and end times for a given shift.
 *
 * @param  string $shift  One of: morning, afternoon, evening, fullday
 * @return array          ['start' => 'HH:MM', 'end' => 'HH:MM']
 */
function getShiftDefaultTimes($shift) {
    $defaults = [
        'morning'   => ['start' => '06:00', 'end' => '12:00'],
        'afternoon' => ['start' => '12:00', 'end' => '18:00'],
        'evening'   => ['start' => '18:00', 'end' => '23:00'],
        'fullday'   => ['start' => '06:00', 'end' => '23:00'],
    ];
    return $defaults[$shift] ?? ['start' => '', 'end' => ''];
}

/**
 * Format a TIME string (HH:MM:SS or HH:MM) to 12-hour display (e.g. "06:00 AM").
 *
 * @param  string|null $time
 * @return string
 */
function formatBookingTime($time) {
    if (empty($time)) return '';
    return date('h:i A', strtotime($time));
}

/**
 * Build the "Shift / Time" display string used in WhatsApp messages.
 *
 * Uses the booking's actual start/end times when available. When either is
 * absent (e.g. for bookings created before the time columns were added), the
 * function fills only the missing piece from the default time range for the
 * booking's shift so that every WhatsApp message always contains a specific
 * time window while preserving any booked availability times.
 *
 * @param  array  $booking  Booking row containing 'shift', 'start_time', 'end_time'
 * @return string           e.g. "Evening (06:00 PM – 11:00 PM)"
 */
function getBookingShiftTimeDisplay($booking) {
    $display = ucfirst(strip_tags($booking['shift'] ?? ''));
    $start   = $booking['start_time'] ?? '';
    $end     = $booking['end_time']   ?? '';

    // Fall back to shift defaults only for missing time parts
    if (empty($start) || empty($end)) {
        $defaults = getShiftDefaultTimes($booking['shift'] ?? '');
        if (empty($start)) {
            $start = $defaults['start'];
        }
        if (empty($end)) {
            $end = $defaults['end'];
        }
    }

    if (!empty($start) && !empty($end)) {
        $display .= ' (' . formatBookingTime($start) . ' – ' . formatBookingTime($end) . ')';
    }

    return $display;
}

/**
 * Generate HTML <option> elements for a time dropdown.
 *
 * Options span 00:00–23:30 in 30-minute intervals.
 * Values are stored as "HH:MM" (24-hour); labels are shown in 12-hour AM/PM format.
 *
 * @param  string $selected  Currently selected time in "HH:MM" or "HH:MM:SS" format
 * @return string            HTML <option> tags (safe to echo inside a <select>)
 */
function generateTimeOptions($selected = '') {
    // Normalize to "HH:MM" so comparison works regardless of seconds.
    // Accept only valid HH:MM values to prevent any injection through the
    // $selected parameter (values are matched against a known safe list).
    if (!empty($selected)) {
        $selected = substr($selected, 0, 5);
        // Discard anything that does not look like a time value
        if (!preg_match('/^\d{2}:\d{2}$/', $selected)) {
            $selected = '';
        }
    }
    $html = '<option value="">-- Select Time --</option>';
    for ($h = 0; $h < 24; $h++) {
        foreach ([0, 30] as $m) {
            $value = sprintf('%02d:%02d', $h, $m);
            $label = date('h:i A', mktime($h, $m, 0));
            $sel   = ($value === $selected) ? ' selected' : '';
            $html .= "<option value=\"{$value}\"{$sel}>{$label}</option>";
        }
    }
    return $html;
}

/**
 * Derive a shift label from start/end times.
 *
 * The shift is used only as a display label and for backward compatibility
 * with existing bookings. The actual availability check uses precise time
 * overlap detection, not this derived shift.
 *
 * @param  string $start_time  "HH:MM" or "HH:MM:SS"
 * @param  string $end_time    "HH:MM" or "HH:MM:SS"
 * @return string  One of: morning | afternoon | evening | fullday
 */
function deriveShiftFromTimes($start_time, $end_time) {
    $s = (int)substr($start_time, 0, 2) * 60 + (int)substr($start_time, 3, 2);
    $e = (int)substr($end_time,   0, 2) * 60 + (int)substr($end_time,   3, 2);

    // Morning: entirely within 00:00–12:00
    if ($e <= 12 * 60) return 'morning';
    // Afternoon: starts at or after noon, ends by 18:00
    if ($s >= 12 * 60 && $e <= 18 * 60) return 'afternoon';
    // Evening: starts at or after 18:00
    if ($s >= 18 * 60) return 'evening';
    // Everything else is treated as a full-day or multi-shift slot
    return 'fullday';
}

/**
 * Check if a specific hall time slot is available for a given date.
 *
 * Uses the booking_time_slots junction table (precise per-slot check) when
 * available, then falls back to a time-overlap check against legacy bookings
 * that were created before the junction table existed.
 *
 * @param int    $hall_time_slot_id  hall_time_slots.id
 * @param int    $hall_id
 * @param string $date               YYYY-MM-DD
 * @return bool  TRUE if the slot is available, FALSE if already booked
 */
function checkIndividualSlotAvailability($hall_time_slot_id, $hall_id, $date) {
    $db = getDB();

    // 1. Check via the junction table (covers new multi-slot bookings)
    $stmt1 = $db->prepare(
        "SELECT COUNT(*) AS cnt
           FROM booking_time_slots bts
           JOIN bookings b ON b.id = bts.booking_id
          WHERE bts.hall_time_slot_id = ?
            AND b.event_date          = ?
            AND b.booking_status     != 'cancelled'"
    );
    $stmt1->execute([$hall_time_slot_id, $date]);
    if ((int)$stmt1->fetch()['cnt'] > 0) return false;

    // 2. Fallback: time-overlap check for legacy bookings that have no junction records
    $slot_stmt = $db->prepare("SELECT start_time, end_time FROM hall_time_slots WHERE id = ?");
    $slot_stmt->execute([$hall_time_slot_id]);
    $slot = $slot_stmt->fetch();
    if (!$slot) return false;

    $stmt2 = $db->prepare(
        "SELECT COUNT(*) AS cnt
           FROM bookings b
      LEFT JOIN booking_time_slots bts ON bts.booking_id = b.id
          WHERE b.hall_id          = ?
            AND b.event_date       = ?
            AND b.booking_status  != 'cancelled'
            AND b.start_time       < ?
            AND b.end_time         > ?
            AND bts.id IS NULL"
    );
    $stmt2->execute([$hall_id, $date, $slot['end_time'], $slot['start_time']]);
    return (int)$stmt2->fetch()['cnt'] === 0;
}

/**
 * Check if a hall has a time overlap with an existing booking.
 *
 * Two time ranges overlap when one starts before the other ends.
 *
 * @param int    $hall_id
 * @param string $date        YYYY-MM-DD
 * @param string $start_time  HH:MM or HH:MM:SS
 * @param string $end_time    HH:MM or HH:MM:SS
 * @return bool  TRUE if the hall is available (no overlap), FALSE if booked
 */
function checkTimeSlotAvailability($hall_id, $date, $start_time, $end_time) {
    $db = getDB();

    $sql = "SELECT COUNT(*) AS cnt FROM bookings
            WHERE hall_id = ?
              AND event_date = ?
              AND booking_status != 'cancelled'
              AND start_time < ?
              AND end_time   > ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$hall_id, $date, $end_time, $start_time]);
    $result = $stmt->fetch();
    return (int)$result['cnt'] === 0;
}

/**
 * Check if hall is available for booking.
 *
 * When explicit start/end times are provided the check uses time-overlap
 * detection (two bookings conflict when their time windows intersect on the
 * same date).  When no times are provided the legacy shift-based check is
 * used so that existing code paths that have not yet been migrated continue
 * to work without errors.
 *
 * @param int         $hall_id
 * @param string      $date        YYYY-MM-DD
 * @param string      $shift       morning|afternoon|evening|fullday
 * @param string|null $start_time  HH:MM  (optional)
 * @param string|null $end_time    HH:MM  (optional)
 * @return bool
 */
function checkHallAvailability($hall_id, $date, $shift, $start_time = null, $end_time = null) {
    if (!empty($start_time) && !empty($end_time)) {
        return checkTimeSlotAvailability($hall_id, $date, $start_time, $end_time);
    }

    // Legacy shift-based check (kept for backward compatibility)
    $db = getDB();
    $sql = "SELECT COUNT(*) as count FROM bookings
            WHERE hall_id = ?
              AND event_date = ?
              AND shift = ?
              AND booking_status != 'cancelled'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$hall_id, $date, $shift]);
    $result = $stmt->fetch();
    return (int)$result['count'] === 0;
}

/**
 * Get all time slots defined for a hall.
 *
 * @param int  $hall_id
 * @param bool $active_only  When TRUE only 'active' slots are returned
 * @return array
 */
function getHallTimeSlots($hall_id, $active_only = true) {
    $db = getDB();
    try {
        $sql = "SELECT * FROM hall_time_slots WHERE hall_id = ?";
        if ($active_only) {
            $sql .= " AND status = 'active'";
        }
        $sql .= " ORDER BY start_time ASC, id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$hall_id]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('getHallTimeSlots() error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Return available (not-yet-booked) time slots for a hall on a given date.
 *
 * @param int    $hall_id
 * @param string $date   YYYY-MM-DD
 * @return array  Each element is a hall_time_slots row plus 'available' => bool
 */
function getAvailableTimeSlotsForHall($hall_id, $date) {
    $slots = getHallTimeSlots($hall_id);
    foreach ($slots as &$slot) {
        $slot['available'] = checkIndividualSlotAvailability(
            $slot['id'],
            $hall_id,
            $date
        );
    }
    unset($slot);
    return $slots;
}

/**
 * Get booking counts grouped by date for a given date range
 *
 * @param string $start_date Start date inclusive (YYYY-MM-DD, AD/Gregorian)
 * @param string $end_date   End date inclusive (YYYY-MM-DD, AD/Gregorian)
 * @return array Associative array mapping AD date strings to booking counts,
 *               e.g. ['2024-04-14' => 3, '2024-04-20' => 1]
 */
function getBookingCountsByDate($start_date, $end_date) {
    $db = getDB();

    $sql = "SELECT event_date, COUNT(*) as booking_count
            FROM bookings
            WHERE event_date BETWEEN ? AND ?
            AND booking_status != 'cancelled'
            GROUP BY event_date
            ORDER BY event_date";

    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['event_date']] = (int)$row['booking_count'];
    }

    return $counts;
}

/**
 * Generate unique booking number
 */
function generateBookingNumber() {
    $db = getDB();
    $date = date('Ymd');
    $prefix = 'BK-' . $date . '-';
    
    $sql = "SELECT booking_number FROM bookings 
            WHERE booking_number LIKE ? 
            ORDER BY booking_number DESC LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$prefix . '%']);
    $result = $stmt->fetch();
    
    if ($result) {
        $lastNumber = intval(substr($result['booking_number'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Calculate booking total
 *
 * @param int|null   $hall_id
 * @param array      $menus
 * @param int        $guests
 * @param array      $services
 * @param array      $selected_designs
 * @param array      $packages
 * @param float|null $slot_price_override  When a time slot has a price override,
 *                                          pass it here to replace the hall base price.
 * @return array
 */
function calculateBookingTotal($hall_id, $menus, $guests, $services = [], $selected_designs = [], $packages = [], $slot_price_override = null) {
    $db = getDB();
    
    // Get hall price (custom venues have id=0 and no price in DB)
    $hall_price = 0;
    if ($slot_price_override !== null) {
        $hall_price = (float)$slot_price_override;
    } elseif (!empty($hall_id)) {
        $stmt = $db->prepare("SELECT base_price FROM halls WHERE id = ?");
        $stmt->execute([$hall_id]);
        $hall = $stmt->fetch();
        $hall_price = $hall ? $hall['base_price'] : 0;
    }
    
    // Calculate menu total
    $menu_total = 0;
    if (!empty($menus)) {
        $placeholders = str_repeat('?,', count($menus) - 1) . '?';
        $stmt = $db->prepare("SELECT SUM(price_per_person) as total FROM menus WHERE id IN ($placeholders)");
        $stmt->execute($menus);
        $result = $stmt->fetch();
        $menu_price_per_person = $result['total'] ?? 0;
        $menu_total = $menu_price_per_person * $guests;
    }
    
    // Calculate services total (regular services without sub-service flows)
    $services_total = 0;
    if (!empty($services)) {
        $placeholders = str_repeat('?,', count($services) - 1) . '?';
        $stmt = $db->prepare("SELECT SUM(price) as total FROM additional_services WHERE id IN ($placeholders)");
        $stmt->execute($services);
        $result = $stmt->fetch();
        $services_total = $result['total'] ?? 0;
    }

    // Add prices from selected designs (sub-service design selections)
    if (!empty($selected_designs)) {
        try {
            $design_ids = array_map('intval', array_values($selected_designs));
            $placeholders = implode(',', array_fill(0, count($design_ids), '?'));
            $stmt = $db->prepare("SELECT COALESCE(SUM(price), 0) as total FROM service_designs WHERE id IN ($placeholders)");
            $stmt->execute($design_ids);
            $result = $stmt->fetch();
            $services_total += (float)($result['total'] ?? 0);
        } catch (\Throwable $designErr) {
            // If service_designs table is missing or inaccessible, skip design prices
            // rather than aborting the entire calculation.
            error_log('calculateBookingTotal: design price query failed: ' . $designErr->getMessage());
        }
    }

    // Add prices from user-selected service packages
    if (!empty($packages)) {
        try {
            $pkg_ids = array_map('intval', $packages);
            $placeholders = implode(',', array_fill(0, count($pkg_ids), '?'));
            $stmt = $db->prepare("SELECT COALESCE(SUM(price), 0) as total FROM service_packages WHERE id IN ($placeholders) AND status = 'active'");
            $stmt->execute($pkg_ids);
            $result = $stmt->fetch();
            $services_total += (float)($result['total'] ?? 0);
        } catch (\Throwable $pkgErr) {
            error_log('calculateBookingTotal: package price query failed: ' . $pkgErr->getMessage());
        }
    }
    
    // Calculate totals - get tax rate from database settings
    $tax_rate = floatval(getSetting('tax_rate', '13'));
    $subtotal = $hall_price + $menu_total + $services_total;
    $tax_amount = $subtotal * ($tax_rate / 100);
    $grand_total = $subtotal + $tax_amount;
    
    return [
        'hall_price' => $hall_price,
        'menu_total' => $menu_total,
        'services_total' => $services_total,
        'subtotal' => $subtotal,
        'tax_amount' => $tax_amount,
        'grand_total' => $grand_total
    ];
}

/**
 * Get all active cities
 */
function getAllCities() {
    $db = getDB();
    try {
        $stmt = $db->query("SELECT * FROM cities WHERE status = 'active' ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('getAllCities() failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get public-facing statistics for the homepage/about counters.
 *
 * @return array{venues:int, events:int, clients:int, service_years:int}
 */
function getPublicStats() {
    $db = getDB();
    $stats = [
        'venues' => 0,
        'events' => 0,
        'clients' => 0,
        'service_years' => 0,
    ];
    $service_years_override = trim((string)getSetting('service_years', ''));

    try {
        $stmt = $db->query("
            SELECT
                (SELECT COUNT(*) FROM venues WHERE status = 'active') AS venues_count,
                (SELECT COUNT(*) FROM bookings WHERE booking_status = 'completed') AS events_count,
                (SELECT COUNT(*) FROM customers) AS clients_count,
                (SELECT MIN(created_at) FROM venues) AS venue_started_at,
                (SELECT MIN(created_at) FROM bookings) AS booking_started_at
        ");
        $row = $stmt->fetch() ?: [];
        $stats['venues'] = (int)($row['venues_count'] ?? 0);
        $stats['events'] = (int)($row['events_count'] ?? 0);
        $stats['clients'] = (int)($row['clients_count'] ?? 0);

        $venue_start = $row['venue_started_at'] ?? null;
        $booking_start = $row['booking_started_at'] ?? null;

        $earliest = null;
        if (!empty($venue_start)) {
            $earliest = $venue_start;
        }
        if (!empty($booking_start) && ($earliest === null || $booking_start < $earliest)) {
            $earliest = $booking_start;
        }

        if (!empty($earliest)) {
            $start = new DateTime($earliest);
            $now = new DateTime();
            $interval = $start->diff($now);
            $stats['service_years'] = $interval->invert ? 0 : max(0, (int)$interval->y);
        }
    } catch (PDOException $e) {
        error_log('getPublicStats() failed: ' . $e->getMessage());
    }

    if ($service_years_override !== '' && is_numeric($service_years_override)) {
        $stats['service_years'] = max(0, (int)$service_years_override);
    }

    if ($stats['service_years'] <= 0) {
        $stats['service_years'] = 5;
    }

    return $stats;
}

/**
 * Get available venues for a date, optionally filtered by city
 */
function getAvailableVenues($date, $shift, $city_id = null) {
    $db = getDB();
    
    // Get all active venues (optionally filtered by city)
    $params = [];
    $sql = "SELECT v.*, c.name AS city_name FROM venues v
            LEFT JOIN cities c ON v.city_id = c.id
            WHERE v.status = 'active'";
    
    if ($city_id) {
        $sql .= " AND v.city_id = ?";
        $params[] = intval($city_id);
    }
    
    $sql .= " ORDER BY v.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $venues = $stmt->fetchAll();
    
    // Check file existence once and cache results
    $file_exists_cache = [];
    $needs_fallback = false;
    
    foreach ($venues as $venue) {
        $safe_filename = !empty($venue['image']) ? basename($venue['image']) : '';
        
        // Validate filename structure using pattern defined at top of file
        // Pattern ensures: name.ext or name-part.ext or name_part.ext
        // Blocks: consecutive dots, leading/trailing separators, special chars
        if (!empty($safe_filename) && !preg_match(SAFE_FILENAME_PATTERN, $safe_filename)) {
            $safe_filename = ''; // Invalid filename structure
        }
        
        $exists = !empty($safe_filename) && file_exists(UPLOAD_PATH . $safe_filename);
        $file_exists_cache[$venue['id']] = ['filename' => $safe_filename, 'exists' => $exists];
        
        if (!$exists) {
            $needs_fallback = true;
        }
    }
    
    // Only fetch gallery images if needed
    $venue_images = [];
    $venue_image_index = 0;
    if ($needs_fallback) {
        $venue_images = getImagesBySection('venue');
    }
    
    // Process each venue to ensure it has an image
    $venue_images_count = count($venue_images);
    
    foreach ($venues as &$venue) {
        $cache = $file_exists_cache[$venue['id']];
        
        // If venue doesn't have a valid image
        if (!$cache['exists']) {
            // Use fallback from site_images
            if ($venue_images_count > 0 && isset($venue_images[$venue_image_index])) {
                $venue['image'] = $venue_images[$venue_image_index]['image_path'];
                $venue_image_index = ($venue_image_index + 1) % $venue_images_count;
            } else {
                // Use empty string to trigger SVG placeholder in frontend
                $venue['image'] = '';
            }
        } else {
            // Ensure we use the sanitized filename
            $venue['image'] = $cache['filename'];
        }

        // Attach gallery images for carousel display: prefer venue-specific, fall back to hall images
        $venue_gallery = getVenueImages($venue['id']);
        $venue['gallery_images'] = !empty($venue_gallery) ? $venue_gallery : getVenueGalleryImages($venue['id']);
    }
    
    return $venues;
}

/**
 * Get all active venues for homepage display
 */
function getAllActiveVenues($city_id = null) {
    $db = getDB();
    
    // Get all active venues, optionally filtered by city
    $params = [];
    $sql = "SELECT v.*, c.name AS city_name FROM venues v
            LEFT JOIN cities c ON v.city_id = c.id
            WHERE v.status = 'active'";
    if ($city_id !== null) {
        $sql .= " AND v.city_id = ?";
        $params[] = $city_id;
    }
    $sql .= " ORDER BY v.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $venues = $stmt->fetchAll();
    
    // Process venue images and get hall images
    foreach ($venues as &$venue) {
        $safe_filename = !empty($venue['image']) ? basename($venue['image']) : '';
        
        // Validate filename structure
        if (!empty($safe_filename) && !preg_match(SAFE_FILENAME_PATTERN, $safe_filename)) {
            $safe_filename = '';
        }
        
        $exists = !empty($safe_filename) && file_exists(UPLOAD_PATH . $safe_filename);
        
        if (!$exists) {
            $venue['image'] = '';
        } else {
            $venue['image'] = $safe_filename;
        }
        
        // Get gallery images: prefer venue-specific images, fall back to hall images
        $venue_imgs = getVenueImages($venue['id']);
        $venue['gallery_images'] = !empty($venue_imgs) ? $venue_imgs : getVenueGalleryImages($venue['id']);
    }
    
    return $venues;
}

/**
 * Get images uploaded directly for a venue (from venue_images table)
 */
function getVenueImages($venue_id) {
    $db = getDB();

    // Check if venue_images table exists to remain backward-compatible
    try {
        $sql = "SELECT image_path, is_primary, display_order
                FROM venue_images
                WHERE venue_id = ?
                ORDER BY is_primary DESC, display_order ASC, id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$venue_id]);
        $images = $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }

    $validated_images = [];
    foreach ($images as $image) {
        $safe_filename = !empty($image['image_path']) ? basename($image['image_path']) : '';
        if (!empty($safe_filename) && preg_match(SAFE_FILENAME_PATTERN, $safe_filename)
            && file_exists(UPLOAD_PATH . $safe_filename)) {
            $validated_images[] = [
                'image_path' => $safe_filename,
                'is_primary' => $image['is_primary'],
                'hall_name'  => null,
            ];
        }
    }
    return $validated_images;
}

/**
 * Get all hall images for a venue (from all halls belonging to the venue)
 */
function getVenueGalleryImages($venue_id) {
    $db = getDB();
    
    // Get all hall images for halls belonging to this venue, ordered by display_order
    $sql = "SELECT hi.image_path, hi.is_primary, hi.display_order, h.name as hall_name
            FROM hall_images hi
            INNER JOIN halls h ON hi.hall_id = h.id
            WHERE h.venue_id = ? AND h.status = 'active'
            ORDER BY hi.is_primary DESC, hi.display_order ASC, hi.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$venue_id]);
    $images = $stmt->fetchAll();
    
    // Process and validate each image
    $validated_images = [];
    foreach ($images as $image) {
        $safe_filename = !empty($image['image_path']) ? basename($image['image_path']) : '';
        
        // Validate filename structure
        if (!empty($safe_filename) && preg_match(SAFE_FILENAME_PATTERN, $safe_filename)) {
            $exists = file_exists(UPLOAD_PATH . $safe_filename);
            
            if ($exists) {
                $validated_images[] = [
                    'image_path' => $safe_filename,
                    'is_primary' => $image['is_primary'],
                    'hall_name' => $image['hall_name']
                ];
            }
        }
    }
    
    return $validated_images;
}

/**
 * Get halls for a venue
 */
function getHallsForVenue($venue_id, $min_capacity = 0) {
    $db = getDB();
    
    $sql = "SELECT h.* FROM halls h 
            WHERE h.venue_id = ? 
            AND h.status = 'active'
            AND h.capacity >= ?
            ORDER BY h.capacity DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$venue_id, $min_capacity]);
    return $stmt->fetchAll();
}

/**
 * Get menus for a hall
 * Falls back to all active menus if no menus are specifically assigned to the hall.
 */
function getMenusForHall($hall_id) {
    $db = getDB();
    
    try {
        $sql = "SELECT m.* FROM menus m
                INNER JOIN hall_menus hm ON m.id = hm.menu_id
                WHERE hm.hall_id = ? 
                AND m.status = 'active'
                AND hm.status = 'active'
                ORDER BY m.price_per_person DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$hall_id]);
        $menus = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getMenusForHall query failed (hall_id=$hall_id): " . $e->getMessage());
        $menus = [];
    }
    
    // If no menus are assigned to this hall, fall back to all active menus
    if (empty($menus)) {
        $fallback_sql = "SELECT m.* FROM menus m WHERE m.status = 'active' ORDER BY m.price_per_person DESC";
        $fallback_stmt = $db->query($fallback_sql);
        $menus = $fallback_stmt->fetchAll();
    }
    
    return $menus;
}

/**
 * Get menu items
 */
function getMenuItems($menu_id) {
    $db = getDB();
    
    $sql = "SELECT * FROM menu_items 
            WHERE menu_id = ? 
            ORDER BY display_order, category";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$menu_id]);
    return $stmt->fetchAll();
}

/**
 * Get all active services, joined with vendor_types for proper categorisation.
 * Returns vendor_type_id and vendor_type_label from the vendor_types table when
 * available, falling back to the legacy free-text category field for older rows
 * that have not been migrated yet.
 */
function getActiveServices() {
    $db = getDB();

    $sql = "SELECT s.*,
                   COALESCE(vt.label, s.category) AS vendor_type_label,
                   vt.slug                         AS vendor_type_slug,
                   COALESCE(vt.display_order, 9999) AS vendor_type_order
            FROM additional_services s
            LEFT JOIN vendor_types vt ON vt.id = s.vendor_type_id
            WHERE s.status = 'active'
            ORDER BY vendor_type_order, vendor_type_label, s.name";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get active sub-services for a given additional service.
 */
function getServiceSubServices($service_id) {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM service_sub_services
         WHERE service_id = ? AND status = 'active'
         ORDER BY display_order, name"
    );
    $stmt->execute([$service_id]);
    return $stmt->fetchAll();
}

/**
 * Get active designs for a given sub-service.
 */
function getSubServiceDesigns($sub_service_id) {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM service_designs
         WHERE sub_service_id = ? AND status = 'active'
         ORDER BY display_order, name"
    );
    $stmt->execute([$sub_service_id]);
    return $stmt->fetchAll();
}

/**
 * Get all sub-services (with their designs) for a given additional service.
 * Returns an array of sub-services, each with a 'designs' key.
 */
function getServiceSubServicesWithDesigns($service_id) {
    $sub_services = getServiceSubServices($service_id);
    foreach ($sub_services as &$ss) {
        $ss['designs'] = getSubServiceDesigns($ss['id']);
    }
    return $sub_services;
}

/**
 * Get active designs directly linked to a service (via service_designs.service_id).
 * Returns an array of designs ordered by display_order, name.
 *
 * Returns an empty array (rather than throwing) if the service_id column does
 * not yet exist on an older installation — the caller treats "no designs" the
 * same as a service without any designs configured, so booking-step4 still
 * loads and users can still submit bookings.  Run the
 * database/migrations/fix_service_designs_columns.sql migration to add the
 * column and enable the direct-design feature.
 */
function getServiceDesigns($service_id) {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT * FROM service_designs
             WHERE service_id = ? AND status = 'active'
             ORDER BY display_order, name"
        );
        $stmt->execute([$service_id]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('getServiceDesigns failed (service_designs.service_id column may be missing — run fix_service_designs_columns.sql migration): ' . $e->getMessage());
        return [];
    }
}

/**
 * Calculate total price for a set of selected designs.
 * $selected_designs is an array of design_id values.
 */
function calculateDesignsTotal($selected_designs) {
    if (empty($selected_designs)) {
        return 0.0;
    }
    $db = getDB();
    $ids = array_map('intval', array_values($selected_designs));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT COALESCE(SUM(price), 0) as total FROM service_designs WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $row = $stmt->fetch();
    return (float)($row['total'] ?? 0);
}

/**
 * Get all active service categories with their active packages and features
 * Returns an array of categories, each with a 'packages' key containing packages,
 * each package having a 'features' key containing its feature list.
 */
function getServicePackagesByCategory() {
    $db = getDB();
    try {
        $cat_stmt = $db->query(
            "SELECT * FROM service_categories WHERE status = 'active' ORDER BY display_order, name"
        );
        $categories = $cat_stmt->fetchAll();

        foreach ($categories as $ci => $category) {
            $pkg_stmt = $db->prepare(
                "SELECT * FROM service_packages
                 WHERE category_id = ? AND status = 'active'
                 ORDER BY display_order, name"
            );
            $pkg_stmt->execute([$category['id']]);
            $packages = $pkg_stmt->fetchAll();

            foreach ($packages as $pi => $package) {
                $feat_stmt = $db->prepare(
                    "SELECT feature_text FROM service_package_features
                     WHERE package_id = ?
                     ORDER BY display_order, id"
                );
                $feat_stmt->execute([$package['id']]);
                $packages[$pi]['features'] = $feat_stmt->fetchAll(PDO::FETCH_COLUMN);

                // Load photos if the table exists
                try {
                    $photo_stmt = $db->prepare(
                        "SELECT image_path FROM service_package_photos
                         WHERE package_id = ?
                         ORDER BY display_order, id"
                    );
                    $photo_stmt->execute([$package['id']]);
                    $packages[$pi]['photos'] = $photo_stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (Exception $e) {
                    $packages[$pi]['photos'] = [];
                }
            }

            $categories[$ci]['packages'] = $packages;
        }

        return $categories;
    } catch (Exception $e) {
        error_log('getServicePackagesByCategory() failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get features for a specific package
 */
function getPackageFeatures($package_id) {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT * FROM service_package_features
             WHERE package_id = ?
             ORDER BY display_order, id"
        );
        $stmt->execute([$package_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('getPackageFeatures() failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get or create customer
 */
function getOrCreateCustomer($full_name, $phone, $email = '', $address = '', $city = '') {
    $db = getDB();
    
    // Check if customer exists
    $stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
    $stmt->execute([$phone]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        // Update customer info
        $stmt = $db->prepare("UPDATE customers SET full_name = ?, email = ?, address = ?, city = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $address, $city ?: null, $customer['id']]);
        return $customer['id'];
    } else {
        // Create new customer
        $stmt = $db->prepare("INSERT INTO customers (full_name, phone, email, address, city) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $phone, $email, $address, $city ?: null]);
        return $db->lastInsertId();
    }
}

/**
 * Create booking
 */
function createBooking($data) {
    $db = getDB();
    $booking_id = null;
    $booking_number = null;

    try {
        // For custom venues (customer's own venue), skip the hall availability check
        $is_custom = !empty($data['is_custom']);
        if (!$is_custom) {
            // Check hall availability inside the try-catch so any DB exception is
            // caught and handled gracefully rather than producing a fatal error.
            // Prefer time-based overlap check when start/end times are available.
            $avail_start = !empty($data['start_time']) ? $data['start_time'] : null;
            $avail_end   = !empty($data['end_time'])   ? $data['end_time']   : null;
            if (!checkHallAvailability($data['hall_id'], $data['event_date'], $data['shift'] ?? '', $avail_start, $avail_end)) {
                return ['success' => false, 'error' => 'Sorry, this hall is no longer available for the selected date and time. Please select a different time slot.'];
            }
        }
    } catch (\Throwable $e) {
        error_log('Hall availability check error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Unable to check hall availability. Please try again or contact support.'];
    }

    // Attempt the booking up to 3 times to handle the rare race condition
    // where two simultaneous requests generate the same booking number and
    // one fails with a UNIQUE constraint violation on booking_number.
    // Only duplicate booking_number errors trigger automatic retry; all other
    // errors fail immediately and are returned to the caller.
    $maxAttempts = 3;
    $lastError = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $booking_id     = null;
        $booking_number = null;

        try {
            $db->beginTransaction();

            // Generate booking number
            $booking_number = generateBookingNumber();

            // Get or create customer
            $customer_id = getOrCreateCustomer(
                $data['full_name'],
                $data['phone'],
                $data['email'] ?? '',
                $data['address'] ?? ''
            );

            // Resolve hall_id — NULL for custom venues
            $hall_id_value = $is_custom ? null : ($data['hall_id'] ?: null);

            // Calculate totals
            $totals = calculateBookingTotal(
                $hall_id_value,
                $data['menus'] ?? [],
                $data['guests'],
                $data['services'] ?? [],
                $data['selected_designs'] ?? [],
                $data['packages'] ?? []
            );

            // Insert booking
            // Derive start/end times from time slot when provided, fall back to shift defaults
            if (!empty($data['start_time']) && !empty($data['end_time'])) {
                $start_time = $data['start_time'];
                $end_time   = $data['end_time'];
                // Auto-derive shift from times when it wasn't explicitly provided
                $booking_shift = !empty($data['shift']) ? $data['shift'] : deriveShiftFromTimes($start_time, $end_time);
            } else {
                $booking_shift = !empty($data['shift']) ? $data['shift'] : 'fullday';
                $shift_times   = getShiftDefaultTimes($booking_shift);
                $start_time    = $shift_times['start'];
                $end_time      = $shift_times['end'];
            }

            $sql = "INSERT INTO bookings (
                        booking_number, customer_id, hall_id, custom_venue_name, custom_hall_name,
                        event_date, start_time, end_time, shift,
                        event_type, number_of_guests, hall_price, menu_total, 
                        services_total, subtotal, tax_amount, grand_total, 
                        special_requests, booking_status, payment_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $booking_number,
                $customer_id,
                $hall_id_value,
                $is_custom ? ($data['custom_venue_name'] ?? '') : null,
                $is_custom ? ($data['custom_hall_name']  ?? '') : null,
                $data['event_date'],
                $start_time ?: null,
                $end_time   ?: null,
                $booking_shift,
                $data['event_type'],
                $data['guests'],
                $totals['hall_price'],
                $totals['menu_total'],
                $totals['services_total'],
                $totals['subtotal'],
                $totals['tax_amount'],
                $totals['grand_total'],
                $data['special_requests'] ?? ''
            ]);

            $booking_id = $db->lastInsertId();

            // Insert individual time slot records into the junction table.
            // selected_slots is an array of ['id', 'slot_name', 'start_time', 'end_time'];
            // slot_id (legacy single-slot key) is also supported for backward compat.
            $slot_ids_to_insert = [];
            if (!empty($data['selected_slots']) && is_array($data['selected_slots'])) {
                foreach ($data['selected_slots'] as $sl) {
                    $sid = isset($sl['id']) ? intval($sl['id']) : 0;
                    if ($sid > 0) $slot_ids_to_insert[] = $sid;
                }
            } elseif (!empty($data['slot_id'])) {
                $slot_ids_to_insert[] = intval($data['slot_id']);
            }
            // Deduplicate to ensure no duplicate rows in the junction table
            $slot_ids_to_insert = array_values(array_unique($slot_ids_to_insert));
            if (!empty($slot_ids_to_insert) && !$is_custom) {
                $bts_stmt = $db->prepare(
                    "INSERT INTO booking_time_slots (booking_id, hall_time_slot_id) VALUES (?, ?)"
                );
                foreach ($slot_ids_to_insert as $sid) {
                    try {
                        $bts_stmt->execute([$booking_id, $sid]);
                    } catch (\Throwable $btsErr) {
                        error_log("booking_time_slots insert error: " . $btsErr->getMessage());
                        // Non-fatal: continue with the rest of the booking
                    }
                }
            }

            // Insert booking menus
            if (!empty($data['menus'])) {
                foreach ($data['menus'] as $menu_id) {
                    $stmt = $db->prepare("SELECT price_per_person FROM menus WHERE id = ?");
                    $stmt->execute([$menu_id]);
                    $menu = $stmt->fetch();

                    if ($menu) {
                        $menu_price = $menu['price_per_person'];
                        $menu_total = $menu_price * $data['guests'];

                        $stmt = $db->prepare("INSERT INTO booking_menus (booking_id, menu_id, price_per_person, number_of_guests, total_price) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$booking_id, $menu_id, $menu_price, $data['guests'], $menu_total]);
                    }
                }
            }

            // Insert booking services
            if (!empty($data['services'])) {
                foreach ($data['services'] as $service_id) {
                    $stmt = $db->prepare("SELECT name, price, description, category FROM additional_services WHERE id = ?");
                    $stmt->execute([$service_id]);
                    $service = $stmt->fetch();

                    if ($service) {
                        $stmt = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$booking_id, $service_id, $service['name'], $service['price'], $service['description'], $service['category'], USER_SERVICE_TYPE, DEFAULT_SERVICE_QUANTITY]);
                    }
                }
            }

            // Insert user-selected service packages into booking_services
            if (!empty($data['packages'])) {
                foreach ($data['packages'] as $package_id) {
                    $package_id = intval($package_id);
                    if ($package_id <= 0) continue;
                    try {
                        $stmt = $db->prepare("SELECT name, price, description FROM service_packages WHERE id = ? AND status = 'active'");
                        $stmt->execute([$package_id]);
                        $package = $stmt->fetch();
                        if ($package) {
                            $insert = $db->prepare("INSERT INTO booking_services (booking_id, service_id, service_name, price, description, category, added_by, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $insert->execute([
                                $booking_id,
                                $package_id,
                                $package['name'],
                                $package['price'],
                                $package['description'] ?? '',
                                PACKAGE_SERVICE_CATEGORY,
                                USER_SERVICE_TYPE,
                                DEFAULT_SERVICE_QUANTITY,
                            ]);
                        }
                    } catch (\Throwable $pkgErr) {
                        error_log("Package insertion skipped for booking {$booking_id}, package_id={$package_id}: " . $pkgErr->getMessage());
                    }
                }
            }

            // Insert selected designs into booking_services.
            // selected_designs format: { service_id => design_id } (direct design flow)
            // Also supports legacy { sub_service_id => design_id } for backward compatibility.
            // Each design insertion is wrapped in its own try-catch so that a schema
            // mismatch (e.g. missing sub_service_id / design_id columns on an older
            // database that hasn't been migrated) does NOT roll back the whole booking.
            // The basic booking record is always preserved; missing designs are logged.
            if (!empty($data['selected_designs'])) {
                foreach ($data['selected_designs'] as $key_id => $design_id) {
                    $key_id    = intval($key_id);
                    $design_id = intval($design_id);

                    try {
                        // Try new direct-service design first (service_id on service_designs)
                        $stmt = $db->prepare(
                            "SELECT d.name, d.price, d.description, d.service_id, s.category
                             FROM service_designs d
                             JOIN additional_services s ON s.id = d.service_id
                             WHERE d.id = ? AND d.service_id = ?"
                        );
                        $stmt->execute([$design_id, $key_id]);
                        $design = $stmt->fetch();
                        $sub_service_id_val = null;

                        if (!$design) {
                            // Fall back to legacy sub-service flow
                            $stmt = $db->prepare(
                                "SELECT d.name, d.price, d.description, ss.service_id, s.category
                                 FROM service_designs d
                                 JOIN service_sub_services ss ON ss.id = d.sub_service_id
                                 JOIN additional_services s ON s.id = ss.service_id
                                 WHERE d.id = ? AND d.sub_service_id = ?"
                            );
                            $stmt->execute([$design_id, $key_id]);
                            $design = $stmt->fetch();
                            if ($design) {
                                $sub_service_id_val = $key_id;
                            }
                        }

                        if ($design) {
                            $insert = $db->prepare(
                                "INSERT INTO booking_services
                                     (booking_id, service_id, service_name, price, description, category,
                                      added_by, quantity, sub_service_id, design_id)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            $insert->execute([
                                $booking_id,
                                $design['service_id'],
                                $design['name'],
                                $design['price'],
                                $design['description'],
                                $design['category'],
                                USER_SERVICE_TYPE,
                                DEFAULT_SERVICE_QUANTITY,
                                $sub_service_id_val,
                                $design_id
                            ]);
                        }
                    } catch (\Throwable $designErr) {
                        // Log the error but do not abort the booking — the core booking
                        // record was already inserted.  Run the fix_booking_step5_submission
                        // migration (database/migrations/fix_booking_step5_submission.sql)
                        // to add the missing columns and prevent this fallback.
                        error_log("Design insertion skipped for booking {$booking_id}, design_id={$design_id}: " . $designErr->getMessage());
                    }
                }
            }

            $db->commit();
            $lastError = null;
            break; // Booking committed successfully — exit retry loop.

        } catch (\Throwable $e) {
            // Roll back this attempt
            try {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            } catch (\Throwable $rollbackError) {
                error_log('Booking rollback failed: ' . $rollbackError->getMessage());
            }

            $errMsg = $e->getMessage();
            error_log("Booking creation attempt {$attempt} error: " . $errMsg);

            // If the booking_number uniqueness constraint was violated (race
            // condition), generate a fresh number and retry automatically.
            if (stripos($errMsg, 'Duplicate entry') !== false
                && stripos($errMsg, 'booking_number') !== false
                && $attempt < $maxAttempts) {
                continue; // next attempt
            }

            $lastError = $e;
            break; // non-retryable error — stop retrying
        }
    } // end for ($attempt ...)

    if ($lastError !== null) {
        $errMsg = $lastError->getMessage();

        // Provide a specific user-facing message for known error types
        $userMessage = 'Unable to complete your booking. Please try again or contact support.';
        if (stripos($errMsg, 'Duplicate entry') !== false) {
            $userMessage = 'A booking with the same details already exists. Please check your previous bookings or contact support.';
        } elseif (stripos($errMsg, 'no longer available') !== false) {
            $userMessage = $errMsg; // Hall availability message is already user-friendly
        } elseif (stripos($errMsg, "doesn't exist") !== false || stripos($errMsg, 'Unknown column') !== false || stripos($errMsg, "Table '") !== false || stripos($errMsg, "table doesn't") !== false) {
            $userMessage = 'A system configuration error occurred. Please contact support and mention: DB schema issue.';
        } elseif (stripos($errMsg, 'Connection') !== false || stripos($errMsg, 'connect') !== false) {
            $userMessage = 'Unable to connect to the database. Please try again in a few minutes.';
        }
        return ['success' => false, 'error' => $userMessage];
    }

    // Send email notifications after successful commit (outside try-catch so email
    // failures do not roll back or mask the successfully stored booking)
    try {
        sendBookingNotification($booking_id, 'new');
    } catch (\Throwable $e) {
        error_log("Booking notification email failed for booking ID {$booking_id}: " . $e->getMessage());
    }

    return ['success' => true, 'booking_id' => $booking_id, 'booking_number' => $booking_number];
}


/**
 * Get booking details
 */
function getBookingDetails($booking_id) {
    try {
        $db = getDB();
        
        $sql = "SELECT b.*, c.full_name, c.phone, c.email, c.address,
                       COALESCE(h.name, b.custom_hall_name) as hall_name,
                       h.capacity,
                       COALESCE(v.name, b.custom_venue_name) as venue_name,
                       COALESCE(v.location, '') as location,
                       COALESCE(v.address, '') as venue_address,
                       v.map_link,
                       v.contact_phone as venue_contact_phone,
                       ci.name as city_name
                FROM bookings b
                INNER JOIN customers c ON b.customer_id = c.id
                LEFT JOIN halls h ON b.hall_id = h.id
                LEFT JOIN venues v ON h.venue_id = v.id
                LEFT JOIN cities ci ON v.city_id = ci.id
                WHERE b.id = ?";
        
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare booking query");
        }
        
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            // Cast numeric fields to proper types to ensure strict comparisons work correctly
            // advance_payment_received is TINYINT(1) DEFAULT 0, so it will never be NULL
            $booking['advance_payment_received'] = (int)$booking['advance_payment_received'];
            $booking['advance_amount_received'] = floatval($booking['advance_amount_received'] ?? 0);

            // Use city name as location when the legacy location field is empty
            if (empty($booking['location']) && !empty($booking['city_name'])) {
                $booking['location'] = $booking['city_name'];
            }
            
            // Get menus
            $stmt = $db->prepare("SELECT bm.*, m.name as menu_name FROM booking_menus bm INNER JOIN menus m ON bm.menu_id = m.id WHERE bm.booking_id = ?");
            if ($stmt) {
                $stmt->execute([$booking_id]);
                $booking['menus'] = $stmt->fetchAll();
            } else {
                $booking['menus'] = [];
            }
            
            // Get menu items for each menu (prepare statement once for efficiency)
            if (!empty($booking['menus'])) {
                $itemsStmt = $db->prepare("SELECT item_name, category, display_order FROM menu_items WHERE menu_id = ? ORDER BY display_order, category");
                if ($itemsStmt) {
                    foreach ($booking['menus'] as &$menu) {
                        $itemsStmt->execute([$menu['menu_id']]);
                        $menu['items'] = $itemsStmt->fetchAll();
                    }
                }
            }
            
            // Get services - using denormalized data from booking_services table
            // This ensures historical data is displayed even if services are deleted from master table
            // Description and category are now stored in booking_services for full historical preservation
            // Now includes quantity and added_by for admin-added services support
            $stmt = $db->prepare("
                SELECT bs.id, bs.booking_id, bs.service_id, bs.service_name, bs.price, 
                       bs.description, bs.category, bs.quantity, bs.added_by,
                       bs.design_id,
                       (bs.price * bs.quantity) as total_price
                FROM booking_services bs 
                WHERE bs.booking_id = ?
                ORDER BY bs.added_by, bs.service_name
            ");
            if ($stmt) {
                $stmt->execute([$booking_id]);
                $booking['services'] = $stmt->fetchAll();
            } else {
                $booking['services'] = [];
            }
        }
        
        return $booking;
    } catch (PDOException $e) {
        error_log("Database error in getBookingDetails: " . $e->getMessage());
        throw new Exception("Unable to retrieve booking information");
    } catch (Exception $e) {
        error_log("Error in getBookingDetails: " . $e->getMessage());
        throw new Exception("Unable to retrieve booking information");
    }
}

/**
 * Calculate booking status display variables from booking data
 * Returns an array with display values for status badges
 * 
 * @param array $booking Booking data array
 * @return array Array containing display variables
 */
function calculateBookingStatusVariables($booking) {
    // Validate required keys
    if (!isset($booking['booking_status']) || !isset($booking['payment_status'])) {
        throw new InvalidArgumentException('Booking array must contain booking_status and payment_status keys');
    }
    
    // Map booking statuses to Bootstrap color classes
    $booking_status_colors = [
        'confirmed' => 'success',
        'pending' => 'warning',
        'cancelled' => 'danger',
        'completed' => 'primary',
        'payment_submitted' => 'info'
    ];
    
    // Map payment statuses to Bootstrap color classes
    $payment_status_colors = [
        'paid' => 'success',
        'partial' => 'warning',
        'pending' => 'danger'
    ];
    
    // Map payment statuses to Font Awesome icons
    $payment_status_icons = [
        'paid' => 'fa-check-circle',
        'partial' => 'fa-clock',
        'pending' => 'fa-exclamation-circle'
    ];
    
    $booking_status = $booking['booking_status'];
    $payment_status = $booking['payment_status'];
    
    return [
        'booking_status_display' => getBookingStatusLabel($booking_status),
        'booking_status_color' => $booking_status_colors[$booking_status] ?? 'info',
        'payment_status_display' => ucfirst($payment_status),
        'payment_status_color' => $payment_status_colors[$payment_status] ?? 'danger',
        'payment_status_icon' => $payment_status_icons[$payment_status] ?? 'fa-exclamation-circle'
    ];
}

/**
 * Get the display label for a booking status
 * 
 * @param string $status The booking status value (e.g., 'completed', 'confirmed')
 * @return string The display label (e.g., 'Order Complete', 'Confirmed')
 */
function getBookingStatusLabel($status) {
    $labels = [
        'confirmed' => 'Confirmed',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
        'completed' => 'Order Complete',
        'payment_submitted' => 'Payment Submitted'
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

/**
 * Return the auto-derived booking_status and advance_payment_received
 * values that correspond to a given payment_status.
 *
 * Rules:
 *   pending  → booking_status = pending,   advance_payment_received = 0
 *   partial  → booking_status = confirmed, advance_payment_received = 1
 *   paid     → booking_status = completed, advance_payment_received = 1
 *   (other)  → no change (returns null values so caller keeps current values)
 *
 * @param  string $payment_status  One of 'pending','partial','paid','cancelled'
 * @return array{booking_status: string|null, advance_payment_received: int|null}
 */
function getAutoStatusByPaymentStatus(string $payment_status): array {
    switch ($payment_status) {
        case 'pending':
            return ['booking_status' => 'pending', 'advance_payment_received' => 0];
        case 'partial':
            return ['booking_status' => 'confirmed', 'advance_payment_received' => 1];
        case 'paid':
            return ['booking_status' => 'completed', 'advance_payment_received' => 1];
        default:
            return ['booking_status' => null, 'advance_payment_received' => null];
    }
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    $currency = getSetting('currency', 'NPR');
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Convert number to words (for invoices)
 * Supports numbers up to 99,999,999.99
 */
function numberToWords($number) {
    $number = number_format($number, 2, '.', '');
    list($integer, $fraction) = explode('.', $number);
    
    $output = '';
    
    if ($integer == 0) {
        $output = 'Zero';
    } else {
        $ones = array(
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen'
        );
        
        $tens = array(
            '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'
        );
        
        $integer = str_pad($integer, 9, '0', STR_PAD_LEFT);
        $crore = intval(substr($integer, 0, 2));
        $lakh = intval(substr($integer, 2, 2));
        $thousand = intval(substr($integer, 4, 2));
        $hundred = intval(substr($integer, 6, 1));
        $ten = intval(substr($integer, 7, 2));
        
        $result = array();
        
        // Crores
        if ($crore > 0) {
            if ($crore < 20) {
                $result[] = $ones[$crore] . ' Crore';
            } else {
                $remainder = $ones[$crore % 10];
                $result[] = trim($tens[intval($crore / 10)] . ' ' . $remainder) . ' Crore';
            }
        }
        
        // Lakhs
        if ($lakh > 0) {
            if ($lakh < 20) {
                $result[] = $ones[$lakh] . ' Lakh';
            } else {
                $remainder = $ones[$lakh % 10];
                $result[] = trim($tens[intval($lakh / 10)] . ' ' . $remainder) . ' Lakh';
            }
        }
        
        // Thousands
        if ($thousand > 0) {
            if ($thousand < 20) {
                $result[] = $ones[$thousand] . ' Thousand';
            } else {
                $remainder = $ones[$thousand % 10];
                $result[] = trim($tens[intval($thousand / 10)] . ' ' . $remainder) . ' Thousand';
            }
        }
        
        // Hundreds
        if ($hundred > 0) {
            $result[] = $ones[$hundred] . ' Hundred';
        }
        
        // Tens and ones
        if ($ten > 0) {
            if ($ten < 20) {
                $result[] = $ones[$ten];
            } else {
                $result[] = trim($tens[intval($ten / 10)] . ' ' . $ones[$ten % 10]);
            }
        }
        
        $output = trim(implode(' ', $result));
    }
    
    // Add paisa if fraction exists
    if (intval($fraction) > 0) {
        $output .= ' and ' . intval($fraction) . '/100';
    }
    
    return $output;
}

/**
 * Calculate advance payment amount
 * 
 * @param float $total_amount The total booking amount
 * @return array Array with 'percentage' and 'amount' keys
 */
function calculateAdvancePayment($total_amount) {
    // Validate input
    if (!is_numeric($total_amount) || $total_amount < 0) {
        return [
            'percentage' => 0,
            'amount' => 0
        ];
    }
    
    $advance_percentage = floatval(getSetting('advance_payment_percentage', '25'));
    $advance_amount = $total_amount * ($advance_percentage / 100);
    
    return [
        'percentage' => $advance_percentage,
        'amount' => $advance_amount
    ];
}

/**
 * Determine the advance payment amount and label to display.
 *
 * Returns the actual advance_amount_received if it has been set (> 0),
 * otherwise falls back to the percentage-calculated advance amount.
 *
 * @param float $grand_total          Grand total of the booking
 * @param float $advance_amount_received Actual advance already received (0 = not yet set)
 * @return array ['amount' => float, 'label' => string]  label is empty string when using actual amount
 */
function getAdvanceDisplayInfo($grand_total, $advance_amount_received) {
    $advance_amount_received = floatval($advance_amount_received);
    if ($advance_amount_received > 0) {
        return [
            'amount' => $advance_amount_received,
            'label'  => '',
        ];
    }
    $calc = calculateAdvancePayment($grand_total);
    return [
        'amount' => $calc['amount'],
        'label'  => '(' . htmlspecialchars($calc['percentage'], ENT_QUOTES, 'UTF-8') . '%)',
    ];
}

/**
 * Get setting value with caching
 */
function getSetting($key, $default = '') {
    static $cache = [];
    
    // Return from cache if available
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    
    // Mapping of setting keys to .env variable names (fallback when DB value is empty)
    $env_map = [
        'email_enabled'     => 'MAIL_NOTIFICATIONS_ENABLED',
        'email_from_name'   => 'MAIL_FROM_NAME',
        'email_from_address'=> 'MAIL_FROM_ADDRESS',
        'admin_email'       => 'MAIL_ADMIN_EMAIL',
        'smtp_enabled'      => 'MAIL_SMTP_ENABLED',
        'smtp_host'         => 'MAIL_HOST',
        'smtp_port'         => 'MAIL_PORT',
        'smtp_username'     => 'MAIL_USERNAME',
        'smtp_password'     => 'MAIL_PASSWORD',
        'smtp_encryption'   => 'MAIL_ENCRYPTION',
    ];
    
    try {
        // Query database
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare settings query");
        }
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        $value = $result ? $result['setting_value'] : null;
        
        // Fall back to .env variable if DB value is empty/null and a mapping exists
        if (($value === null || $value === '') && isset($env_map[$key])) {
            $env_value = $_ENV[$env_map[$key]] ?? getenv($env_map[$key]);
            if ($env_value !== false && $env_value !== null) {
                $value = $env_value;
            }
        }
        
        // Store in cache and return (use $default if still empty)
        $cache[$key] = ($value !== null) ? $value : $default;
        return $cache[$key];
    } catch (Exception $e) {
        error_log("Error in getSetting for key '$key': " . $e->getMessage());
        // Fall back to .env variable on DB error
        if (isset($env_map[$key])) {
            $env_value = $_ENV[$env_map[$key]] ?? getenv($env_map[$key]);
            if ($env_value !== false && $env_value !== null) {
                $cache[$key] = $env_value;
                return $cache[$key];
            }
        }
        // Return default value on error
        $cache[$key] = $default;
        return $default;
    }
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $table_name = '', $record_id = null, $details = '') {
    $db = getDB();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $table_name, $record_id, $details, $ip_address]);
}

/**
 * Get images by section
 * Returns active images for a specific section, ordered by display_order
 */
function getImagesBySection($section, $limit = null) {
    $db = getDB();
    
    $sql = "SELECT id, title, description, image_path, section, card_id, display_order 
            FROM site_images 
            WHERE section = ? AND status = 'active' 
            ORDER BY display_order, created_at DESC";
    
    if ($limit !== null) {
        $sql .= " LIMIT " . intval($limit);
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$section]);
    $images = $stmt->fetchAll();
    
    // Add full URL to each image
    foreach ($images as &$image) {
        $image['image_url'] = UPLOAD_URL . $image['image_path'];
    }
    
    return $images;
}

/**
 * Get images for a section grouped into photo cards (max 10 photos per card).
 * Returns an array of cards; each card is an array of up to 10 image records.
 * The first image in each card serves as the preview.
 *
 * @param  string $section  The section slug (e.g. 'gallery')
 * @return array[]          Indexed array of card arrays
 */
function getImagesByCards($section) {
    $db = getDB();

    $sql = "SELECT id, title, description, image_path, section, card_id, display_order
            FROM site_images
            WHERE section = ? AND status = 'active'
            ORDER BY card_id, display_order, created_at";

    $stmt = $db->prepare($sql);
    $stmt->execute([$section]);
    $images = $stmt->fetchAll();

    $cards = [];
    foreach ($images as &$image) {
        $image['image_url'] = UPLOAD_URL . $image['image_path'];
        $cid = (int)$image['card_id'];
        if (!isset($cards[$cid])) {
            $cards[$cid] = [];
        }
        $cards[$cid][] = $image;
    }

    // Re-index to a plain 0-based array
    return array_values($cards);
}

/**
 * Get first image from a section
 * Convenience function to get just the first image
 */
function getFirstImage($section) {
    $images = getImagesBySection($section, 1);
    return !empty($images) ? $images[0] : null;
}

/**
 * Get work_photos images grouped by event_category (folder-style gallery).
 *
 * Returns an associative array keyed by category name. Each value is an array
 * of image records (with image_url added). Images without an event_category are
 * grouped under an 'Uncategorized' bucket so they are never lost.
 *
 * @return array  [ 'Category Name' => [ imageRow, … ], … ]
 */
function getWorkPhotosByCategory() {
    $db = getDB();

    $sql = "SELECT id, title, description, image_path, section, event_category, display_order
            FROM site_images
            WHERE section = 'work_photos' AND status = 'active'
            ORDER BY
                COALESCE(event_category, '') ASC,
                display_order ASC,
                created_at ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $images = $stmt->fetchAll();

    $categories = [];
    foreach ($images as &$image) {
        $image['image_url'] = UPLOAD_URL . $image['image_path'];
        $cat = !empty($image['event_category']) ? $image['event_category'] : 'Uncategorized';
        $categories[$cat][] = $image;
    }
    unset($image);

    return $categories;
}

/**
 * Handle file upload for images
 * 
 * @param array $file The $_FILES array element
 * @param string $prefix Prefix for the filename (e.g., 'hall', 'venue', 'menu')
 * @return array Array with 'success' boolean and 'message' or 'filename'
 */
function handleImageUpload($file, $prefix = 'image') {
    $result = ['success' => false, 'message' => ''];
    
    // Check if file was uploaded
    if ($file['error'] == UPLOAD_ERR_NO_FILE) {
        $result['message'] = 'No file uploaded.';
        return $result;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'Error uploading file. Please try again.';
        return $result;
    }
    
    // Validate file type using MIME type (basic check)
    // Accept both 'image/jpg' (non-standard but common) and 'image/jpeg'
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array(strtolower($file['type']), $allowed_types)) {
        $result['message'] = 'Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.';
        return $result;
    }
    
    // Validate actual image content using getimagesize (security check)
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $result['message'] = 'Invalid image file. The file does not appear to be a valid image.';
        return $result;
    }
    
    // Double-check MIME type from getimagesize and map to extension
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    if (!isset($mime_to_ext[$image_info['mime']])) {
        $result['message'] = 'Invalid image type detected. Only JPG, PNG, GIF, and WebP images are allowed.';
        return $result;
    }
    
    // Use extension based on actual MIME type, not client-provided filename
    $extension = $mime_to_ext[$image_info['mime']];
    
    // Validate file size (5MB max)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        $result['message'] = 'File is too large. Maximum size is 5MB.';
        return $result;
    }
    
    // Generate unique filename with validation
    $filename = basename($prefix . '_' . time() . '_' . uniqid() . '.' . $extension);
    
    // Additional safety check: ensure filename contains no directory separators
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
        $result['message'] = 'Invalid filename generated.';
        return $result;
    }
    
    $upload_path = UPLOAD_PATH . $filename;
    
    // Create uploads directory if it doesn't exist with error handling
    if (!is_dir(UPLOAD_PATH)) {
        if (!mkdir(UPLOAD_PATH, 0755, true)) {
            $result['message'] = 'Failed to create upload directory. Please check server permissions.';
            return $result;
        }
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $result['success'] = true;
        $result['filename'] = $filename;
    } else {
        $result['message'] = 'Failed to upload file. Please check directory permissions.';
    }
    
    return $result;
}

/**
 * Delete an uploaded file
 * 
 * @param string $filename The filename to delete
 * @return boolean True if file was deleted or doesn't exist, false on error
 */
function deleteUploadedFile($filename) {
    if (empty($filename)) {
        return true;
    }
    
    // Validate filename to prevent directory traversal
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
        return false; // Invalid filename
    }
    
    // Use basename as additional safety measure
    $filename = basename($filename);
    
    $filepath = UPLOAD_PATH . $filename;
    
    // Ensure the file path is within the upload directory before attempting deletion
    // Use realpath on the directory and manually construct the expected path
    $real_upload_path = realpath(UPLOAD_PATH);
    if ($real_upload_path === false) {
        return false; // Upload directory doesn't exist or is inaccessible
    }
    
    // Construct expected path
    $expected_path = $real_upload_path . DIRECTORY_SEPARATOR . $filename;
    
    // If file exists, verify its real path matches expected path
    if (file_exists($filepath)) {
        $real_file_path = realpath($filepath);
        if ($real_file_path === false || $real_file_path !== $expected_path) {
            return false; // File path doesn't match expected location
        }
        return unlink($filepath);
    }
    
    return true; // File doesn't exist, consider it deleted
}

/**
 * Validate uploaded file path for display
 * Ensures the file exists, is within upload directory, and has no path traversal
 * 
 * @param string $filename The filename to validate
 * @return bool True if valid and safe to display
 */
function validateUploadedFilePath($filename) {
    if (empty($filename)) {
        return false;
    }
    
    // Check for null bytes which can be used to bypass security
    if (strpos($filename, "\0") !== false) {
        return false;
    }
    
    // Check for directory traversal characters
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
        return false;
    }
    
    // Use basename as additional safety - ensures only filename, no path
    $safe_filename = basename($filename);
    
    // Verify filename hasn't changed after basename (would indicate path manipulation)
    if ($safe_filename !== $filename) {
        return false;
    }
    
    // Check if file exists
    $filepath = UPLOAD_PATH . $safe_filename;
    if (!file_exists($filepath)) {
        return false;
    }
    
    // Verify the real path is within upload directory
    $real_upload_path = realpath(UPLOAD_PATH);
    $real_file_path = realpath($filepath);
    
    if ($real_upload_path === false || $real_file_path === false) {
        return false;
    }
    
    // Ensure both paths end with directory separator for accurate comparison
    $real_upload_path = rtrim($real_upload_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    
    // Check if file is within upload directory (with proper path comparison)
    if (strpos($real_file_path, $real_upload_path) !== 0) {
        return false;
    }
    
    return true;
}

/**
 * Display current image preview HTML
 * 
 * @param string $image_filename The image filename
 * @param string $alt_text Alternative text for the image
 * @return string HTML for image preview or empty string if no image
 */
function displayImagePreview($image_filename, $alt_text = 'Current image') {
    if (empty($image_filename)) {
        return '';
    }
    
    $image_path = UPLOAD_PATH . $image_filename;
    if (!file_exists($image_path)) {
        return '';
    }
    
    // URL encode the filename and escape for HTML
    $image_url = UPLOAD_URL . rawurlencode($image_filename);
    $escaped_url = htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8');
    $escaped_alt = htmlspecialchars($alt_text, ENT_QUOTES, 'UTF-8');
    
    return '<div class="mb-2">
        <img src="' . $escaped_url . '" alt="' . $escaped_alt . '" class="img-thumbnail" style="max-width: 200px;">
        <p class="text-muted small mt-1">Current image</p>
    </div>';
}

/**
 * Get placeholder image data URL for missing images
 * Returns an inline SVG as a data URL
 * 
 * @return string Data URL for placeholder SVG
 */
function getPlaceholderImageUrl() {
    // Build SVG placeholder for better readability
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">' .
           '<rect fill="#e9ecef" width="400" height="300"/>' .
           '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" ' .
           'fill="#6c757d" font-size="24" font-family="Arial">No Image</text>' .
           '</svg>';
    
    // URL encode for use in data URL
    return 'data:image/svg+xml,' . rawurlencode($svg);
}

/**
 * Send email notification
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @param string $recipient_name Optional recipient name
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $message, $recipient_name = '') {
    // Check if email is enabled
    if (getSetting('email_enabled', '1') != '1') {
        error_log("Email notification skipped - email notifications are disabled in settings");
        return false;
    }
    
    // Validate email address
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Email notification failed - invalid email address: " . ($to ?: '(empty)'));
        return false;
    }
    
    $from_name = getSetting('email_from_name', 'Venue Booking System');
    $from_email = getSetting('email_from_address', 'noreply@venubooking.com');
    
    // Use SMTP if enabled
    if (getSetting('smtp_enabled', '0') == '1') {
        return sendEmailSMTP($to, $subject, $message, $recipient_name);
    }
    
    // Use PHP mail() function
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $full_to = !empty($recipient_name) ? $recipient_name . ' <' . $to . '>' : $to;
    
    $result = @mail($full_to, $subject, $message, implode("\r\n", $headers));
    
    if (!$result) {
        error_log("Failed to send email to: $to, subject: $subject");
    }
    
    return $result;
}

/**
 * Read a complete (possibly multi-line) SMTP response from a socket.
 * SMTP multi-line responses use "NNN-text" for continuation and "NNN text" for the final line.
 *
 * @param resource $socket
 * @return string The last line of the response (contains the status code)
 */
function smtpReadResponse($socket) {
    $response = '';
    while ($line = fgets($socket)) {
        $response = $line;
        // When the 4th character is a space (or line is shorter than 4 chars), it's the last line
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    return $response;
}

/**
 * Send email using SMTP
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @param string $recipient_name Optional recipient name
 * @return bool True on success, false on failure
 */
function sendEmailSMTP($to, $subject, $message, $recipient_name = '') {
    $smtp_host = getSetting('smtp_host', '');
    $smtp_port = intval(getSetting('smtp_port', '587'));
    $smtp_username = getSetting('smtp_username', '');
    $smtp_password = getSetting('smtp_password', '');
    $smtp_encryption = getSetting('smtp_encryption', 'tls');
    $from_name = getSetting('email_from_name', 'Venue Booking System');
    $from_email = getSetting('email_from_address', 'noreply@venubooking.com');
    
    if (empty($smtp_host) || empty($smtp_username)) {
        error_log("SMTP email failed - SMTP settings incomplete (host: " . ($smtp_host ?: '(empty)') . ", username: " . ($smtp_username ?: '(empty)') . ")");
        return false;
    }

    // Use the authenticated SMTP username as the envelope sender so that SPF
    // and DKIM alignment checks pass (required by Gmail and other providers).
    $envelope_from = $smtp_username;
    
    try {
        // Set timeout for socket operations
        ini_set('default_socket_timeout', 30);
        
        // Create socket connection
        $context = stream_context_create();
        
        if ($smtp_encryption === 'ssl') {
            $socket = @stream_socket_client(
                "ssl://$smtp_host:$smtp_port",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            $socket = @stream_socket_client(
                "tcp://$smtp_host:$smtp_port",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }
        
        if (!$socket) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }
        
        // Set socket timeout
        stream_set_timeout($socket, 30);
        
        // Read server greeting (may be multi-line)
        $response = smtpReadResponse($socket);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            error_log("SMTP server not ready: $response");
            return false;
        }
        
        // Get server name for EHLO, use localhost as fallback
        $ehlo_domain = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        // Sanitize domain name to prevent SMTP injection
        $ehlo_domain = preg_replace('/[^a-zA-Z0-9.-]/', '', $ehlo_domain);
        if (empty($ehlo_domain)) {
            $ehlo_domain = 'localhost';
        }
        
        // Send EHLO and consume the full multi-line response
        fwrite($socket, "EHLO $ehlo_domain\r\n");
        $response = smtpReadResponse($socket);
        
        // Start TLS if needed
        if ($smtp_encryption === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $response = smtpReadResponse($socket);
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                error_log("STARTTLS failed: $response");
                return false;
            }
            
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after TLS and consume full response
            fwrite($socket, "EHLO $ehlo_domain\r\n");
            $response = smtpReadResponse($socket);
        }
        
        // Authenticate
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = smtpReadResponse($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            error_log("SMTP AUTH LOGIN failed: $response");
            return false;
        }
        
        fwrite($socket, base64_encode($smtp_username) . "\r\n");
        $response = smtpReadResponse($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            error_log("SMTP username failed: $response");
            return false;
        }
        
        fwrite($socket, base64_encode($smtp_password) . "\r\n");
        $response = smtpReadResponse($socket);
        
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            error_log("SMTP authentication failed: $response");
            return false;
        }
        
        // Use the authenticated account as envelope sender for SPF/DKIM alignment
        fwrite($socket, "MAIL FROM: <$envelope_from>\r\n");
        $response = smtpReadResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            error_log("SMTP MAIL FROM failed: $response");
            return false;
        }
        
        fwrite($socket, "RCPT TO: <$to>\r\n");
        $response = smtpReadResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            error_log("SMTP RCPT TO failed: $response");
            return false;
        }
        
        fwrite($socket, "DATA\r\n");
        $response = smtpReadResponse($socket);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            error_log("SMTP DATA failed: $response");
            return false;
        }
        
        // Build email content
        $full_to = !empty($recipient_name) ? $recipient_name : $to;
        // Use envelope_from (smtp_username) as the From address so that the
        // From header domain matches the authenticated sender, satisfying DKIM
        // and SPF alignment required by Gmail and other providers.
        $email_content = "From: $from_name <$envelope_from>\r\n";
        $email_content .= "To: $full_to <$to>\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "Date: " . date('r') . "\r\n";
        $email_content .= "Message-ID: <" . uniqid(getmypid() . '.', true) . "@" . (substr(strrchr($envelope_from, '@'), 1) ?: $ehlo_domain) . ">\r\n";
        $email_content .= "MIME-Version: 1.0\r\n";
        $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email_content .= "\r\n";
        $email_content .= $message;
        $email_content .= "\r\n.\r\n";
        
        fwrite($socket, $email_content);
        $response = smtpReadResponse($socket);
        
        // Quit
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        if (substr($response, 0, 3) != '250') {
            error_log("SMTP send failed: $response");
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send booking notification emails
 * 
 * @param int $booking_id Booking ID
 * @param string $type Type of notification (new, update, payment_request)
 * @param string $old_status Old status (for updates)
 * @return array Array with admin and user email results
 */
function sendBookingNotification($booking_id, $type = 'new', $old_status = '') {
    $booking = getBookingDetails($booking_id);
    
    if (!$booking) {
        error_log("Email notification failed - could not retrieve booking details for booking ID: $booking_id");
        return ['admin' => false, 'user' => false];
    }
    
    $admin_email = getSetting('admin_email', getSetting('contact_email', ''));
    $results = ['admin' => false, 'user' => false];
    
    // Determine subject and message based on type
    if ($type === 'new') {
        $admin_subject = 'New Booking Received - ' . $booking['booking_number'];
        $user_subject = 'Booking Confirmation - ' . $booking['booking_number'];
    } elseif ($type === 'payment_request') {
        $admin_subject = 'Payment Request Sent - ' . $booking['booking_number'];
        $user_subject = 'Payment Request for Booking - ' . $booking['booking_number'];
    } elseif ($type === 'confirmed') {
        $admin_subject = 'Booking Confirmed - ' . $booking['booking_number'];
        $user_subject = 'Booking Confirmed - ' . $booking['booking_number'];
    } elseif ($type === 'paid') {
        $admin_subject = 'Full Payment Received - ' . $booking['booking_number'];
        $user_subject = 'Payment Complete - Thank You! - ' . $booking['booking_number'];
    } else {
        $status_text = ucfirst($booking['booking_status']);
        $admin_subject = 'Booking Updated - ' . $booking['booking_number'];
        $user_subject = 'Booking Status Updated - ' . $booking['booking_number'];
    }
    
    // Generate email HTML
    $admin_message = generateBookingEmailHTML($booking, 'admin', $type, $old_status);
    $user_message = generateBookingEmailHTML($booking, 'user', $type, $old_status);
    
    // Send to admin (skip for payment_request and paid - those go to user only)
    if ($type !== 'payment_request' && $type !== 'paid') {
        if (!empty($admin_email)) {
            $results['admin'] = sendEmail($admin_email, $admin_subject, $admin_message);
            if ($results['admin']) {
                error_log("Booking notification email sent to admin: $admin_email for booking " . $booking['booking_number']);
            }
        } else {
            error_log("Admin email notification skipped for booking " . $booking['booking_number'] . " - admin email not configured in settings");
        }
    }
    
    // Send to user
    if (!empty($booking['email'])) {
        $results['user'] = sendEmail($booking['email'], $user_subject, $user_message, $booking['full_name']);
        if ($results['user']) {
            error_log("Booking notification email sent to customer: " . $booking['email'] . " for booking " . $booking['booking_number']);
        }
    } else {
        error_log("Customer email notification skipped for booking " . $booking['booking_number'] . " - customer email not provided");
    }
    
    return $results;
}

/**
 * Generate booking email HTML
 * 
 * @param array $booking Booking details
 * @param string $recipient Type of recipient (admin/user)
 * @param string $type Type of notification (new/update/payment_request/confirmed)
 * @param string $old_status Old status (for updates)
 * @return string HTML email content
 */
function generateBookingEmailHTML($booking, $recipient = 'user', $type = 'new', $old_status = '') {
    $site_name = getSetting('site_name', 'Venue Booking System');
    $contact_email = getSetting('contact_email', '');
    $contact_phone = getSetting('contact_phone', '');
    $whatsapp_number = getSetting('whatsapp_number', '');
    $google_review_link = getSetting('google_review_link') ?: 'https://g.page/r/CXn4LyBY3iY7EBM/review';
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 20px; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .detail-row { padding: 8px 0; border-bottom: 1px solid #eee; }
            .detail-label { font-weight: bold; color: #555; }
            .detail-value { color: #333; }
            .section-title { color: #4CAF50; font-size: 18px; margin: 20px 0 10px 0; border-bottom: 2px solid #4CAF50; padding-bottom: 5px; }
            .cost-row { display: flex; justify-content: space-between; padding: 5px 0; }
            .total-row { font-weight: bold; font-size: 18px; color: #4CAF50; border-top: 2px solid #4CAF50; padding-top: 10px; margin-top: 10px; }
            .footer { text-align: center; padding: 20px; color: #777; font-size: 14px; }
            .status-badge { display: inline-block; padding: 5px 15px; border-radius: 3px; font-weight: bold; }
            .status-pending { background-color: #fff3cd; color: #856404; }
            .status-confirmed { background-color: #d4edda; color: #155724; }
            .status-cancelled { background-color: #f8d7da; color: #721c24; }
            .status-completed { background-color: #d1ecf1; color: #0c5460; }
            .payment-notice { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php echo htmlspecialchars($site_name); ?></h1>
                <h2><?php
                    if ($type === 'new') echo 'Booking Confirmation';
                    elseif ($type === 'payment_request') echo 'Payment Request';
                    elseif ($type === 'confirmed') echo 'Booking Confirmed ✅';
                    elseif ($type === 'paid') echo 'Payment Complete ✅';
                    else echo 'Booking Update';
                ?></h2>
            </div>
            
            <div class="content">
                <?php if ($recipient === 'user'): ?>
                    <?php if ($type === 'new'): ?>
                        <p>Dear <?php echo htmlspecialchars($booking['full_name']); ?>,</p>
                        <p>Thank you for your booking! Your reservation has been successfully created.</p>
                    <?php elseif ($type === 'payment_request'): ?>
                        <p>Dear <?php echo htmlspecialchars($booking['full_name']); ?>,</p>
                        <?php 
                        $adv_info = getAdvanceDisplayInfo(
                            floatval($booking['grand_total']),
                            floatval($booking['advance_amount_received'] ?? 0)
                        );
                        $advance_display_label = 'Advance Payment Required' . ($adv_info['label'] ? ' ' . $adv_info['label'] : '');
                        ?>
                        <div class="payment-notice">
                            <strong>Payment Request</strong><br>
                            Your booking for <?php echo htmlspecialchars($booking['venue_name']); ?> on <?php echo convertToNepaliDate($booking['event_date']); ?> is almost confirmed.<br><br>
                            <strong>Total Amount:</strong> <?php echo formatCurrency($booking['grand_total']); ?><br>
                            <strong><?php echo $advance_display_label; ?>:</strong> <?php echo formatCurrency($adv_info['amount']); ?><br><br>
                            Please complete the advance payment at your earliest convenience to confirm your booking.
                        </div>
                    <?php elseif ($type === 'confirmed'): ?>
                        <p>Dear <?php echo htmlspecialchars($booking['full_name']); ?>,</p>
                        <p>We are pleased to confirm your booking. Please find your booking details below.</p>
                    <?php elseif ($type === 'paid'): ?>
                        <?php
                        $booked_packages = [];
                        if (!empty($booking['services'])) {
                            foreach ($booking['services'] as $svc) {
                                if (strtolower($svc['category'] ?? '') === 'package') {
                                    $booked_packages[] = htmlspecialchars($svc['service_name']);
                                }
                            }
                        }
                        $package_display = !empty($booked_packages)
                            ? implode(', ', $booked_packages)
                            : htmlspecialchars($booking['event_type']);
                        ?>
                        <p>Dear <?php echo htmlspecialchars($booking['full_name']); ?>,</p>
                        <p>On behalf of the entire team at <strong><?php echo htmlspecialchars($site_name); ?></strong>, we would like to extend our heartfelt gratitude for choosing us for your <strong><?php echo $package_display; ?></strong> event. Your full payment of <strong><?php echo formatCurrency($booking['grand_total']); ?></strong> has been received and your booking is now successfully completed.</p>
                        <p>It was a true honour and privilege for us to be a part of your special occasion. We sincerely hope that our services met your expectations. Should there be anything we could have done better, please know that we take all feedback very seriously. We are committed to continuously improving our services so that every guest has an exceptional experience.</p>
                        <p>We value your trust and look forward to being of service to you again in the future. Thank you sincerely for your confidence in <strong><?php echo htmlspecialchars($site_name); ?></strong>.</p>
                    <?php else: ?>
                        <p>Dear <?php echo htmlspecialchars($booking['full_name']); ?>,</p>
                        <p>Your booking status has been updated.</p>
                        <?php if (!empty($old_status)): ?>
                            <p><strong>Previous Status:</strong> <?php echo ucfirst($old_status); ?> → <strong>New Status:</strong> <?php echo ucfirst($booking['booking_status']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($type === 'new'): ?>
                        <p><strong>A new booking has been received:</strong></p>
                    <?php elseif ($type === 'payment_request'): ?>
                        <p><strong>Payment request sent for booking:</strong></p>
                    <?php elseif ($type === 'confirmed'): ?>
                        <p><strong>Booking confirmation sent for:</strong></p>
                    <?php elseif ($type === 'paid'): ?>
                        <p><strong>Full payment received for booking:</strong></p>
                    <?php else: ?>
                        <p><strong>Booking has been updated:</strong></p>
                        <?php if (!empty($old_status)): ?>
                            <p><strong>Status Change:</strong> <?php echo ucfirst($old_status); ?> → <?php echo ucfirst($booking['booking_status']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="booking-details">
                    <div class="section-title">Booking Information</div>
                    <div class="detail-row">
                        <span class="detail-label">Booking Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                            <?php echo ucfirst($booking['booking_status']); ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Status:</span>
                        <span class="detail-value"><?php echo ucfirst($booking['payment_status']); ?></span>
                    </div>
                </div>
                
                <div class="booking-details">
                    <div class="section-title">Customer Information</div>
                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
                    </div>
                    <?php if (!empty($booking['email'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="booking-details">
                    <div class="section-title">Event Details</div>
                    <div class="detail-row">
                        <span class="detail-label">Event Type:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['event_type']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value"><?php echo convertToNepaliDate($booking['event_date']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Shift:</span>
                        <span class="detail-value"><?php echo ucfirst($booking['shift']); ?><?php if (!empty($booking['start_time']) && !empty($booking['end_time'])): ?> (<?php echo formatBookingTime($booking['start_time']); ?> – <?php echo formatBookingTime($booking['end_time']); ?>)<?php endif; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Number of Guests:</span>
                        <span class="detail-value"><?php echo $booking['number_of_guests']; ?> persons</span>
                    </div>
                </div>
                
                <div class="booking-details">
                    <div class="section-title">Venue & Hall</div>
                    <div class="detail-row">
                        <span class="detail-label">Venue:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['venue_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Location:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['location']); ?></span>
                    </div>
                    <?php if (!empty($booking['venue_address'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Full Address:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['venue_address']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['map_link'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Google Map:</span>
                        <span class="detail-value"><a href="<?php echo htmlspecialchars($booking['map_link']); ?>">View on Google Maps</a></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label">Hall:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['hall_name']); ?> (<?php echo $booking['capacity']; ?> capacity)</span>
                    </div>
                </div>
                
                <?php if (!empty($booking['menus'])): ?>
                <div class="booking-details">
                    <div class="section-title">Selected Menus</div>
                    <?php foreach ($booking['menus'] as $menu): ?>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo htmlspecialchars($menu['menu_name']); ?>:</span>
                            <?php if ($recipient === 'admin'): ?>
                                <span class="detail-value"><?php echo $menu['number_of_guests']; ?> persons</span>
                            <?php else: ?>
                                <span class="detail-value"><?php echo formatCurrency($menu['price_per_person']); ?>/person × <?php echo $menu['number_of_guests']; ?> = <?php echo formatCurrency($menu['total_price']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['services'])): ?>
                <div class="booking-details">
                    <div class="section-title">Additional Services</div>
                    <?php foreach ($booking['services'] as $service): ?>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo htmlspecialchars($service['service_name']); ?>:</span>
                            <?php if ($recipient !== 'admin'): ?>
                                <span class="detail-value"><?php echo formatCurrency($service['price']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['special_requests'])): ?>
                <div class="booking-details">
                    <div class="section-title">Special Requests</div>
                    <p><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($recipient !== 'admin'): ?>
                <div class="booking-details">
                    <div class="section-title">Cost Breakdown</div>
                    <div class="cost-row">
                        <span>Hall Cost:</span>
                        <span><?php echo formatCurrency($booking['hall_price']); ?></span>
                    </div>
                    <?php if ($booking['menu_total'] > 0): ?>
                    <div class="cost-row">
                        <span>Menu Cost:</span>
                        <span><?php echo formatCurrency($booking['menu_total']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['services_total'] > 0): ?>
                    <div class="cost-row">
                        <span>Services Cost:</span>
                        <span><?php echo formatCurrency($booking['services_total']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="cost-row">
                        <span>Subtotal:</span>
                        <span><?php echo formatCurrency($booking['subtotal']); ?></span>
                    </div>
                    <?php if (floatval(getSetting('tax_rate', '13')) > 0): ?>
                    <div class="cost-row">
                        <span>Tax (<?php echo htmlspecialchars(getSetting('tax_rate', '13'), ENT_QUOTES, 'UTF-8'); ?>%):</span>
                        <span><?php echo formatCurrency($booking['tax_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="cost-row total-row">
                        <span>Grand Total:</span>
                        <span><?php echo formatCurrency($booking['grand_total']); ?></span>
                    </div>
                    <?php if ($type === 'payment_request' || $type === 'confirmed'): ?>
                        <?php 
                        $adv_info2 = getAdvanceDisplayInfo(
                            floatval($booking['grand_total']),
                            floatval($booking['advance_amount_received'] ?? 0)
                        );
                        if ($adv_info2['amount'] > 0):
                        ?>
                        <?php if ($type === 'payment_request'): ?>
                        <div class="cost-row" style="margin-top: 10px; border-top: 1px solid #ddd; background-color: #fff3cd; padding: 10px; border-radius: 3px;">
                            <span><strong>Advance Payment Required<?php echo ($adv_info2['label'] ? ' ' . $adv_info2['label'] : ''); ?>:</strong></span>
                            <span style="color: #856404; font-weight: bold; font-size: 18px;"><?php echo formatCurrency($adv_info2['amount']); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="cost-row" style="margin-top: 10px; border-top: 1px solid #ddd; background-color: #d4edda; padding: 10px; border-radius: 3px;">
                            <span><strong>Advance Received<?php echo ($adv_info2['label'] ? ' ' . $adv_info2['label'] : ''); ?>:</strong></span>
                            <span style="color: #155724; font-weight: bold; font-size: 18px;"><?php echo formatCurrency($adv_info2['amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <?php 
                // Get payment methods for this booking (only show if payment request or if methods are linked)
                if ($type === 'payment_request' || $type === 'new'):
                    $payment_methods = getBookingPaymentMethods($booking['id']);
                    if (!empty($payment_methods)): 
                ?>
                <div class="booking-details">
                    <div class="section-title">Payment Methods</div>
                    <p style="margin-bottom: 15px;">You can make payment using any of the following methods:</p>
                    <?php foreach ($payment_methods as $idx => $method): ?>
                        <div style="margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #4CAF50; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #4CAF50;"><?php echo htmlspecialchars($method['name']); ?></h4>
                            
                            <?php if (!empty($method['qr_code']) && validateUploadedFilePath($method['qr_code'])): ?>
                                <div style="margin: 10px 0;">
                                    <img src="<?php echo BASE_URL . '/' . UPLOAD_URL . htmlspecialchars($method['qr_code']); ?>" 
                                         alt="<?php echo htmlspecialchars($method['name']); ?> QR Code" 
                                         style="max-width: 250px; max-height: 250px; border: 2px solid #ddd; border-radius: 8px; padding: 10px; background: white;">
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($method['bank_details'])): ?>
                                <div style="background-color: white; padding: 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; white-space: pre-wrap; line-height: 1.6;">
                                    <?php echo htmlspecialchars($method['bank_details']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($idx < count($payment_methods) - 1): ?>
                            <div style="margin: 15px 0; text-align: center; color: #999;">- OR -</div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <?php if ($type === 'payment_request'): ?>
                        <p style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border-radius: 4px;">
                            <strong>Note:</strong> After making the payment, please inform us via WhatsApp or by phone with your booking number 
                            <strong><?php echo htmlspecialchars($booking['booking_number']); ?></strong> 
                            to confirm the payment.
                            <?php if (!empty($whatsapp_number)): ?>
                                <br>📱 WhatsApp / Phone: <strong><?php echo htmlspecialchars($whatsapp_number); ?></strong>
                            <?php elseif (!empty($contact_phone)): ?>
                                <br>📞 Phone: <strong><?php echo htmlspecialchars($contact_phone); ?></strong>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php 
                    endif;
                endif; 
                ?>
                <?php endif; // end: non-admin only sections ?>
                
                <?php if ($recipient === 'user'): ?>
                    <p style="margin-top: 20px;">If you have any questions about your booking, please don't hesitate to contact us.</p>
                <?php endif; ?>
                
                <?php if ($recipient === 'user' && $type === 'paid'): ?>
                <div class="booking-details" style="background-color: #f0f9f0; border-left: 4px solid #4CAF50; padding: 20px; margin-top: 20px;">
                    <div class="section-title">Share Your Experience ⭐</div>
                    <p>We would greatly appreciate it if you could take a moment to write a review about your experience with us. Your honest feedback not only helps us improve our services but also helps other families make informed decisions for their special occasions.</p>
                    <p style="text-align: center; margin: 15px 0;">
                        <a href="<?php echo htmlspecialchars($google_review_link); ?>" 
                           style="display: inline-block; background-color: #4CAF50; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                            ⭐ Write a Google Review
                        </a>
                    </p>
                    <p style="font-size: 13px; color: #555; text-align: center;">It only takes a minute and means the world to our entire team.</p>
                </div>
                <?php endif; ?>
                
                <?php if ($type === 'confirmed'):
                    $assigned_vendors = getBookingVendorAssignments($booking['id']);
                    if (!empty($assigned_vendors)): ?>
                <div class="booking-details">
                    <div class="section-title">Assigned Vendors</div>
                    <?php foreach ($assigned_vendors as $va): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php echo htmlspecialchars(getVendorTypeLabel($va['vendor_type'])); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($va['vendor_name']); ?>
                            <?php if (!empty($va['vendor_description'])): ?>
                                <br><small><?php echo htmlspecialchars($va['vendor_description']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($va['vendor_city'])): ?>
                                <br><small><?php echo htmlspecialchars($va['vendor_city']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($va['vendor_phone'])): ?>
                                &nbsp;|&nbsp; <?php echo htmlspecialchars($va['vendor_phone']); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                    <?php endif; endif; ?>
            </div>
            
            <div class="footer">
                <p><strong><?php echo htmlspecialchars($site_name); ?></strong></p>
                <?php if ($contact_phone): ?>
                    <p>Phone: <?php echo htmlspecialchars($contact_phone); ?></p>
                <?php endif; ?>
                <?php if ($contact_email): ?>
                    <p>Email: <?php echo htmlspecialchars($contact_email); ?></p>
                <?php endif; ?>
                <p style="margin-top: 15px; font-size: 12px; color: #999;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Get active payment methods
 * @return array Array of active payment methods
 */
function getActivePaymentMethods() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM payment_methods WHERE status = 'active' ORDER BY display_order ASC, name ASC");
    return $stmt->fetchAll();
}

/**
 * Get payment methods for a booking
 * @param int $booking_id Booking ID
 * @return array Array of payment methods assigned to the booking
 */
function getBookingPaymentMethods($booking_id) {
    $db = getDB();
    $sql = "SELECT pm.* FROM payment_methods pm
            INNER JOIN booking_payment_methods bpm ON pm.id = bpm.payment_method_id
            WHERE bpm.booking_id = ?
            ORDER BY pm.display_order ASC, pm.name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$booking_id]);
    return $stmt->fetchAll();
}

/**
 * Link payment methods to a booking
 * @param int $booking_id Booking ID
 * @param array $payment_method_ids Array of payment method IDs
 * @return bool Success status
 */
function linkPaymentMethodsToBooking($booking_id, $payment_method_ids) {
    if (empty($payment_method_ids)) {
        return true; // No payment methods to link
    }
    
    $db = getDB();
    
    try {
        // Delete existing payment method associations
        $stmt = $db->prepare("DELETE FROM booking_payment_methods WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        
        // Insert new associations
        $stmt = $db->prepare("INSERT INTO booking_payment_methods (booking_id, payment_method_id) VALUES (?, ?)");
        foreach ($payment_method_ids as $method_id) {
            $stmt->execute([$booking_id, intval($method_id)]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Error linking payment methods: ' . $e->getMessage());
        return false;
    }
}

/**
 * Record a payment transaction
 * @param array $data Payment data (booking_id, payment_method_id, transaction_id, paid_amount, payment_slip, notes)
 * @return array Result with success status and payment_id or error message
 */
function recordPayment($data) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Validate required fields
        if (empty($data['booking_id'])) {
            throw new Exception('Booking ID is required.');
        }
        if (empty($data['paid_amount'])) {
            throw new Exception('Paid amount is required.');
        }
        
        // Insert payment record
        $sql = "INSERT INTO payments (booking_id, payment_method_id, transaction_id, paid_amount, payment_slip, notes) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['booking_id'],
            $data['payment_method_id'] ?? null,
            $data['transaction_id'] ?? null,
            $data['paid_amount'],
            $data['payment_slip'] ?? null,
            $data['notes'] ?? null
        ]);
        
        $payment_id = $db->lastInsertId();
        
        // Update booking status
        if (isset($data['update_booking_status']) && $data['update_booking_status']) {
            // Update booking status to payment_submitted if with payment
            $stmt = $db->prepare("UPDATE bookings SET booking_status = 'payment_submitted' WHERE id = ?");
            $stmt->execute([$data['booking_id']]);
        }
        
        // Calculate total paid amount for this booking
        $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total_paid FROM payments WHERE booking_id = ?");
        $stmt->execute([$data['booking_id']]);
        $result = $stmt->fetch();
        $total_paid = floatval($result['total_paid']);
        
        // Get booking grand total
        $stmt = $db->prepare("SELECT grand_total FROM bookings WHERE id = ?");
        $stmt->execute([$data['booking_id']]);
        $booking = $stmt->fetch();
        $grand_total = $booking['grand_total'] ?? 0;
        
        // Update payment status
        $payment_status = 'pending';
        if ($total_paid >= $grand_total) {
            $payment_status = 'paid';
        } elseif ($total_paid > 0) {
            $payment_status = 'partial';
        }
        
        $stmt = $db->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
        $stmt->execute([$payment_status, $data['booking_id']]);
        
        $db->commit();
        
        return ['success' => true, 'payment_id' => $payment_id];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get payments for a booking
 * @param int $booking_id Booking ID
 * @return array Array of payments
 */
function getBookingPayments($booking_id) {
    $db = getDB();
    $sql = "SELECT p.*, pm.name as payment_method_name 
            FROM payments p
            LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
            WHERE p.booking_id = ?
            ORDER BY p.payment_date DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$booking_id]);
    return $stmt->fetchAll();
}

/**
 * Calculate payment summary for a booking
 * Single source of truth for all payment calculations
 * 
 * Formula:
 * - Subtotal = Hall Price + Menu Total + Services Total
 * - Tax Amount = (Tax Rate > 0) ? (Subtotal × Tax Rate / 100) : 0
 * - Grand Total = Subtotal + Tax Amount
 * - Paid Amount = SUM(paid_amount) from verified payments
 * - Due Amount = max(0, Grand Total - Paid Amount)
 * 
 * @param int $booking_id Booking ID
 * @return array Payment summary with keys: subtotal, tax_amount, grand_total, total_paid, due_amount, advance_amount, advance_percentage, advance_amount_received
 * @throws Exception if booking_id is invalid or booking not found
 */
function calculatePaymentSummary($booking_id) {
    // Validate booking_id
    $booking_id = intval($booking_id);
    if ($booking_id <= 0) {
        throw new Exception("Invalid booking ID: {$booking_id}");
    }
    
    $db = getDB();
    
    // Get booking totals, advance payment status and actual received amount from database
    $stmt = $db->prepare("SELECT hall_price, menu_total, services_total, subtotal, tax_amount, grand_total,
                                  advance_payment_received, advance_amount_received
                          FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        throw new Exception("Booking not found: {$booking_id}");
    }
    
    // Calculate total paid from verified payments only
    $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total_paid 
                          FROM payments 
                          WHERE booking_id = ? AND payment_status = 'verified'");
    $stmt->execute([$booking_id]);
    $payment_result = $stmt->fetch();
    $total_paid = floatval($payment_result['total_paid']);
    
    // Calculate vendor assignments total
    $stmt = $db->prepare("SELECT COALESCE(SUM(assigned_amount), 0) as vendors_total FROM booking_vendor_assignments WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $vendor_result = $stmt->fetch();
    $vendors_total = floatval($vendor_result['vendors_total']);

    // Calculate grand total
    $grand_total = floatval($booking['grand_total']);

    // Actual advance amount received (manually entered by admin)
    $advance_amount_received = floatval($booking['advance_amount_received'] ?? 0);

    // Due amount = grand total minus all verified payments recorded
    // (advance is already included in total_paid if recorded via Record Payment)
    $due_amount = max(0.0, $grand_total - $total_paid);
    
    // Calculate advance payment info (percentage-based, for reference display only)
    $advance = calculateAdvancePayment($grand_total);
    
    return [
        'subtotal' => floatval($booking['subtotal']),
        'tax_amount' => floatval($booking['tax_amount']),
        'grand_total' => $grand_total,
        'vendors_total' => $vendors_total,
        'total_paid' => $total_paid,
        'due_amount' => $due_amount,
        'advance_amount' => $advance['amount'],
        'advance_percentage' => $advance['percentage'],
        'advance_amount_received' => $advance_amount_received,
    ];
}

/**
 * Get company logo for invoices with proper validation and fallback
 * Returns an array with 'path' (validated safe path) and 'url' (for display)
 * 
 * @return array|null Array with 'path' and 'url' keys, or null if no valid logo exists
 */
function getCompanyLogo() {
    // Try company_logo first
    $logo_filename = getSetting('company_logo', '');
    
    // Fallback to site_logo if company_logo is empty
    if (empty($logo_filename)) {
        $logo_filename = getSetting('site_logo', '');
    }
    
    if (empty($logo_filename)) {
        return null;
    }
    
    // Validate the file path for security
    if (!validateUploadedFilePath($logo_filename)) {
        return null;
    }
    
    // Return validated path and URL with proper encoding
    return [
        'path' => UPLOAD_PATH . $logo_filename,
        'url' => UPLOAD_URL . rawurlencode($logo_filename),
        'filename' => $logo_filename
    ];
}

/**
 * Get all active menus (for admin interface)
 */
function getAllActiveMenus() {
    $db = getDB();
    $sql = "SELECT id, name, price_per_person FROM menus WHERE status = 'active' ORDER BY name";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

/**
 * Get assigned menu IDs for a hall
 */
function getAssignedMenuIds($hall_id) {
    $db = getDB();
    try {
        $sql = "SELECT menu_id FROM hall_menus WHERE hall_id = ? AND status = 'active'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$hall_id]);
        return array_column($stmt->fetchAll(), 'menu_id');
    } catch (PDOException $e) {
        error_log("getAssignedMenuIds failed (hall_id=$hall_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Update hall-menu assignments
 */
function updateHallMenus($hall_id, $menu_ids) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Get current assignments
        $current_menus = getAssignedMenuIds($hall_id);
        
        // Convert to arrays for comparison
        $menu_ids = is_array($menu_ids) ? $menu_ids : [];
        $menu_ids = array_map('intval', $menu_ids);
        
        // Find menus to add
        $menus_to_add = array_diff($menu_ids, $current_menus);
        
        // Find menus to remove (mark as inactive)
        $menus_to_remove = array_diff($current_menus, $menu_ids);
        
        // Add new menu assignments
        foreach ($menus_to_add as $menu_id) {
            // Check if assignment already exists but is inactive
            $stmt = $db->prepare("SELECT id FROM hall_menus WHERE hall_id = ? AND menu_id = ?");
            $stmt->execute([$hall_id, $menu_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Reactivate existing assignment
                $stmt = $db->prepare("UPDATE hall_menus SET status = 'active' WHERE id = ?");
                $stmt->execute([$existing['id']]);
            } else {
                // Create new assignment
                $stmt = $db->prepare("INSERT INTO hall_menus (hall_id, menu_id, status) VALUES (?, ?, 'active')");
                $stmt->execute([$hall_id, $menu_id]);
            }
        }
        
        // Mark removed menus as inactive
        foreach ($menus_to_remove as $menu_id) {
            $stmt = $db->prepare("UPDATE hall_menus SET status = 'inactive' WHERE hall_id = ? AND menu_id = ?");
            $stmt->execute([$hall_id, $menu_id]);
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error updating hall menus: " . $e->getMessage());
        return false;
    }
}

/**
 * Add admin service to a booking
 * Admins can add additional services after the booking is created
 * 
 * @param int $booking_id Booking ID
 * @param string $service_name Service name
 * @param string $description Service description (optional)
 * @param int $quantity Quantity
 * @param float $price Price per unit
 * @return bool|int Returns service ID on success, false on failure
 */
function addAdminService($booking_id, $service_name, $description, $quantity, $price, $design_id = 0, $catalog_service_id = 0) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Validate inputs
        $booking_id = intval($booking_id);
        $service_name = trim($service_name);
        $description = trim($description);
        $quantity = max(1, intval($quantity));
        $price = floatval($price);
        $design_id = max(0, intval($design_id));
        $catalog_service_id = max(0, intval($catalog_service_id));
        
        if (empty($service_name)) {
            throw new Exception("Service name is required");
        }
        
        if ($price <= 0) {
            throw new Exception("Price must be greater than 0");
        }
        
        // Store the catalog service_id reference when available so photo/vendor-type lookups work
        $service_ref_id = $catalog_service_id > 0 ? $catalog_service_id : ADMIN_SERVICE_NO_REF_ID;

        // Insert admin service (include design_id when provided)
        $stmt = $db->prepare("
            INSERT INTO booking_services 
            (booking_id, service_id, service_name, price, description, category, added_by, quantity, design_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$booking_id, $service_ref_id, $service_name, $price, $description, ADMIN_SERVICE_DEFAULT_CATEGORY, ADMIN_SERVICE_TYPE, $quantity, $design_id > 0 ? $design_id : null]);
        $service_id = $db->lastInsertId();
        
        // Recalculate booking totals
        recalculateBookingTotals($booking_id);
        
        $db->commit();
        return $service_id;
    } catch (PDOException $e) {
        $db->rollBack();
        $error_code = $e->getCode();
        $error_msg = $e->getMessage();
        
        // Check for specific errors without exposing full message
        if (strpos($error_msg, "Unknown column '" . BOOKING_SERVICE_ADDED_BY_COLUMN . "'") !== false || 
            strpos($error_msg, "Unknown column '" . BOOKING_SERVICE_QUANTITY_COLUMN . "'") !== false) {
            error_log("ADMIN SERVICES ERROR: Database schema is missing required columns (" . BOOKING_SERVICE_ADDED_BY_COLUMN . ", " . BOOKING_SERVICE_QUANTITY_COLUMN . "). Please run fix_admin_services.php or apply database migration.");
        } else {
            // Log generic database error without details
            error_log("Admin service database error. Error code: " . $error_code);
        }
        
        return false;
    } catch (Exception $e) {
        $db->rollBack();
        // Log general error without sensitive details
        error_log("Error adding admin service: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a predefined service package to a booking.
 * The package is stored in booking_services with category = PACKAGE_SERVICE_CATEGORY
 * and added_by = ADMIN_SERVICE_TYPE so it can be managed like an admin service.
 *
 * @param int $booking_id  Booking ID
 * @param int $package_id  ID from service_packages table
 * @param int $quantity    Quantity (default 1)
 * @return bool|int Returns booking_service ID on success, false on failure
 */
function addPackageToBooking($booking_id, $package_id, $quantity = 1) {
    $db = getDB();

    try {
        $booking_id = intval($booking_id);
        $package_id = intval($package_id);
        $quantity   = max(1, intval($quantity));

        // Fetch package details
        $stmt = $db->prepare("SELECT * FROM service_packages WHERE id = ? AND status = 'active'");
        $stmt->execute([$package_id]);
        $package = $stmt->fetch();

        if (!$package) {
            throw new Exception("Package not found or is inactive");
        }

        $price       = floatval($package['price']);
        $name        = trim($package['name']);
        $description = trim($package['description'] ?? '');

        if ($price <= 0) {
            throw new Exception("Package price must be greater than 0");
        }

        $db->beginTransaction();

        $insert = $db->prepare("
            INSERT INTO booking_services
            (booking_id, service_id, service_name, price, description, category, added_by, quantity)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $booking_id,
            $package_id,
            $name,
            $price,
            $description,
            PACKAGE_SERVICE_CATEGORY,
            ADMIN_SERVICE_TYPE,
            $quantity,
        ]);
        $service_id = $db->lastInsertId();

        recalculateBookingTotals($booking_id);

        $db->commit();
        return $service_id;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error adding package to booking: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete admin service from a booking
 * Only admin-added services can be deleted
 * 
 * @param int $service_id Service ID
 * @return bool Success status
 */
function deleteAdminService($service_id) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Get service details and verify it's an admin service
        $stmt = $db->prepare("SELECT booking_id, added_by FROM booking_services WHERE id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            throw new Exception("Service not found");
        }
        
        if ($service['added_by'] !== ADMIN_SERVICE_TYPE) {
            throw new Exception("Only admin-added services can be deleted");
        }
        
        $booking_id = $service['booking_id'];
        
        // Delete the service
        $stmt = $db->prepare("DELETE FROM booking_services WHERE id = ?");
        $stmt->execute([$service_id]);
        
        // Recalculate booking totals
        recalculateBookingTotals($booking_id);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error deleting admin service: " . $e->getMessage());
        return false;
    }
}

/**
 * Recalculate booking totals after adding/removing admin services
 * This function recalculates all totals based on actual data in the database
 * 
 * @param int $booking_id Booking ID
 * @return bool Success status
 */
function recalculateBookingTotals($booking_id) {
    $db = getDB();
    
    try {
        // Get current booking data
        $stmt = $db->prepare("SELECT hall_id FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            throw new Exception("Booking not found");
        }
        
        // Calculate hall price
        $stmt = $db->prepare("SELECT base_price FROM halls WHERE id = ?");
        $stmt->execute([$booking['hall_id']]);
        $hall = $stmt->fetch();
        $hall_price = floatval($hall['base_price'] ?? 0);
        
        // Calculate menu total from booking_menus
        $stmt = $db->prepare("SELECT SUM(total_price) as total FROM booking_menus WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $result = $stmt->fetch();
        $menu_total = floatval($result['total'] ?? 0);
        
        // Calculate total from all services (user + admin)
        $stmt = $db->prepare("SELECT SUM(price * quantity) as total FROM booking_services WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $result = $stmt->fetch();
        $services_total = floatval($result['total'] ?? 0);
        
        // Calculate total from vendor assignments
        $stmt = $db->prepare("SELECT COALESCE(SUM(assigned_amount), 0) as total FROM booking_vendor_assignments WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $result = $stmt->fetch();
        $vendors_total = floatval($result['total'] ?? 0);
        
        // Calculate new totals (vendor charge is included in subtotal)
        $subtotal = $hall_price + $menu_total + $services_total + $vendors_total;
        
        $tax_rate = floatval(getSetting('tax_rate', '13'));
        $tax_amount = $subtotal * ($tax_rate / 100);
        $grand_total = $subtotal + $tax_amount;
        
        // Update booking totals
        $stmt = $db->prepare("
            UPDATE bookings 
            SET hall_price = ?, menu_total = ?, services_total = ?, subtotal = ?, tax_amount = ?, grand_total = ?
            WHERE id = ?
        ");
        $stmt->execute([$hall_price, $menu_total, $services_total, $subtotal, $tax_amount, $grand_total, $booking_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error recalculating booking totals: " . $e->getMessage());
        return false;
    }
}

/**
 * Get admin services for a booking
 * 
 * @param int $booking_id Booking ID
 * @return array Array of admin services
 */
function getAdminServices($booking_id) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT id, service_name, description, quantity, price, 
                   (price * quantity) as total_price,
                   created_at
            FROM booking_services 
            WHERE booking_id = ? AND added_by = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$booking_id, ADMIN_SERVICE_TYPE]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting admin services: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user services for a booking (services selected during booking)
 * 
 * @param int $booking_id Booking ID
 * @return array Array of user services
 */
function getUserServices($booking_id) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT id, service_id, service_name, description, category, price, quantity,
                   (price * quantity) as total_price
            FROM booking_services 
            WHERE booking_id = ? AND added_by = ?
            ORDER BY service_name
        ");
        $stmt->execute([$booking_id, USER_SERVICE_TYPE]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting user services: " . $e->getMessage());
        return [];
    }
}

// ============================================================
// VENDOR MANAGEMENT FUNCTIONS
// ============================================================

/**
 * Get all active vendors, optionally filtered by type
 *
 * @param string|null $type Vendor type filter
 * @return array
 */
function getVendors($type = null) {
    $db = getDB();
    try {
        if ($type) {
            $stmt = $db->prepare("SELECT v.*, c.name AS city_name FROM vendors v LEFT JOIN cities c ON v.city_id = c.id WHERE v.status = 'active' AND v.type = ? ORDER BY v.name");
            $stmt->execute([$type]);
        } else {
            $stmt = $db->prepare("SELECT v.*, c.name AS city_name FROM vendors v LEFT JOIN cities c ON v.city_id = c.id WHERE v.status = 'active' ORDER BY v.id DESC");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting vendors: " . $e->getMessage());
        return [];
    }
}

/**
 * Get active vendors not yet assigned to any booking on the given event date
 *
 * @param string $event_date  Date string (YYYY-MM-DD)
 * @return array
 */
function getAvailableVendors($event_date, $current_booking_id = 0) {
    $db = getDB();
    try {
        $current_booking_id = intval($current_booking_id);
        // Exclude vendors assigned to OTHER bookings on the same date.
        // Vendors already assigned to the current booking remain available so they
        // can be assigned to additional services within the same booking.
        $booking_exclude = $current_booking_id > 0 ? 'AND bva.booking_id != ?' : '';
        $stmt = $db->prepare("
            SELECT v.*, c.name AS city_name
            FROM vendors v
            LEFT JOIN cities c ON v.city_id = c.id
            WHERE v.status = 'active'
              AND v.id NOT IN (
                  SELECT DISTINCT bva.vendor_id
                  FROM booking_vendor_assignments bva
                  INNER JOIN bookings b ON bva.booking_id = b.id
                  WHERE b.event_date = ?
                    $booking_exclude
              )
            ORDER BY v.type, v.name
        ");
        $params = [$event_date];
        if ($current_booking_id > 0) {
            $params[] = $current_booking_id;
        }
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting available vendors: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a single vendor by ID
 *
 * @param int $vendor_id
 * @return array|false
 */
function getVendor($vendor_id) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT v.*, c.name AS city_name FROM vendors v LEFT JOIN cities c ON v.city_id = c.id WHERE v.id = ?");
        $stmt->execute([intval($vendor_id)]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting vendor: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all photos for a vendor from the vendor_photos table
 *
 * @param int $vendor_id
 * @return array
 */
function getVendorPhotos($vendor_id) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT id, image_path, is_primary, display_order FROM vendor_photos WHERE vendor_id = ? ORDER BY is_primary DESC, display_order ASC, id ASC");
        $stmt->execute([intval($vendor_id)]);
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
    $photos = [];
    foreach ($rows as $row) {
        $safe_filename = !empty($row['image_path']) ? basename($row['image_path']) : '';
        if (!empty($safe_filename) && preg_match(SAFE_FILENAME_PATTERN, $safe_filename)
            && file_exists(UPLOAD_PATH . $safe_filename)) {
            $photos[] = [
                'id'         => $row['id'],
                'image_path' => $safe_filename,
                'is_primary' => $row['is_primary'],
            ];
        }
    }
    return $photos;
}

/**
 * Get the primary photo URL for multiple vendors at once (avoids N+1 queries).
 *
 * @param int[] $vendor_ids
 * @return array Associative array keyed by vendor_id with the primary photo URL string (or '' if none).
 */
function getVendorPrimaryPhotoUrls(array $vendor_ids) {
    if (empty($vendor_ids)) {
        return [];
    }
    $db = getDB();
    $normalized_ids = array_map('intval', $vendor_ids);
    $valid_ids = array_filter($normalized_ids, fn($id) => $id > 0);
    $ids = array_values(array_unique($valid_ids));
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $db->prepare("
            SELECT vendor_id, image_path
            FROM vendor_photos
            WHERE vendor_id IN ($placeholders)
            ORDER BY vendor_id, is_primary DESC, display_order ASC, id ASC
        ");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
    $result = [];
    foreach ($rows as $row) {
        $vid = (int)$row['vendor_id'];
        if (isset($result[$vid])) {
            continue; // already captured the best photo for this vendor
        }
        $safe = !empty($row['image_path']) ? basename($row['image_path']) : '';
        if (!empty($safe) && preg_match(SAFE_FILENAME_PATTERN, $safe)
            && file_exists(UPLOAD_PATH . $safe)) {
            $result[$vid] = UPLOAD_URL . $safe;
        }
    }

    if (count($result) < count($ids)) {
        $missing_ids = array_values(array_diff($ids, array_keys($result)));
        if (empty($missing_ids)) {
            return $result;
        }
        $fallback_placeholders = implode(',', array_fill(0, count($missing_ids), '?'));
        try {
            $fallback_stmt = $db->prepare("SELECT id, photo FROM vendors WHERE id IN ($fallback_placeholders) AND photo IS NOT NULL AND photo != ''");
            $fallback_stmt->execute($missing_ids);
            $fallback_rows = $fallback_stmt->fetchAll();
        } catch (Exception $e) {
            $fallback_rows = [];
        }

        foreach ($fallback_rows as $row) {
            $vid = (int)$row['id'];
            if (isset($result[$vid])) {
                continue;
            }
            $safe = !empty($row['photo']) ? basename($row['photo']) : '';
            if (!empty($safe) && preg_match(SAFE_FILENAME_PATTERN, $safe)
                && file_exists(UPLOAD_PATH . $safe)) {
                $result[$vid] = UPLOAD_URL . $safe;
            }
        }
    }

    return $result;
}

/**
 * Get the primary photo URL for multiple additional_services at once (avoids N+1 queries).
 * Uses the `photo` column stored directly on the additional_services table.
 *
 * @param int[] $service_ids  Values of additional_services.id (booking_services.service_id)
 * @return array Associative array keyed by service_id with the photo URL string (or '' if none).
 */
function getServicePrimaryPhotoUrls(array $service_ids) {
    if (empty($service_ids)) {
        return [];
    }
    $db = getDB();
    $ids = array_filter(array_map('intval', $service_ids), fn($id) => $id > 0);
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $db->prepare("SELECT id, photo FROM additional_services WHERE id IN ($placeholders) AND photo IS NOT NULL AND photo != ''");
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
    $result = [];
    foreach ($rows as $row) {
        $sid = (int)$row['id'];
        $safe = !empty($row['photo']) ? basename($row['photo']) : '';
        if (!empty($safe) && preg_match(SAFE_FILENAME_PATTERN, $safe)
            && file_exists(UPLOAD_PATH . $safe)) {
            $result[$sid] = UPLOAD_URL . $safe;
        }
    }
    return $result;
}

/**
 * Get the primary photo URL for multiple service_packages at once (avoids N+1 queries).
 * Fetches from the service_package_photos table ordered by display_order / id.
 *
 * @param int[] $package_ids  Values of service_packages.id (booking_services.service_id for package rows)
 * @return array Associative array keyed by package_id with the photo URL string.
 */
function getPackagePrimaryPhotoUrls(array $package_ids) {
    if (empty($package_ids)) {
        return [];
    }
    $db = getDB();
    $ids = array_filter(array_map('intval', $package_ids), fn($id) => $id > 0);
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $db->prepare(
            "SELECT package_id, image_path FROM service_package_photos
             WHERE package_id IN ($placeholders)
             ORDER BY package_id, display_order ASC, id ASC"
        );
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
    $result = [];
    foreach ($rows as $row) {
        $pid = (int)$row['package_id'];
        if (isset($result[$pid])) {
            continue; // keep first (lowest display_order) photo per package
        }
        $safe = !empty($row['image_path']) ? basename($row['image_path']) : '';
        if (!empty($safe) && preg_match(SAFE_FILENAME_PATTERN, $safe)
            && file_exists(UPLOAD_PATH . $safe)) {
            $result[$pid] = UPLOAD_URL . $safe;
        }
    }
    return $result;
}

/**
 * Get vendor assignments for a booking
 *
 * @param int $booking_id
 * @return array
 */
function getBookingVendorAssignments($booking_id) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT bva.*, v.name as vendor_name, v.type as vendor_type, v.phone as vendor_phone,
                   v.email as vendor_email, v.short_description as vendor_description,
                   c.name as vendor_city
            FROM booking_vendor_assignments bva
            INNER JOIN vendors v ON bva.vendor_id = v.id
            LEFT JOIN cities c ON v.city_id = c.id
            WHERE bva.booking_id = ?
            ORDER BY bva.booking_service_id IS NULL, bva.booking_service_id, v.type, v.name
        ");
        $stmt->execute([intval($booking_id)]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting booking vendor assignments: " . $e->getMessage());
        return [];
    }
}

/**
 * Add a vendor assignment to a booking
 *
 * @param int $booking_id
 * @param int $vendor_id
 * @param string $task_description
 * @param float $assigned_amount
 * @param string $notes
 * @param int|null $booking_service_id Optional booking_services.id to link the assignment to a specific service
 * @return int|false New assignment ID or false on failure
 */
function addVendorAssignment($booking_id, $vendor_id, $task_description, $assigned_amount, $notes, $booking_service_id = null) {
    $db = getDB();
    try {
        $booking_id = intval($booking_id);
        $vendor_id = intval($vendor_id);
        $task_description = trim($task_description);
        $assigned_amount = max(0, floatval($assigned_amount));
        $notes = trim($notes);
        $booking_service_id = ($booking_service_id !== null && intval($booking_service_id) > 0)
            ? intval($booking_service_id)
            : null;

        if ($vendor_id <= 0) {
            throw new Exception("A valid vendor must be selected");
        }

        // Prevent duplicate: same vendor already assigned to the same booking service
        if ($booking_service_id !== null) {
            $chk = $db->prepare("SELECT COUNT(*) FROM booking_vendor_assignments WHERE booking_id = ? AND booking_service_id = ? AND vendor_id = ?");
            $chk->execute([$booking_id, $booking_service_id, $vendor_id]);
            if ((int)$chk->fetchColumn() > 0) {
                throw new Exception("This vendor is already assigned to this service");
            }
        }

        $stmt = $db->prepare("
            INSERT INTO booking_vendor_assignments (booking_id, booking_service_id, vendor_id, task_description, assigned_amount, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'assigned')
        ");
        $stmt->execute([$booking_id, $booking_service_id, $vendor_id, $task_description, $assigned_amount, $notes]);
        $new_id = (int)$db->lastInsertId();
        recalculateBookingTotals($booking_id);
        return $new_id;
    } catch (Exception $e) {
        error_log("Error adding vendor assignment: " . $e->getMessage());
        return false;
    }
}

/**
 * Update the status of a vendor assignment
 *
 * @param int $assignment_id
 * @param string $status
 * @return bool
 */
function updateVendorAssignmentStatus($assignment_id, $status) {
    $allowed = ['assigned', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }
    $db = getDB();
    try {
        $stmt = $db->prepare("UPDATE booking_vendor_assignments SET status = ? WHERE id = ?");
        $stmt->execute([$status, intval($assignment_id)]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating vendor assignment status: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a vendor assignment
 *
 * @param int $assignment_id
 * @return bool
 */
function deleteVendorAssignment($assignment_id) {
    $db = getDB();
    try {
        $assignment_id = intval($assignment_id);
        // Get booking_id before deleting so we can recalculate totals
        $stmt = $db->prepare("SELECT booking_id FROM booking_vendor_assignments WHERE id = ?");
        $stmt->execute([$assignment_id]);
        $row = $stmt->fetch();
        $booking_id = $row ? intval($row['booking_id']) : 0;

        $stmt = $db->prepare("DELETE FROM booking_vendor_assignments WHERE id = ?");
        $stmt->execute([$assignment_id]);

        if ($booking_id > 0) {
            recalculateBookingTotals($booking_id);
        }
        return true;
    } catch (Exception $e) {
        error_log("Error deleting vendor assignment: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all active vendor types from the database.
 * Results are cached in a static variable for the request lifetime.
 *
 * @return array Array of ['slug' => ..., 'label' => ..., ...]
 */
function getVendorTypes() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM vendor_types WHERE status = 'active' ORDER BY display_order ASC, label ASC");
        $cache = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting vendor types: " . $e->getMessage());
        $cache = [];
    }
    return $cache;
}

/**
 * Get human-readable label for a vendor type slug.
 * Looks up the vendor_types table; falls back to ucfirst($type).
 *
 * @param string $type
 * @return string
 */
function getVendorTypeLabel($type) {
    foreach (getVendorTypes() as $vt) {
        if ($vt['slug'] === $type) {
            return $vt['label'];
        }
    }
    return ucfirst($type);
}

/**
 * Get Bootstrap badge colour for a vendor assignment status
 *
 * @param string $status
 * @return string Bootstrap colour class suffix
 */
function getVendorAssignmentStatusColor($status) {
    $colors = [
        'assigned'  => 'secondary',
        'confirmed' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger',
    ];
    return $colors[$status] ?? 'secondary';
}

/**
 * Build a WhatsApp notification URL for a vendor assignment.
 *
 * @param string $vendor_name
 * @param string $vendor_phone
 * @param array  $booking  Booking row from getBookingDetails()
 * @return string WhatsApp URL, or empty string if no phone available
 */
function buildVendorAssignmentWhatsAppUrl($vendor_name, $vendor_phone, $booking, $vendor_type = '') {
    $clean_phone = preg_replace('/[^0-9]/', '', $vendor_phone);
    if (empty($clean_phone)) {
        return '';
    }

    $text  = "📋 *Assignment Notice*\n\n";
    $text .= "Dear *" . strip_tags($vendor_name) . "*,\n\n";
    $text .= "You have been assigned for an upcoming event.\n\n";
    if (!empty($vendor_type)) {
        $text .= "🎯 Service: *" . strip_tags(getVendorTypeLabel($vendor_type)) . "*\n";
    }
    $text .= "📅 " . convertToNepaliDate($booking['event_date']) . " (" . date('d M Y', strtotime($booking['event_date'])) . ")\n";
    $text .= "🕐 " . getBookingShiftTimeDisplay($booking) . "\n";
    $text .= "🎉 " . strip_tags($booking['event_type']) . "\n";
    $text .= "🏛️ *" . strip_tags($booking['venue_name']) . "*\n";
    if (!empty($booking['hall_name'])) {
        $text .= "🚪 " . strip_tags($booking['hall_name']) . "\n";
    }
    if (!empty($booking['venue_address'])) {
        $text .= "🏠 " . strip_tags($booking['venue_address']) . "\n";
    }
    if (!empty($booking['map_link'])) {
        $text .= "🗺️ " . strip_tags($booking['map_link']) . "\n";
    }
    $text .= "\nPlease confirm your availability by replying to this message.\n\n";
    $text .= "*" . strip_tags(getSetting('company_name', 'Booking Team')) . "*\n";
    $contact_phone = getSetting('contact_phone', '');
    if (!empty($contact_phone)) {
        $text .= "📞 " . $contact_phone;
    }

    return 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode($text);
}

/**
 * Build a WhatsApp notification URL to inform the venue provider about a confirmed booking.
 *
 * @param array $booking  Booking row from getBookingDetails() (must include venue_contact_phone)
 * @return string WhatsApp URL, or empty string if no venue phone available
 */
function buildVenueProviderWhatsAppUrl($booking) {
    $clean_phone = preg_replace('/[^0-9]/', '', $booking['venue_contact_phone'] ?? '');
    if (empty($clean_phone)) {
        return '';
    }

    $nepali_date   = convertToNepaliDate($booking['event_date']);
    $company_name  = getSetting('company_name', getSetting('site_name', 'Booking Team'));
    $contact_phone = getSetting('contact_phone', '');

    $text  = "🏛️ *Booking Notice – " . strip_tags($booking['venue_name']) . "*\n";
    $text .= "🔖 Ref: *" . strip_tags($booking['booking_number']) . "*\n\n";
    $text .= "A new confirmed booking has been scheduled at your venue.\n\n";
    $text .= "📅 " . $nepali_date . " (" . date('d M Y', strtotime($booking['event_date'])) . ")\n";
    $text .= "🕐 " . getBookingShiftTimeDisplay($booking) . "\n";
    $text .= "🎉 " . strip_tags($booking['event_type']) . "\n";
    if (!empty($booking['hall_name'])) {
        $text .= "🏠 Hall: " . strip_tags($booking['hall_name']) . "\n";
    }
    $text .= "👥 Guests: " . intval($booking['number_of_guests']) . "\n";

    if (!empty($booking['menus'])) {
        $text .= "\n🍽️ *Menu(s)*\n";
        foreach ($booking['menus'] as $menu) {
            $menu_name = str_replace(['*', '_'], ['\*', '\_'], strip_tags($menu['menu_name']));
            $text .= "• *" . $menu_name . "*\n";
            if (!empty($menu['items'])) {
                $by_category = [];
                foreach ($menu['items'] as $item) {
                    $cat = !empty($item['category']) ? strip_tags($item['category']) : '';
                    $by_category[$cat][] = str_replace(['*', '_'], ['\*', '\_'], strip_tags($item['item_name']));
                }
                foreach ($by_category as $category => $items) {
                    if (!empty($category)) {
                        $cat_escaped = str_replace(['*', '_'], ['\*', '\_'], $category);
                        $text .= "   _" . $cat_escaped . ":_ " . implode(', ', $items) . "\n";
                    } else {
                        foreach ($items as $item_name) {
                            $text .= "   - " . $item_name . "\n";
                        }
                    }
                }
            }
        }
    }

    if (!empty($booking['special_requests'])) {
        $text .= "\n📝 *Special Requests*\n" . strip_tags($booking['special_requests']) . "\n";
    }

    $text .= "\nFor coordination, please contact us:\n";
    $text .= "*" . strip_tags($company_name) . "*\n";
    if (!empty($contact_phone)) {
        $text .= "📞 " . $contact_phone;
    }

    return 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode($text);
}

/**
 * Send an email notification to a vendor when they are assigned to a booking.
 *
 * @param string $vendor_name
 * @param string $vendor_email
 * @param array  $booking  Booking row from getBookingDetails()
 * @return bool Whether the email was sent successfully
 */
function sendVendorAssignmentEmail($vendor_name, $vendor_email, $booking) {
    $site_name    = getSetting('site_name', 'Venue Booking System');
    $contact_phone = getSetting('contact_phone', '');

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 20px; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .detail-row { padding: 8px 0; border-bottom: 1px solid #eee; }
            .detail-label { font-weight: bold; color: #555; }
            .detail-value { color: #333; }
            .section-title { color: #4CAF50; font-size: 18px; margin: 20px 0 10px 0; border-bottom: 2px solid #4CAF50; padding-bottom: 5px; }
            .footer { text-align: center; padding: 20px; color: #777; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php echo htmlspecialchars($site_name); ?></h1>
                <h2>Vendor Assignment Notification</h2>
            </div>
            <div class="content">
                <p>Dear <?php echo htmlspecialchars($vendor_name); ?>,</p>
                <p>We would like to inform you that you have been officially assigned to the following event. Please find the details below:</p>
                <div class="booking-details">
                    <div class="section-title">Event Details</div>
                    <div class="detail-row">
                        <span class="detail-label">Booking Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Event Date:</span>
                        <span class="detail-value"><?php echo convertToNepaliDate($booking['event_date']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Event Type:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['event_type']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Shift:</span>
                        <span class="detail-value"><?php echo ucfirst($booking['shift']); ?><?php if (!empty($booking['start_time']) && !empty($booking['end_time'])): ?> (<?php echo formatBookingTime($booking['start_time']); ?> – <?php echo formatBookingTime($booking['end_time']); ?>)<?php endif; ?></span>
                    </div>
                </div>
                <div class="booking-details">
                    <div class="section-title">Venue Details</div>
                    <div class="detail-row">
                        <span class="detail-label">Venue Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['venue_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Venue Location:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['location']); ?></span>
                    </div>
                    <?php if (!empty($booking['venue_address'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Full Address:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['venue_address']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($booking['map_link'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Google Map:</span>
                        <span class="detail-value"><a href="<?php echo htmlspecialchars($booking['map_link']); ?>">View on Google Maps</a></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label">Hall:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($booking['hall_name']); ?></span>
                    </div>
                </div>
                <p>Kindly confirm your availability and ensure your presence at the venue as per the schedule. For any clarification, please contact us.</p>
                <p>Thank you for your cooperation.</p>
            </div>
            <div class="footer">
                <p><strong><?php echo htmlspecialchars($site_name); ?></strong></p>
                <?php if ($contact_phone): ?>
                    <p>Phone: <?php echo htmlspecialchars($contact_phone); ?></p>
                <?php endif; ?>
                <p style="margin-top: 15px; font-size: 12px; color: #999;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $subject = 'Vendor Assignment - ' . $booking['booking_number'] . ' (' . convertToNepaliDate($booking['event_date']) . ')';
    return sendEmail($vendor_email, $subject, $html, $vendor_name);
}

/**
 * Generate a JPEG preview thumbnail for a shared-folder image.
 *
 * The original file is never modified; the thumbnail is written to
 * $target_path (JPEG, max $max_size × $max_size, quality 85).
 *
 * Returns true on success, false if the thumbnail could not be created
 * (e.g. GD extension missing, animated GIF, unsupported format).
 * Callers should fall back to showing the original image when false is returned.
 *
 * @param  string $source_path  Absolute path to the original image file.
 * @param  string $target_path  Absolute path where the thumbnail should be saved.
 * @param  int    $max_size     Maximum width and height in pixels (default 800).
 * @return bool
 */
function generateSharedFolderThumbnail(string $source_path, string $target_path, int $max_size = 800): bool
{
    // Require GD
    if (!function_exists('imagecreatefromjpeg')) {
        return false;
    }

    $image_info = getimagesize($source_path);
    if (!$image_info) {
        error_log("generateSharedFolderThumbnail: getimagesize failed for {$source_path}");
        return false;
    }

    $mime   = $image_info['mime'];
    $orig_w = (int)$image_info[0];
    $orig_h = (int)$image_info[1];

    // Skip animated GIFs – GD only captures the first frame
    if ($mime === 'image/gif') {
        return false;
    }

    // Load source image
    switch ($mime) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($source_path);
            break;
        case 'image/webp':
            $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source_path) : false;
            break;
        default:
            return false;
    }

    if (!$src) {
        return false;
    }

    // Calculate thumbnail dimensions (maintain aspect ratio)
    if ($orig_w > $orig_h) {
        $new_w = min($orig_w, $max_size);
        $new_h = (int)round($orig_h * $new_w / $orig_w);
    } else {
        $new_h = min($orig_h, $max_size);
        $new_w = (int)round($orig_w * $new_h / $orig_h);
    }

    // Ensure at least 1×1
    $new_w = max(1, $new_w);
    $new_h = max(1, $new_h);

    $thumb = imagecreatetruecolor($new_w, $new_h);
    if (!$thumb) {
        imagedestroy($src);
        return false;
    }

    // Fill with white background (handles PNG transparency when converting to JPEG)
    $white = imagecolorallocate($thumb, 255, 255, 255);
    imagefill($thumb, 0, 0, $white);

    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
    imagedestroy($src);

    // Ensure the target directory exists
    $target_dir = dirname($target_path);
    if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
        imagedestroy($thumb);
        error_log("generateSharedFolderThumbnail: failed to create directory {$target_dir}");
        return false;
    }
    // Ensure the directory is world-executable/readable so the web server can
    // serve thumbnails regardless of which system user PHP and Apache run as.
    // (Some shared-hosting servers apply a restrictive umask that overrides
    // the mode passed to mkdir, leaving the directory as 0700 or 0750.)
    if (!chmod($target_dir, 0755)) {
        error_log("generateSharedFolderThumbnail: failed to chmod directory {$target_dir}");
    }

    $result = imagejpeg($thumb, $target_path, 85);
    imagedestroy($thumb);

    return $result;
}

/**
 * Compress an uploaded image file in-place.
 *
 * Resizes the image so that neither dimension exceeds $max_size pixels while
 * preserving the aspect ratio, then re-encodes it at the given quality level.
 * The original file is replaced with the compressed version.
 *
 * - JPEG / WebP: re-encoded at $quality (0-100).
 * - PNG: alpha channel is preserved; $quality is mapped to PNG compression level.
 * - GIF: skipped (animated GIFs would lose animation).
 *
 * If the image already fits within $max_size on both dimensions the function
 * returns true without modifying the file.
 *
 * @param string $image_path  Absolute path to the uploaded image file.
 * @param int    $max_size    Maximum pixel size for the longer edge (default 2048).
 * @param int    $quality     JPEG/WebP quality 0-100 (default 85).
 * @return bool               true on success or when no resize was needed; false on error.
 */
function compressUploadedImage(string $image_path, int $max_size = 2048, int $quality = 85): bool
{
    if (!function_exists('imagecreatefromjpeg')) {
        return false;
    }

    $image_info = @getimagesize($image_path);
    if (!$image_info) {
        return false;
    }

    $mime   = $image_info['mime'];
    $orig_w = (int)$image_info[0];
    $orig_h = (int)$image_info[1];

    // Skip animated GIFs – GD only captures the first frame; nothing to do
    if ($mime === 'image/gif') {
        return true;
    }

    // Determine output dimensions.
    // Always re-encode (even when no resize is needed) so that high-quality
    // originals stored at a very low compression ratio are brought down to the
    // target $quality.  This is important for raw camera photos that may fit
    // within the pixel limit but still occupy tens of megabytes.
    if ($orig_w <= $max_size && $orig_h <= $max_size) {
        $new_w = $orig_w;
        $new_h = $orig_h;
    } else {
        if ($orig_w > $orig_h) {
            $new_w = $max_size;
            $new_h = (int)round($orig_h * $max_size / $orig_w);
        } else {
            $new_h = $max_size;
            $new_w = (int)round($orig_w * $max_size / $orig_h);
        }
        $new_w = max(1, $new_w);
        $new_h = max(1, $new_h);
    }

    // Load source image
    switch ($mime) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($image_path);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($image_path);
            break;
        case 'image/webp':
            $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($image_path) : false;
            break;
        default:
            return false;
    }

    if (!$src) {
        return false;
    }

    $dst = imagecreatetruecolor($new_w, $new_h);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    if ($mime === 'image/png') {
        // Preserve transparency for PNG
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $transparent);
    } else {
        // White background for JPEG/WebP
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
    imagedestroy($src);

    // Write to a temporary file first, then atomically replace the original
    $tmp_path = $image_path . '.tmp';

    switch ($mime) {
        case 'image/jpeg':
            $ok = @imagejpeg($dst, $tmp_path, $quality);
            break;
        case 'image/png':
            // Map quality (0-100) to PNG compression level (0-9).
            // Higher quality → lower compression level → better quality, larger file.
            // e.g. quality=85 → level=2 (light compression, retains most detail)
            $png_level = max(0, min(9, (int)round((100 - $quality) / 10)));
            $ok = @imagepng($dst, $tmp_path, $png_level);
            break;
        case 'image/webp':
            $ok = function_exists('imagewebp') ? @imagewebp($dst, $tmp_path, $quality) : false;
            break;
        default:
            imagedestroy($dst);
            return false;
    }

    imagedestroy($dst);

    if ($ok && file_exists($tmp_path) && filesize($tmp_path) > 0) {
        // rename() is atomic on the same filesystem; fall back to copy+unlink
        if (@rename($tmp_path, $image_path)) {
            return true;
        }
        if (@copy($tmp_path, $image_path)) {
            @unlink($tmp_path);
            return true;
        }
        @unlink($tmp_path);
    } elseif (file_exists($tmp_path)) {
        @unlink($tmp_path);
    }

    return false;
}
