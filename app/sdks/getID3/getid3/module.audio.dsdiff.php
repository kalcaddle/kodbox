<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.dsdiff.php                                     //
// module for analyzing Direct Stream Digital Interchange      //
// File Format (DSDIFF) files                                  //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_dsdiff extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$DSDIFFheader = $this->fread(4);

		// https://dsd-guide.com/sites/default/files/white-papers/DSDIFF_1.5_Spec.pdf
		if (substr($DSDIFFheader, 0, 4) != 'FRM8') {
			$this->error('Expecting "FRM8" at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes(substr($DSDIFFheader, 0, 4)).'"');
			return false;
		}
		unset($DSDIFFheader);
		$this->fseek($info['avdataoffset']);

		$info['encoding']                 = 'ISO-8859-1'; // not certain, but assumed
		$info['fileformat']               = 'dsdiff';
		$info['mime_type']                = 'audio/dsd';
		$info['audio']['dataformat']      = 'dsdiff';
		$info['audio']['bitrate_mode']    = 'cbr';
		$info['audio']['bits_per_sample'] = 1;

		$info['dsdiff'] = array();
		while (!$this->feof() && ($ChunkHeader = $this->fread(12))) {
			if (strlen($ChunkHeader) < 12) {
				$this->error('Expecting chunk header at offset '.(isset($thisChunk['offset']) ? $thisChunk['offset'] : 'N/A').', found insufficient data in file, aborting parsing');
				break;
			}
			$thisChunk = array();
			$thisChunk['offset'] = $this->ftell() - 12;
			$thisChunk['name'] = substr($ChunkHeader, 0, 4);
			if (!preg_match('#^[\\x21-\\x7E]+ *$#', $thisChunk['name'])) {
				// "a concatenation of four printable ASCII characters in the range ' ' (space, 0x20) through '~'(0x7E). Space (0x20) cannot precede printing characters; trailing spaces are allowed."
				$this->error('Invalid chunk name "'.$thisChunk['name'].'" ('.getid3_lib::PrintHexBytes($thisChunk['name']).') at offset '.$thisChunk['offset'].', aborting parsing');
			}
			$thisChunk['size'] = getid3_lib::BigEndian2Int(substr($ChunkHeader, 4, 8));
			$datasize = $thisChunk['size'] + ($thisChunk['size'] % 2); // "If the data is an odd number of bytes in length, a pad byte must be added at the end. The pad byte is not included in ckDataSize."

			switch ($thisChunk['name']) {
				case 'FRM8':
					$thisChunk['form_type'] = $this->fread(4);
					if ($thisChunk['form_type'] != 'DSD ') {
						$this->error('Expecting "DSD " at offset '.($this->ftell() - 4).', found "'.getid3_lib::PrintHexBytes($thisChunk['form_type']).'", aborting parsing');
						break 2;
					}
					// do nothing further, prevent skipping subchunks
					break;
				case 'PROP': // PROPerty chunk
					$thisChunk['prop_type'] = $this->fread(4);
					if ($thisChunk['prop_type'] != 'SND ') {
						$this->error('Expecting "SND " at offset '.($this->ftell() - 4).', found "'.getid3_lib::PrintHexBytes($thisChunk['prop_type']).'", aborting parsing');
						break 2;
					}
					// do nothing further, prevent skipping subchunks
					break;
				case 'DIIN': // eDIted master INformation chunk
					// do nothing, just prevent skipping subchunks
					break;

				case 'FVER': // Format VERsion chunk
					if ($thisChunk['size'] == 4) {
						$FVER = $this->fread(4);
						$info['dsdiff']['format_version'] = ord($FVER[0]).'.'.ord($FVER[1]).'.'.ord($FVER[2]).'.'.ord($FVER[3]);
						unset($FVER);
					} else {
						$this->warning('Expecting "FVER" chunk to be 4 bytes, found '.$thisChunk['size'].' bytes, skipping chunk');
						$this->fseek($datasize, SEEK_CUR);
					}
					break;
				case 'FS  ': // sample rate chunk
					if ($thisChunk['size'] == 4) {
						$info['dsdiff']['sample_rate'] = getid3_lib::BigEndian2Int($this->fread(4));
						$info['audio']['sample_rate'] = $info['dsdiff']['sample_rate'];
					} else {
						$this->warning('Expecting "FVER" chunk to be 4 bytes, found '.$thisChunk['size'].' bytes, skipping chunk');
						$this->fseek($datasize, SEEK_CUR);
					}
					break;
				case 'CHNL': // CHaNneLs chunk
					$thisChunk['num_channels'] = getid3_lib::BigEndian2Int($this->fread(2));
					if ($thisChunk['num_channels'] == 0) {
						$this->warning('channel count should be greater than zero, skipping chunk');
						$this->fseek($datasize - 2, SEEK_CUR);
					}
					for ($i = 0; $i < $thisChunk['num_channels']; $i++) {
						$thisChunk['channels'][$i] = $this->fread(4);
					}
					$info['audio']['channels'] = $thisChunk['num_channels'];
					break;
				case 'CMPR': // CoMPRession type chunk
					$thisChunk['compression_type'] = $this->fread(4);
					$info['audio']['dataformat'] = trim($thisChunk['compression_type']);
					$humanReadableByteLength = getid3_lib::BigEndian2Int($this->fread(1));
					$thisChunk['compression_name'] = $this->fread($humanReadableByteLength);
					if (($humanReadableByteLength % 2) == 0) {
						// need to seek to multiple of 2 bytes, human-readable string length is only one byte long so if the string is an even number of bytes we need to seek past a padding byte after the string
						$this->fseek(1, SEEK_CUR);
					}
					unset($humanReadableByteLength);
					break;
				case 'ABSS': // ABSolute Start time chunk
					$ABSS = $this->fread(8);
					$info['dsdiff']['absolute_start_time']['hours']   = getid3_lib::BigEndian2Int(substr($ABSS, 0, 2));
					$info['dsdiff']['absolute_start_time']['minutes'] = getid3_lib::BigEndian2Int(substr($ABSS, 2, 1));
					$info['dsdiff']['absolute_start_time']['seconds'] = getid3_lib::BigEndian2Int(substr($ABSS, 3, 1));
					$info['dsdiff']['absolute_start_time']['samples'] = getid3_lib::BigEndian2Int(substr($ABSS, 4, 4));
					unset($ABSS);
					break;
				case 'LSCO': // LoudSpeaker COnfiguration chunk
					// 0 = 2-channel stereo set-up
					// 3 = 5-channel set-up according to ITU-R BS.775-1 [ITU]
					// 4 = 6-channel set-up, 5-channel set-up according to ITU-R BS.775-1 [ITU], plus additional Low Frequency Enhancement (LFE) loudspeaker. Also known as "5.1 configuration"
					// 65535 = Undefined channel set-up
					$thisChunk['loundspeaker_config_id'] = getid3_lib::BigEndian2Int($this->fread(2));
					break;
				case 'COMT': // COMmenTs chunk
					$thisChunk['num_comments'] = getid3_lib::BigEndian2Int($this->fread(2));
					for ($i = 0; $i < $thisChunk['num_comments']; $i++) {
						$thisComment = array();
						$COMT = $this->fread(14);
						$thisComment['creation_year']   = getid3_lib::BigEndian2Int(substr($COMT,  0, 2));
						$thisComment['creation_month']  = getid3_lib::BigEndian2Int(substr($COMT,  2, 1));
						$thisComment['creation_day']    = getid3_lib::BigEndian2Int(substr($COMT,  3, 1));
						$thisComment['creation_hour']   = getid3_lib::BigEndian2Int(substr($COMT,  4, 1));
						$thisComment['creation_minute'] = getid3_lib::BigEndian2Int(substr($COMT,  5, 1));
						$thisComment['comment_type_id'] = getid3_lib::BigEndian2Int(substr($COMT,  6, 2));
						$thisComment['comment_ref_id']  = getid3_lib::BigEndian2Int(substr($COMT,  8, 2));
						$thisComment['string_length']   = getid3_lib::BigEndian2Int(substr($COMT, 10, 4));
						$thisComment['comment_text'] = $this->fread($thisComment['string_length']);
						if ($thisComment['string_length'] % 2) {
							// commentText[] is the description of the Comment. This text must be padded with a byte at the end, if needed, to make it an even number of bytes long. This pad byte, if present, is not included in count.
							$this->fseek(1, SEEK_CUR);
						}
						$thisComment['comment_type']      = $this->DSDIFFcmtType($thisComment['comment_type_id']);
						$thisComment['comment_reference'] = $this->DSDIFFcmtRef($thisComment['comment_type_id'], $thisComment['comment_ref_id']);
						$thisComment['creation_unix'] = mktime($thisComment['creation_hour'], $thisComment['creation_minute'], 0, $thisComment['creation_month'], $thisComment['creation_day'], $thisComment['creation_year']);
						$thisChunk['comments'][$i] = $thisComment;

						$commentkey = ($thisComment['comment_reference'] ?: 'comment');
						$info['dsdiff']['comments'][$commentkey][] = $thisComment['comment_text'];
						unset($thisComment);
					}
					break;
				case 'MARK': // MARKer chunk
					$MARK = $this->fread(22);
					$thisChunk['marker_hours']   = getid3_lib::BigEndian2Int(substr($MARK,  0, 2));
					$thisChunk['marker_minutes'] = getid3_lib::BigEndian2Int(substr($MARK,  2, 1));
					$thisChunk['marker_seconds'] = getid3_lib::BigEndian2Int(substr($MARK,  3, 1));
					$thisChunk['marker_samples'] = getid3_lib::BigEndian2Int(substr($MARK,  4, 4));
					$thisChunk['marker_offset']  = getid3_lib::BigEndian2Int(substr($MARK,  8, 4));
					$thisChunk['marker_type_id'] = getid3_lib::BigEndian2Int(substr($MARK, 12, 2));
					$thisChunk['marker_channel'] = getid3_lib::BigEndian2Int(substr($MARK, 14, 2));
					$thisChunk['marker_flagraw'] = getid3_lib::BigEndian2Int(substr($MARK, 16, 2));
					$thisChunk['string_length']  = getid3_lib::BigEndian2Int(substr($MARK, 18, 4));
					$thisChunk['description'] = ($thisChunk['string_length'] ? $this->fread($thisChunk['string_length']) : '');
					if ($thisChunk['string_length'] % 2) {
						// markerText[] is the description of the marker. This text must be padded with a byte at the end, if needed, to make it an even number of bytes long. This pad byte, if present, is not included in count.
						$this->fseek(1, SEEK_CUR);
					}
					$thisChunk['marker_type'] = $this->DSDIFFmarkType($thisChunk['marker_type_id']);
					unset($MARK);
					break;
				case 'DIAR': // artist chunk
				case 'DITI': // title chunk
					$thisChunk['string_length']  = getid3_lib::BigEndian2Int($this->fread(4));
					$thisChunk['description'] = ($thisChunk['string_length'] ? $this->fread($thisChunk['string_length']) : '');
					if ($thisChunk['string_length'] % 2) {
						// This text must be padded with a byte at the end, if needed, to make it an even number of bytes long. This pad byte, if present, is not included in count.
						$this->fseek(1, SEEK_CUR);
					}

					if ($commentkey = (($thisChunk['name'] == 'DIAR') ? 'artist' : (($thisChunk['name'] == 'DITI') ? 'title' : ''))) {
						@$info['dsdiff']['comments'][$commentkey][] = $thisChunk['description'];
					}
					break;
				case 'EMID': // Edited Master ID chunk
					if ($thisChunk['size']) {
						$thisChunk['identifier'] = $this->fread($thisChunk['size']);
					}
					break;

				case 'ID3 ':
					$endOfID3v2 = $this->ftell() + $datasize; // we will need to reset the filepointer after parsing ID3v2

					getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.tag.id3v2.php', __FILE__, true);
					$getid3_temp = new getID3();
					$getid3_temp->openfile($this->getid3->filename, null, $this->getid3->fp);
					$getid3_id3v2 = new getid3_id3v2($getid3_temp);
					$getid3_id3v2->StartingOffset = $this->ftell();
					if ($thisChunk['valid'] = $getid3_id3v2->Analyze()) {
						$info['id3v2'] = $getid3_temp->info['id3v2'];
					}
					unset($getid3_temp, $getid3_id3v2);

					$this->fseek($endOfID3v2);
					break;

				case 'DSD ': // DSD sound data chunk
				case 'DST ': // DST sound data chunk
					// actual audio data, we're not interested, skip
					$this->fseek($datasize, SEEK_CUR);
					break;
				default:
					$this->warning('Unhandled chunk "'.$thisChunk['name'].'"');
					$this->fseek($datasize, SEEK_CUR);
					break;
			}

			@$info['dsdiff']['chunks'][] = $thisChunk;
			//break;
		}
		if (empty($info['audio']['bitrate']) && !empty($info['audio']['channels']) && !empty($info['audio']['sample_rate']) && !empty($info['audio']['bits_per_sample'])) {
			$info['audio']['bitrate'] = $info['audio']['bits_per_sample'] * $info['audio']['sample_rate'] * $info['audio']['channels'];
		}

		return true;
	}

	/**
	 * @param int $cmtType
	 *
	 * @return string
	 */
	public static function DSDIFFcmtType($cmtType) {
		static $DSDIFFcmtType = array(
			0 => 'General (album) Comment',
			1 => 'Channel Comment',
			2 => 'Sound Source',
			3 => 'File History',
		);
		return (isset($DSDIFFcmtType[$cmtType]) ? $DSDIFFcmtType[$cmtType] : 'reserved');
	}

	/**
	 * @param int $cmtType
	 * @param int $cmtRef
	 *
	 * @return string
	 */
	public static function DSDIFFcmtRef($cmtType, $cmtRef) {
		static $DSDIFFcmtRef = array(
			2 => array(  // Sound Source
				0 => 'DSD recording',
				1 => 'Analogue recording',
				2 => 'PCM recording',
			),
			3 => array( // File History
				0 => 'comment',   // General Remark
				1 => 'encodeby',  // Name of the operator
				2 => 'encoder',   // Name or type of the creating machine
				3 => 'timezone',  // Time zone information
				4 => 'revision',  // Revision of the file
			),
		);
		switch ($cmtType) {
			case 0:
				// If the comment type is General Comment the comment reference must be 0
				return '';
			case 1:
				// If the comment type is Channel Comment, the comment reference defines the channel number to which the comment belongs
				return ($cmtRef ? 'channel '.$cmtRef : 'all channels');
			case 2:
			case 3:
				return (isset($DSDIFFcmtRef[$cmtType][$cmtRef]) ? $DSDIFFcmtRef[$cmtType][$cmtRef] : 'reserved');
		}
		return 'unsupported $cmtType='.$cmtType;
	}

	/**
	 * @param int $markType
	 *
	 * @return string
	 */
	public static function DSDIFFmarkType($markType) {
		static $DSDIFFmarkType = array(
			0 => 'TrackStart',   // Entry point for a Track start
			1 => 'TrackStop',    // Entry point for ending a Track
			2 => 'ProgramStart', // Start point of 2-channel or multi-channel area
			3 => 'Obsolete',     //
			4 => 'Index',        // Entry point of an Index
		);
		return (isset($DSDIFFmarkType[$markType]) ? $DSDIFFmarkType[$markType] : 'reserved');
	}

}
