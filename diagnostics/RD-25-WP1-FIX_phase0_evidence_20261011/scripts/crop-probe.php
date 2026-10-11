<?php
// Phase 0: call only the fresh export's existing crop API. No library edits.
require __DIR__ . '/source/system/library/image.php';
$fixture = __DIR__ . '/crop-alpha-fixture.png';
$gd = imagecreatetruecolor(8, 8);
imagealphablending($gd, false);
imagesavealpha($gd, true);
imagefill($gd, 0, 0, imagecolorallocatealpha($gd, 255, 255, 255, 127));
imagesetpixel($gd, 3, 3, imagecolorallocatealpha($gd, 255, 0, 0, 63));
imagepng($gd, $fixture);
imagedestroy($gd);
$image = new \Opencart\System\Library\Image($fixture);
$before = imagecolorsforindex($image->getImage(), imagecolorat($image->getImage(), 3, 3));
$image->crop(2, 2, 6, 6);
$after = imagecolorsforindex($image->getImage(), imagecolorat($image->getImage(), 1, 1));
$bg = imagecolorsforindex($image->getImage(), imagecolorat($image->getImage(), 0, 0));
echo json_encode(['runtime' => PHP_VERSION, 'signature' => 'crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void', 'bottom_endpoints' => 'exclusive', 'output_dimensions' => [$image->getWidth(), $image->getHeight()], 'source_semtransparent_pixel' => $before, 'cropped_pixel' => $after, 'cropped_transparent_background' => $bg, 'preserves_alpha' => $after['alpha'] === $before['alpha'] && $bg['alpha'] === 127], JSON_PRETTY_PRINT) . "\n";
