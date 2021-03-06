<?php
/**
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Tools\Exif;

use SplFileInfo;
use RuntimeException;
use LogicException;
use DateTime;


/**
 * @author Martin Takáč <martin@takac.name>
 */
class ExifReader
{

    /**
     * Contains the mapping of names to IPTC field numbers
     *
     * @var array
     */
	private static $iptcMapping = array(
		'name'       => '2#005', // string:..64 / object name
		'editstatus' => '2#007', // string:..64 / edit status
		'urgency'    => '2#010', // int:0..9 1 nejvíc, 9 nejméně
		'category'   => '2#015', // string:..3
		'keywords'   => '2#025', // string:..64
		'byline'     => '2#080', // string:..32
		'jobtitle'   => '2#085', // string:..32
		'city'       => '2#090', // string:..32
		'headline'   => '2#105', // string:..256
		'credit'     => '2#110', // string:..32
		'source'     => '2#115', // string:..32
		'copyright'  => '2#116',
		'contact'    => '2#118', // string:..128
		'caption'    => '2#120', // string:..2000
		'writeeditor'=> '2#122', // string:..32
		'ownerid'    => '2#188', // string:..128
		'notes'      => '2#230', // string:..1024
	);



	function read(SplFileInfo $file)
	{
		if ( ! file_exists($file)) {
			throw new RuntimeException("File `{$file}' is not found.");
		}

		$exif = @exif_read_data($file, 'IFD0,THUMBNAIL', true);
		if ($exif === false) {
			$exif = [
				'FILE' => [
					'MimeType' => finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file),
				],
			];
		}
		getimagesize($file, $info);
		if (isset($info['APP13'])) {
			$exif['IPTC'] = iptcparse($info['APP13']);
		}

		$data = [
			'mime' => self::parseMime($exif),
			'title' => self::parseTitle($exif),
			'description' => self::parseDescription($exif),
			'keywords' => self::parseKeywords($exif),
			'author' => self::parseAuthor($exif),
			'owner' => self::parseOwner($exif),
			'datetime' => self::parseDateTime($exif),
			'orientation' => self::parseOrientation($exif),
		];

		return (object) array_filter($data);
	}



	private static function parseMime(array $exif)
	{
		if (isset($exif['FILE']['MimeType'])) {
			return trim($exif['FILE']['MimeType']);
		}
	}



	private static function parseTitle(array $exif)
	{
		if (isset($exif['COMPUTED']['UserComment'])) {
			return trim($exif['COMPUTED']['UserComment']);
		}
		elseif (isset($exif['IFD0']['DocumentName'])) {
			return trim($exif['IFD0']['DocumentName']);
		}
		// caption
		elseif (isset($exif['IPTC'][self::$iptcMapping['caption']][0])) {
			return trim($exif['IPTC'][self::$iptcMapping['caption']][0]);
		}
		// headline
		elseif (isset($exif['IPTC'][self::$iptcMapping['headline']][0])) {
			return trim($exif['IPTC'][self::$iptcMapping['headline']][0]);
		}
		// graphic name
		elseif (isset($exif['IPTC'][self::$iptcMapping['name']][0])) {
			return trim($exif['IPTC'][self::$iptcMapping['name']][0]);
		}
	}



	private static function parseDescription(array $exif)
	{
		if (isset($exif['IFD0']['ImageDescription'])) {
			return trim($exif['IFD0']['ImageDescription']);
		}
		elseif (isset($exif['IPTC'][self::$iptcMapping['notes']][0])) {
			return trim($exif['IPTC'][self::$iptcMapping['notes']][0]);
		}
	}



	private static function parseKeywords(array $exif)
	{
		if (isset($exif['IPTC'][self::$iptcMapping['keywords']][0])) {
			return trim($exif['IPTC'][self::$iptcMapping['keywords']][0]);
		}
	}



	private static function parseAuthor(array $exif)
	{
		if (isset($exif['IFD0']['Artist'])) {
			return trim($exif['IFD0']['Artist']);
		}
	}



	private static function parseOwner(array $exif)
	{
		if (isset($exif['IPTC'][self::$iptcMapping['ownerid']][0])) {
			return trim($exif['IPTC'][self::$iptcMapping['ownerid']][0]);
		}
	}



	/**
	 * the date and time the image was taken
	 * @return DateTime
	 */
	private static function parseDateTime(array $exif)
	{
		if (isset($exif['IFD0']['DateTime'])) {
			return DateTime::createFromFormat('Y:m:d H:i:s', $exif['IFD0']['DateTime']);
		}
		else if (isset($exif['EXIF']['DateTimeOriginal'])) {
			return DateTime::createFromFormat('Y:m:d H:i:s', $exif['EXIF']['DateTimeOriginal']);
		}
		else if (isset($exif['EXIF']['DateTimeDigitized'])) {
			return DateTime::createFromFormat('Y:m:d H:i:s', $exif['EXIF']['DateTimeDigitized']);
		}
		elseif (isset($exif['IPTC']["2#055"][0]) && isset($iptc["2#060"][0])) {
			return self::datetimeFromIPTC($exif['IPTC']["2#055"][0], $iptc["2#060"][0]);
		}
		else if (isset($exif['FILE']['FileDateTime'])) {
			//~ return $exif['FILE']['FileDateTime'];
		}
	}



	/**
	 * 1 = Horizontal (normal)
	 * 2 = Mirror horizontal
	 * 3 = Rotate 180
	 * 4 = Mirror vertical
	 * 5 = Mirror horizontal and rotate 270 clock wise
	 * 6 = Rotate 90 clock wise
	 * 7 = Mirror horizontal and rotate 90 clock wise
	 * 8 = Rotate 270 clock wise
	 *
	 * @return int
	 */
	private static function parseOrientation(array $exif)
	{
		if (isset($exif['IFD0']['Orientation'])) {
			return (int) $exif['IFD0']['Orientation'];
		}
		return 1;
	}



	private static function datetimeFromIPTC($date, $time)
	{
		if ( ! ( preg_match('/\d\d\d\d\d\d[-+]\d\d\d\d/', $time)
				&& preg_match('/\d\d\d\d\d\d\d\d/', $date)
				&& substr($date, 0, 8) !== '00000000' ) ) {
			return false;
		}

		$timestamp = mktime(
				substr( $time, 0, 2 ),
				substr( $time, 2, 2 ),
				substr( $time, 4, 2 ),
				substr( $date, 4, 2 ),
				substr( $date, 6, 2 ),
				substr( $date, 0, 4 ));

		$diff = ( intval( substr( $time, 7, 2 ) ) *60*60 ) + ( intval( substr( $time, 9, 2 ) ) * 60 );
		if ( substr( $time, 6, 1 ) === '-' ) {
			$diff = - $diff;
		}

		return new DateTime($timestamp + $diff);
	}

}
