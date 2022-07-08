<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or https://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.tak.php                                        //
// module for analyzing Tom's lossless Audio Kompressor        //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_tak extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat']            = 'tak';
		$info['audio']['dataformat']   = 'tak';
		$info['audio']['bitrate_mode'] = 'vbr';
		$info['audio']['lossless']     = true;

		$info['tak_audio']['raw'] = array();
		$thisfile_takaudio                = &$info['tak_audio'];
		$thisfile_takaudio_raw            = &$thisfile_takaudio['raw'];

		$this->fseek($info['avdataoffset']);
		$TAKMetaData = $this->fread(4);

		$thisfile_takaudio_raw['magic'] = $TAKMetaData;
		$magic = 'tBaK';
		if ($thisfile_takaudio_raw['magic'] != $magic) {
			$this->error('Expecting "'.getid3_lib::PrintHexBytes($magic).'" at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes($thisfile_takaudio_raw['magic']).'"');
			unset($info['fileformat']);
			return false;
		}
		$offset = 4; //skip magic
		$this->fseek($offset);
		$TAKMetaData = $this->fread(4); //read Metadata Block Header
		$objtype = getid3_lib::BigEndian2Int(substr($TAKMetaData, 0, 1)); //Metadata Block Object Type
		$objlength = getid3_lib::LittleEndian2Int(substr($TAKMetaData, 1, 3)); //Metadata Block Object Lenght excluding header
		if ($objtype == 1) { //The First Metadata Block Object must be of Type 1 (STREAMINFO)
			$offset += 4; //skip to Metadata Block contents
			$this->fseek($offset);
		        $TAKMetaData = $this->fread($objlength); // Get the raw Metadata Block Data
			$thisfile_takaudio_raw['STREAMINFO'] = getid3_lib::LittleEndian2Bin(substr($TAKMetaData, 0, $objlength - 3));
			$offset += $objlength; // Move to the next Metadata Block Object
			$thisfile_takaudio['channels'] = getid3_lib::Bin2Dec(substr($thisfile_takaudio_raw['STREAMINFO'], 1, 4)) + 1;
			$thisfile_takaudio['bits_per_sample'] = getid3_lib::Bin2Dec(substr($thisfile_takaudio_raw['STREAMINFO'], 5, 5)) + 8;
			$thisfile_takaudio['sample_rate'] = getid3_lib::Bin2Dec(substr($thisfile_takaudio_raw['STREAMINFO'], 10, 18)) + 6000;
			$thisfile_takaudio['samples'] = getid3_lib::Bin2Dec(substr($thisfile_takaudio_raw['STREAMINFO'], 31, 35));
			$thisfile_takaudio['framesize'] = self::TAKFramesizeLookup(getid3_lib::Bin2Dec(substr($thisfile_takaudio_raw['STREAMINFO'], 66, 4)));
			$thisfile_takaudio['codectype'] = self::TAKCodecTypeLookup(getid3_lib::Bin2Dec(substr($thisfile_takaudio_raw['STREAMINFO'], 74, 6)));
		} else {
			$this->error('Expecting Type 1 (STREAMINFO) Metadata Object header, but found Type "'.$objtype.'" Object instead');
			unset($info['fileformat']);
			return false;
		}
		$this->fseek($offset);
		$TAKMetaData = $this->fread(4);
		$objtype = getid3_lib::BigEndian2Int(substr($TAKMetaData, 0, 1));
		$objlength = getid3_lib::LittleEndian2Int(substr($TAKMetaData, 1, 3));
		while ($objtype != 0) {
			switch ($objtype) {
			case 4 :
				// ENCODERINFO Metadata Block
				$offset += 4;
				$this->fseek($offset);
		                $TAKMetaData = $this->fread($objlength);
				$ver = getid3_lib::LittleEndian2Int(substr($TAKMetaData, 0, 3));
				$major = ($ver & 0xff0000) >> 16;
				$minor = ($ver & 0x00ff00) >> 8;
				$revision= $ver & 0x0000ff;
				$thisfile_takaudio['version'] = 'TAK V '.$major.'.'.$minor.'.'.$revision;
				$thisfile_takaudio['profile'] = self::TAKProfileLookup(getid3_lib::BigEndian2Int(substr($TAKMetaData, 3, 1)));
				$offset += $objlength;
				break;
			case 6 :
				// MD5 Checksum Metadata Block
				$offset += 4;
				$this->fseek($offset);
		                $TAKMetaData = $this->fread($objlength);
				$thisfile_takaudio_raw['MD5Data'] = substr($TAKMetaData, 0, 16);
				$offset += $objlength;
				break;
			case 7 :
				// LASTFRAME Metadata Block
				$offset += 4;
				$this->fseek($offset);
		                $TAKMetaData = $this->fread($objlength);
				$thisfile_takaudio['lastframe_pos'] = getid3_lib::LittleEndian2Int(substr($TAKMetaData, 0, 5));
				$thisfile_takaudio['last_frame_size'] = getid3_lib::LittleEndian2Int(substr($TAKMetaData, 5, 3));
				$offset += $objlength;
				break;
			case 3 :
				// ORIGINALFILEDATA Metadata Block
				$offset += 4;
				$this->fseek($offset);
		                $TAKMetaData = $this->fread($objlength);
				$headersize = getid3_lib::LittleEndian2Int(substr($TAKMetaData, 0, 3));
				$footersize = getid3_lib::LittleEndian2Int(substr($TAKMetaData, 3, 3));
				if ($headersize) $thisfile_takaudio_raw['header_data'] = substr($TAKMetaData, 6, $headersize);
				if ($footersize) $thisfile_takaudio_raw['footer_data'] = substr($TAKMetaData, $headersize, $footersize);
				$offset += $objlength;
				break;
			default :
				// PADDING or SEEKTABLE Metadata Block. Just skip it
				$offset += 4;
				$this->fseek($offset);
		                $TAKMetaData = $this->fread($objlength);
				$offset += $objlength;
				break;
			}
			$this->fseek($offset);
			$TAKMetaData = $this->fread(4);
			$objtype = getid3_lib::BigEndian2Int(substr($TAKMetaData, 0, 1));
			$objlength = getid3_lib::LittleEndian2Int(substr($TAKMetaData, 1, 3));
		}
		// Finished all Metadata Blocks. So update $info['avdataoffset'] because next block is the first Audio data block
		$info['avdataoffset'] = $offset;

		$info['audio']['channels'] = $thisfile_takaudio['channels'];
		if ($thisfile_takaudio['sample_rate'] == 0) {
			$this->error('Corrupt TAK file: samplerate == zero');
			return false;
		}
		$info['audio']['sample_rate'] = $thisfile_takaudio['sample_rate'];
		$thisfile_takaudio['playtime'] = $thisfile_takaudio['samples'] / $thisfile_takaudio['sample_rate'];
		if ($thisfile_takaudio['playtime'] == 0) {
			$this->error('Corrupt TAK file: playtime == zero');
			return false;
		}
		$info['playtime_seconds'] = $thisfile_takaudio['playtime'];
		$thisfile_takaudio['compressed_size'] = $info['avdataend'] - $info['avdataoffset'];
		$thisfile_takaudio['uncompressed_size'] = $thisfile_takaudio['samples'] * $thisfile_takaudio['channels'] * ($thisfile_takaudio['bits_per_sample'] / 8);
		if ($thisfile_takaudio['uncompressed_size'] == 0) {
			$this->error('Corrupt TAK file: uncompressed_size == zero');
			return false;
		}
		$thisfile_takaudio['compression_ratio'] = $thisfile_takaudio['compressed_size'] / ($thisfile_takaudio['uncompressed_size'] + $offset);
		$thisfile_takaudio['bitrate'] = (($thisfile_takaudio['samples'] * $thisfile_takaudio['channels'] * $thisfile_takaudio['bits_per_sample']) / $thisfile_takaudio['playtime']) * $thisfile_takaudio['compression_ratio'];
		$info['audio']['bitrate'] = $thisfile_takaudio['bitrate'];

		if (empty($thisfile_takaudio_raw['MD5Data'])) {
			//$this->warning('MD5Data is not set');
		} elseif ($thisfile_takaudio_raw['MD5Data'] === str_repeat("\x00", 16)) {
			//$this->warning('MD5Data is null');
		} else {
			$info['md5_data_source'] = '';
			$md5 = $thisfile_takaudio_raw['MD5Data'];
			for ($i = 0; $i < strlen($md5); $i++) {
				$info['md5_data_source'] .= str_pad(dechex(ord($md5[$i])), 2, '00', STR_PAD_LEFT);
			}
			if (!preg_match('/^[0-9a-f]{32}$/', $info['md5_data_source'])) {
				unset($info['md5_data_source']);
			}
		}

		foreach (array('bits_per_sample', 'version', 'profile') as $key) {
			if (!empty($thisfile_takaudio[$key])) {
				$info['audio'][$key] = $thisfile_takaudio[$key];
			}
		}

		return true;
	}

	public function TAKFramesizeLookup($framesize) {
		static $TAKFramesizeLookup = array(
			0     => '94 ms',
			1     => '125 ms',
			2     => '188 ms',
			3     => '250 ms',
			4     => '4096 samples',
			5     => '8192 samples',
			6     => '16384 samples',
			7     => '512 samples',
			8     => '1024 samples',
			9     => '2048 samples'
		);
		return (isset($TAKFramesizeLookup[$framesize]) ? $TAKFramesizeLookup[$framesize] : 'invalid');
	}
	public function TAKCodecTypeLookup($code) {
		static $TAKCodecTypeLookup = array(
			0     => 'Integer 24 bit (TAK 1.0)',
			1     => 'Experimental!',
			2     => 'Integer 24 bit (TAK 2.0)',
			3     => 'LossyWav (TAK 2.1)',
			4     => 'Integer 24 bit MC (TAK 2.2)'
		);
		return (isset($TAKCodecTypeLookup[$code]) ? $TAKCodecTypeLookup[$code] : 'invalid');
	}
	public function TAKProfileLookup($code) {
		$out ='-p';
		$evaluation = ($code & 0xf0) >> 4;
		$compresion = $code & 0x0f;
		static $TAKEvaluationLookup = array(
			0     => '',
			1     => 'e',
			2     => 'm'
		);
		return (isset($TAKEvaluationLookup[$evaluation]) ? $out .= $compresion . $TAKEvaluationLookup[$evaluation] : 'invalid');
	}

}
