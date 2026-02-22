<?php
// Set the content type to PNG image
header('Content-Type: image/png');

// Create a placeholder SCC logo (green circle with SCC text)
$width = 200;
$height = 200;
$logo = imagecreatetruecolor($width, $height);

// Set colors
$green = imagecolorallocate($logo, 0, 100, 0);
$gold = imagecolorallocate($logo, 255, 215, 0);
$white = imagecolorallocate($logo, 255, 255, 255);

// Create a green circle
imagefilledrectangle($logo, 0, 0, $width, $height, $white);
imagefilledellipse($logo, $width/2, $height/2, $width-10, $height-10, $green);

// Add SCC text
$font = 5; // Built-in font
$text = "SCC";
$text_width = imagefontwidth($font) * strlen($text);
$text_height = imagefontheight($font);
$x = ($width - $text_width) / 2;
$y = ($height - $text_height) / 2;
imagestring($logo, $font, $x, $y, $text, $gold);

// Output the image
imagepng($logo);
imagedestroy($logo);
?> 