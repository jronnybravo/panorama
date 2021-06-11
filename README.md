# Panorama
Process panorama images via PHP.

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
$panorama->crop($attributes, $panoramaImageOutputFilePath);
```

## License
[MIT](https://choosealicense.com/licenses/mit/)
