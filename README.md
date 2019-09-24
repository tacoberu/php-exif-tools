php-exif-tools
==============

php-exif-tools is a simple library for manipulate (read and write) with the EXIF meta-data of an image.


### Example

Read meta-data:

``` php
use Taco\Tools\Exif;
use SplFileInfo;

(new Exif\ExifReader)->read(new SplFileInfo('file.jpeg')) == (object)[
	'mime' => 'image/jpeg',
	'title' => 'Salut',
	'datetime' => new DateTime('2019-02-23 21:20:00'),
	'orientation' => 3,
];
```

Write meta-data:

``` php
use Taco\Tools\Exif;
use SplFileInfo;

(new Exif\ExifWrite(new SplFileInfo('file.jpeg'))->write([
	ExifWriter::SECTION_TITLE => 'Salut',
]));
```
