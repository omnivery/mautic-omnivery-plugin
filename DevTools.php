<?php

namespace MauticPlugin\OmniveryMailerBundle;

class DevTools
{
    public static function debugLog($content)
    {
        $fileName = '/var/www/html/var/logs/mzdebug.txt'; // You can change the file name here
        $content  = '['.date('Y-m-d H:i:s').']: '.$content."\n"; // Add date and time to the content

        // Open the file for appending
        $file = fopen($fileName, 'a');

        // Write the content to the file
        fwrite($file, $content);

        // Close the file
        fclose($file);
    }
}
