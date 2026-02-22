<?php
// Set the content type to PNG image
header('Content-Type: image/png');

// Create a watermark image
$width = 400;
$height = 400;
$watermark = imagecreatetruecolor($width, $height);

// Set transparent background
$transparent = imagecolorallocatealpha($watermark, 0, 0, 0, 127);
imagefill($watermark, 0, 0, $transparent);

// Set colors with transparency
$green = imagecolorallocatealpha($watermark, 0, 100, 0, 115); // Very transparent green

// Add SCC text as watermark
$font = 5; // Built-in font
$text = "SCC";
$text_width = imagefontwidth($font) * strlen($text);
$text_height = imagefontheight($font);
$x = ($width - $text_width) / 2;
$y = ($height - $text_height) / 2;
imagestring($watermark, $font, $x, $y, $text, $green);

// Save the watermark with transparency
imagesavealpha($watermark, true);
imagepng($watermark);
imagedestroy($watermark);
?> 