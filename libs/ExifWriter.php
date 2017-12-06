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
	const SECTION_DATETIME = 'datetime';


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
		foreach ($values as $key => $val) {
			switch ($key) {
				case self::SECTION_TITLE:
					$opts['2#120'] = $val; // Label
					//~ $opts['2#105'] = $val; // Headline
					break;
					//~ $opts['2#116'] = $val; // Copy
				default:
					throw new LogicException("Section name must be from SECTION_* enum. Give `$key'.");
			}
		}

		// Convert the IPTC tags into binary code
		$data = '';
		foreach ($opts as $key => $val) {
			$key = substr($key, 2);
			$data .= self::iptcMakeTag(2, $key, $val);
		}

		// Embed the IPTC data
		$content = iptcembed($data, $this->file);

		// Write the new image data out to the file.
		$fp = fopen($this->file, "wb");
		fwrite($fp, $content);
		fclose($fp);
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
