<?php
/**
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Tools\Exif;

use SplFileInfo;
use RuntimeException;
use LogicException;


class ExifWriter
{

	const SECTION_TITLE = 'title';
	const SECTION_DESCRIPTION = 'description';
	const SECTION_KEYWORDS = 'keywords';
	const SECTION_DATETIME = 'datetime';
	const SECTION_OWNER = 'datetime';


    /**
     * Contains the mapping of names to IPTC field numbers
     *
     * @var array
     */
	private static $iptcMapping = array(
		self::SECTION_TITLE => ['2#120', 2000],
		self::SECTION_DESCRIPTION => ['2#230', 1024],
		self::SECTION_KEYWORDS => ['2#025', 64],
		self::SECTION_OWNER => ['2#188', 128],
	);

	/**
	 * @var SplFileInfo
	 */
	private $file;

	function __construct(SplFileInfo $file)
	{
		if ( ! file_exists($file)) {
			throw new RuntimeException("File `{$file}' is not found.");
		}
		$this->file = $file;
	}



	function write(array $values)
	{
		$iptc = [];
		foreach ($values as $key => $val) {
			switch ($key) {
				case self::SECTION_TITLE:
					list($code, $limit) = self::iptcMapping[self::SECTION_TITLE];
					$iptc[$code] = substr($val, 0, $limit);
					break;
				case self::SECTION_KEYWORDS:
					list($code, $limit) = self::iptcMapping[self::SECTION_KEYWORDS];
					$iptc[$code] = substr($val, 0, $limit);
					break;
				case self::SECTION_DESCRIPTION:
					list($code, $limit) = self::iptcMapping[self::SECTION_DESCRIPTION];
					$iptc[$code] = substr($val, 0, $limit);
					break;
				case self::SECTION_OWNER:
					list($code, $limit) = self::iptcMapping[self::SECTION_OWNER];
					$iptc[$code] = substr($val, 0, $limit);
					break;
				default:
					throw new LogicException("Section name must be from SECTION_* enum. Give `$key'.");
			}
		}

		$content = $this->writeIPTC($iptc);

		// Write the new image data out to the file.
		$fp = fopen($this->file, "wb");
		fwrite($fp, $content);
		fclose($fp);
	}



	private function writeIPTC(array $opts)
	{
		// Convert the IPTC tags into binary code
		$data = '';
		foreach ($opts as $key => $val) {
			$key = substr($key, 2);
			$data .= self::iptcMakeTag(2, $key, $val);
		}

		// Embed the IPTC data
		$content = iptcembed($data, $this->file);

		return $content;
	}



	/**
	 * iptc_make_tag() function by Thies C. Arntzen
	 */
	private static function iptcMakeTag($rec, $data, $value)
	{
		$length = strlen($value);
		$retval = chr(0x1C) . chr($rec) . chr($data);

		if($length < 0x8000) {
			$retval .= chr($length >> 8) . chr($length & 0xFF);
		}
		else {
			$retval .= chr(0x80) .
					   chr(0x04) .
					   chr(($length >> 24) & 0xFF) .
					   chr(($length >> 16) & 0xFF) .
					   chr(($length >> 8) & 0xFF) .
					   chr($length & 0xFF);
		}

		return $retval . $value;
	}

}
