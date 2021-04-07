<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.misc.torrent.php                                     //
// module for analyzing .torrent files                         //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_torrent extends getid3_handler
{
	/**
	 * Assume all .torrent files are less than 1MB and just read entire thing into memory for easy processing.
	 * Override this value if you need to process files larger than 1MB
	 *
	 * @var int
	 */
	public $max_torrent_filesize = 1048576;

	/**
	 * calculated InfoHash (SHA1 of the entire "info" Dictionary)
	 *
	 * @var string
	 */
	private $infohash = '';

	const PIECE_HASHLENGTH = 20; // number of bytes the SHA1 hash is for each piece

	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;
		$filesize = $info['avdataend'] - $info['avdataoffset'];
		if ($filesize > $this->max_torrent_filesize) {  //
			$this->error('File larger ('.number_format($filesize).' bytes) than $max_torrent_filesize ('.number_format($this->max_torrent_filesize).' bytes), increase getid3_torrent->max_torrent_filesize if needed');
			return false;
		}
		$this->fseek($info['avdataoffset']);
		$TORRENT = $this->fread($filesize);
		$offset = 0;
		if (!preg_match('#^(d8\\:announce|d7\\:comment)#', $TORRENT)) {
			$this->error('Expecting "d8:announce" or "d7:comment" at '.$info['avdataoffset'].', found "'.substr($TORRENT, $offset, 12).'" instead.');
			return false;
		}
		$info['fileformat'] = 'torrent';

		$info['torrent'] = $this->NextEntity($TORRENT, $offset);
		if ($this->infohash) {
			$info['torrent']['infohash'] = $this->infohash;
		}

		if (empty($info['torrent']['info']['length']) && !empty($info['torrent']['info']['files'][0]['length'])) {
			$info['torrent']['info']['length'] = 0;
			foreach ($info['torrent']['info']['files'] as $key => $filedetails) {
				$info['torrent']['info']['length'] += $filedetails['length'];
			}
		}
		if (!empty($info['torrent']['info']['length']) && !empty($info['torrent']['info']['piece length']) && !empty($info['torrent']['info']['pieces'])) {
			$num_pieces_size = ceil($info['torrent']['info']['length'] / $info['torrent']['info']['piece length']);
			$num_pieces_hash = strlen($info['torrent']['info']['pieces']) / getid3_torrent::PIECE_HASHLENGTH; // should be concatenated 20-byte SHA1 hashes
			if ($num_pieces_hash == $num_pieces_size) {
				$info['torrent']['info']['piece_hash'] = array();
				for ($i = 0; $i < $num_pieces_size; $i++) {
					$info['torrent']['info']['piece_hash'][$i] = '';
					for ($j = 0; $j < getid3_torrent::PIECE_HASHLENGTH; $j++) {
						$info['torrent']['info']['piece_hash'][$i] .= sprintf('%02x', ord($info['torrent']['info']['pieces'][(($i * getid3_torrent::PIECE_HASHLENGTH) + $j)]));
					}
				}
				unset($info['torrent']['info']['pieces']);
			} else {
				$this->warning('found '.$num_pieces_size.' pieces based on file/chunk size; found '.$num_pieces_hash.' pieces in hash table');
			}
		}
		if (!empty($info['torrent']['info']['name']) && !empty($info['torrent']['info']['length']) && !isset($info['torrent']['info']['files'])) {
			// single-file torrent
			$info['torrent']['files'] = array($info['torrent']['info']['name'] => $info['torrent']['info']['length']);
		} elseif (!empty($info['torrent']['info']['files'])) {
			// multi-file torrent
			$info['torrent']['files'] = array();
			foreach ($info['torrent']['info']['files'] as $key => $filedetails) {
				$info['torrent']['files'][implode('/', $filedetails['path'])] = $filedetails['length'];
			}
		} else {
			$this->warning('no files found');
		}

		return true;
	}

	/**
	 * @return string|array|int|bool
	 */
	public function NextEntity(&$TORRENT, &$offset) {
		// https://fileformats.fandom.com/wiki/Torrent_file
		// https://en.wikipedia.org/wiki/Torrent_file
		// https://en.wikipedia.org/wiki/Bencode

		if ($offset >= strlen($TORRENT)) {
			$this->error('cannot read beyond end of file '.$offset);
			return false;
		}
		$type = $TORRENT[$offset++];
		if ($type == 'i') {

			// Integers are stored as i<integer>e:
			//   i90e
			$value = $this->ReadSequentialDigits($TORRENT, $offset, true);
			if ($TORRENT[$offset++] == 'e') {
//echo '<li>int: '.$value.'</li>';
				return (int) $value;
			}
			$this->error('unexpected('.__LINE__.') input "'.$value.'" at offset '.($offset - 1));
			return false;

		} elseif ($type == 'd') {

			// Dictionaries are stored as d[key1][value1][key2][value2][...]e. Keys and values appear alternately.
			// Keys must be strings and must be ordered alphabetically.
			// For example, {apple-red, lemon-yellow, violet-blue, banana-yellow} is stored as:
			//   d5:apple3:red6:banana6:yellow5:lemon6:yellow6:violet4:bluee
			$values = array();
//echo 'DICTIONARY @ '.$offset.'<ul>';
			$info_dictionary_start = null; // dummy declaration to prevent "Variable might not be defined" warnings
			while (true) {
				if ($TORRENT[$offset] === 'e') {
					break;
				}
				$thisentry = array();
				$key = $this->NextEntity($TORRENT, $offset);
				if ($key == 'info') {
					$info_dictionary_start = $offset;
				}
				if ($key === false) {
					$this->error('unexpected('.__LINE__.') input at offset '.$offset);
					return false;
				}
				$value = $this->NextEntity($TORRENT, $offset);
				if ($key == 'info') {
					$info_dictionary_end = $offset;
					$this->infohash = sha1(substr($TORRENT, $info_dictionary_start, $info_dictionary_end - $info_dictionary_start));
				}
				if ($value === false) {
					$this->error('unexpected('.__LINE__.') input at offset '.$offset);
					return false;
				}
				$values[$key] = $value;
			}
			if ($TORRENT[$offset++] == 'e') {
//echo '</ul>';
				return $values;
			}
			$this->error('unexpected('.__LINE__.') input "'.$TORRENT[($offset - 1)].'" at offset '.($offset - 1));
			return false;

		} elseif ($type == 'l') {

//echo 'LIST @ '.$offset.'<ul>';
			// Lists are stored as l[value 1][value2][value3][...]e. For example, {spam, eggs, cheeseburger} is stored as:
			//	l4:spam4:eggs12:cheeseburgere
			$values = array();
			while (true) {
				if ($TORRENT[$offset] === 'e') {
					break;
				}
				$NextEntity = $this->NextEntity($TORRENT, $offset);
				if ($NextEntity === false) {
					$this->error('unexpected('.__LINE__.') input at offset '.($offset - 1));
					return false;
				}
				$values[] = $NextEntity;
			}
			if ($TORRENT[$offset++] == 'e') {
//echo '</ul>';
				return $values;
			}
			$this->error('unexpected('.__LINE__.') input "'.$TORRENT[($offset - 1)].'" at offset '.($offset - 1));
			return false;

		} elseif (ctype_digit($type)) {

			// Strings are stored as <length of string>:<string>:
			//   4:wiki
			$length = $type;
			while (true) {
				$char = $TORRENT[$offset++];
				if ($char == ':') {
					break;
				} elseif (!ctype_digit($char)) {
					$this->error('unexpected('.__LINE__.') input "'.$char.'" at offset '.($offset - 1));
					return false;
				}
				$length .= $char;
			}
			if (($offset + $length) > strlen($TORRENT)) {
				$this->error('string at offset '.$offset.' claims to be '.$length.' bytes long but only '.(strlen($TORRENT) - $offset).' bytes of data left in file');
				return false;
			}
			$string = substr($TORRENT, $offset, $length);
			$offset += $length;
//echo '<li>string: '.$string.'</li>';
			return (string) $string;

		} else {

			$this->error('unexpected('.__LINE__.') input "'.$type.'" at offset '.($offset - 1));
			return false;

		}
	}

	/**
	 * @return string
	 */
	public function ReadSequentialDigits(&$TORRENT, &$offset, $allow_negative=false) {
		$start_offset = $offset;
		$value = '';
		while (true) {
			$char = $TORRENT[$offset++];
			if (!ctype_digit($char)) {
				if ($allow_negative && ($char == '-') && (strlen($value) == 0)) {
					// allow negative-sign if first character and $allow_negative enabled
				} else {
					$offset--;
					break;
				}
			}
			$value .= $char;
		}
		if (($value[0] === '0') && ($value !== '0')) {
			$this->warning('illegal zero-padded number "'.$value.'" at offset '.$start_offset);
		}
		return $value;
	}

}
