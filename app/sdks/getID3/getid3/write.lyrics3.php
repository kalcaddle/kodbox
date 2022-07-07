<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// write.lyrics3.php                                           //
// module for writing Lyrics3 tags                             //
// dependencies: module.tag.lyrics3.php                        //
//                                                            ///
/////////////////////////////////////////////////////////////////


class getid3_write_lyrics3
{
	/**
	 * @var string
	 */
	public $filename;

	/**
	 * @var array
	 */
	public $tag_data;
	//public $lyrics3_version = 2;       // 1 or 2

	/**
	 * Any non-critical errors will be stored here.
	 *
	 * @var array
	 */
	public $warnings        = array();

	/**
	 * Any critical errors will be stored here.
	 *
	 * @var array
	 */
	public $errors          = array();

	public function __construct() {
	}

	/**
	 * @return bool
	 */
	public function WriteLyrics3() {
		$this->errors[] = 'WriteLyrics3() not yet functional - cannot write Lyrics3';
		return false;
	}

	/**
	 * @return bool
	 */
	public function DeleteLyrics3() {
		// Initialize getID3 engine
		$getID3 = new getID3;
		$ThisFileInfo = $getID3->analyze($this->filename);
		if (isset($ThisFileInfo['lyrics3']['tag_offset_start']) && isset($ThisFileInfo['lyrics3']['tag_offset_end'])) {
			if (is_readable($this->filename) && getID3::is_writable($this->filename) && is_file($this->filename) && ($fp = fopen($this->filename, 'a+b'))) {

				flock($fp, LOCK_EX);
				$oldignoreuserabort = ignore_user_abort(true);

				fseek($fp, $ThisFileInfo['lyrics3']['tag_offset_end']);
				$DataAfterLyrics3 = '';
				if ($ThisFileInfo['filesize'] > $ThisFileInfo['lyrics3']['tag_offset_end']) {
					$DataAfterLyrics3 = fread($fp, $ThisFileInfo['filesize'] - $ThisFileInfo['lyrics3']['tag_offset_end']);
				}

				ftruncate($fp, $ThisFileInfo['lyrics3']['tag_offset_start']);

				if (!empty($DataAfterLyrics3)) {
					fseek($fp, $ThisFileInfo['lyrics3']['tag_offset_start']);
					fwrite($fp, $DataAfterLyrics3, strlen($DataAfterLyrics3));
				}

				flock($fp, LOCK_UN);
				fclose($fp);
				ignore_user_abort($oldignoreuserabort);

				return true;

			} else {
				$this->errors[] = 'Cannot fopen('.$this->filename.', "a+b")';
				return false;
			}
		}
		// no Lyrics3 present
		return true;
	}

}
