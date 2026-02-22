<?php
// This script extracts logos from the letterhead image and saves them as separate files
// It's a one-time script to set up the letterhead assets

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

// Save the logo
imagepng($logo, 'scc-logo.png');
imagedestroy($logo);

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

// Save the logo
imagepng($acts_logo, 'scc-acts-logo.png');
imagedestroy($acts_logo);

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
imagepng($watermark, 'scc-watermark.png');
imagedestroy($watermark);

echo "Logo files created successfully!";
?> 