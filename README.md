# Panorama
Process panorama images via PHP + ImageMagick

Current features: `crop`

## Usage
```php
$attributes = [
  'width' => 400,
  'height' => 300,
  'yaw' => 0,
  'pitch' => 0,
  'roll' => 0,
  'fov' => 90,
  ...
];

$panorama = new Panorama($panoramaImageFilePath);

$savefile = true;
$panoramaImageOutputFilePath = '(...).jpg';
$panorama->crop($attributes, $saveFile, $panoramaImageOutputFilePath);
```

## License
[MIT](https://choosealicense.com/licenses/mit/)
