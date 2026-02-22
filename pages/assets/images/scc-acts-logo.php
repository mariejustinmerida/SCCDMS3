<?php
// Set the content type to PNG image
header('Content-Type: image/png');

// Create a placeholder SCC ACTs logo
$width = 300;
$height = 100;
$acts_logo = imagecreatetruecolor($width, $height);

// Set colors
$green = imagecolorallocate($acts_logo, 0, 100, 0);
$red = imagecolorallocate($acts_logo, 255, 0, 0);
$gold = imagecolorallocate($acts_logo, 255, 215, 0);
$white = imagecolorallocate($acts_logo, 255, 255, 255);

// Fill background
imagefilledrectangle($acts_logo, 0, 0, $width, $height, $white);

// Add colored rectangles
imagefilledrectangle($acts_logo, 10, 20, 80, 80, $green);
imagefilledrectangle($acts_logo, 90, 20, 160, 80, $red);
imagefilledrectangle($acts_logo, 170, 20, 240, 80, $gold);

// Add SCC ACTs text
$font = 5; // Built-in font
$text = "SCC ACTs";
$text_width = imagefontwidth($font) * strlen($text);
$text_height = imagefontheight($font);
$x = ($width - $text_width) / 2;
$y = 40;
imagestring($acts_logo, $font, $x, $y, $text, $green);

// Output the image
imagepng($acts_logo);
imagedestroy($acts_logo);
?> 