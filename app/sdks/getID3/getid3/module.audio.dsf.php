<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.dsf.php                                        //
// module for analyzing dsf/DSF Audio files                    //
// dependencies: module.tag.id3v2.php                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}
getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.tag.id3v2.php', __FILE__, true);

class getid3_dsf extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat']            = 'dsf';
		$info['audio']['dataformat']   = 'dsf';
		$info['audio']['lossless']     = true;
		$info['audio']['bitrate_mode'] = 'cbr';

		$this->fseek($info['avdataoffset']);
		$dsfheader = $this->fread(28 + 12);

		$headeroffset = 0;
		$info['dsf']['dsd']['magic'] = substr($dsfheader, $headeroffset, 4);
		$headeroffset += 4;
		$magic = 'DSD ';
		if ($info['dsf']['dsd']['magic'] != $magic) {
			$this->error('Expecting "'.getid3_lib::PrintHexBytes($magic).'" at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes($info['dsf']['dsd']['magic']).'"');
			unset($info['fileformat']);
			unset($info['audio']);
			unset($info['dsf']);
			return false;
		}
		$info['dsf']['dsd']['dsd_chunk_size']     = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 8)); // should be 28
		$headeroffset += 8;
		$info['dsf']['dsd']['dsf_file_size']      = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 8));
		$headeroffset += 8;
		$info['dsf']['dsd']['meta_chunk_offset']  = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 8));
		$headeroffset += 8;


		$info['dsf']['fmt']['magic'] = substr($dsfheader, $headeroffset, 4);
		$headeroffset += 4;
		$magic = 'fmt ';
		if ($info['dsf']['fmt']['magic'] != $magic) {
			$this->error('Expecting "'.getid3_lib::PrintHexBytes($magic).'" at offset '.$headeroffset.', found "'.getid3_lib::PrintHexBytes($info['dsf']['fmt']['magic']).'"');
			return false;
		}
		$info['dsf']['fmt']['fmt_chunk_size']     = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 8));  // usually 52 bytes
		$headeroffset += 8;
		$dsfheader .= $this->fread($info['dsf']['fmt']['fmt_chunk_size'] - 12 + 12);  // we have already read the entire DSD chunk, plus 12 bytes of FMT. We now want to read the size of FMT, plus 12 bytes into the next chunk to get magic and size.
		if (strlen($dsfheader) != ($info['dsf']['dsd']['dsd_chunk_size'] + $info['dsf']['fmt']['fmt_chunk_size'] + 12)) {
			$this->error('Expecting '.($info['dsf']['dsd']['dsd_chunk_size'] + $info['dsf']['fmt']['fmt_chunk_size']).' bytes header, found '.strlen($dsfheader).' bytes');
			return false;
		}
		$info['dsf']['fmt']['format_version']     = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 4));  // usually "1"
		$headeroffset += 4;
		$info['dsf']['fmt']['format_id']          = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 4));  // usually "0" = "DSD Raw"
		$headeroffset += 4;
		$info['dsf']['fmt']['channel_type_id']    = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 4));
		$headeroffset += 4;
		$info['dsf']['fmt']['channels']           = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 4));
		$headeroffset += 4;
		$info['dsf']['fmt']['sample_rate']        = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 4));
		$headeroffset += 4;
		$info['dsf']['fmt']['bits_per_sample']    = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 4));
		$headeroffset += 4;
		$info['dsf']['fmt']['sample_count']       = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 8));
		$headeroffset += 8;
		$info['dsf']['fmt']['channel_block_size'] = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 4));
		$headeroffset += 4;
		$info['dsf']['fmt']['reserved']           = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 4)); // zero-filled
		$headeroffset += 4;


		$info['dsf']['data']['magic'] = substr($dsfheader, $headeroffset, 4);
		$headeroffset += 4;
		$magic = 'data';
		if ($info['dsf']['data']['magic'] != $magic) {
			$this->error('Expecting "'.getid3_lib::PrintHexBytes($magic).'" at offset '.$headeroffset.', found "'.getid3_lib::PrintHexBytes($info['dsf']['data']['magic']).'"');
			return false;
		}
		$info['dsf']['data']['data_chunk_size']    = getid3_lib::LittleEndian2Int(substr($dsfheader, $headeroffset, 8));
		$headeroffset += 8;
		$info['avdataoffset'] = $headeroffset;
		$info['avdataend']    = $info['avdataoffset'] + $info['dsf']['data']['data_chunk_size'];


		if ($info['dsf']['dsd']['meta_chunk_offset'] > 0) {
			$getid3_id3v2 = new getid3_id3v2($this->getid3);
			$getid3_id3v2->StartingOffset = $info['dsf']['dsd']['meta_chunk_offset'];
			$getid3_id3v2->Analyze();
			unset($getid3_id3v2);
		}


		$info['dsf']['fmt']['channel_type'] = $this->DSFchannelTypeLookup($info['dsf']['fmt']['channel_type_id']);
		$info['audio']['channelmode']       = $info['dsf']['fmt']['channel_type'];
		$info['audio']['bits_per_sample']   = $info['dsf']['fmt']['bits_per_sample'];
		$info['audio']['sample_rate']       = $info['dsf']['fmt']['sample_rate'];
		$info['audio']['channels']          = $info['dsf']['fmt']['channels'];
		$info['audio']['bitrate']           = $info['audio']['bits_per_sample'] * $info['audio']['sample_rate'] * $info['audio']['channels'];
		$info['playtime_seconds']           = ($info['dsf']['data']['data_chunk_size'] * 8) / $info['audio']['bitrate'];

		return true;
	}

	/**
	 * @param int $channel_type_id
	 *
	 * @return string
	 */
	public static function DSFchannelTypeLookup($channel_type_id) {
		static $DSFchannelTypeLookup = array(
			                  // interleaving order:
			1 => 'mono',      // 1: Mono
			2 => 'stereo',    // 1: Front-Left; 2: Front-Right
			3 => '3-channel', // 1: Front-Left; 2: Front-Right; 3: Center
			4 => 'quad',      // 1: Front-Left; 2: Front-Right; 3: Back-Left; 4: Back-Right
			5 => '4-channel', // 1: Front-Left; 2: Front-Right; 3: Center;    4: Low-Frequency
			6 => '5-channel', // 1: Front-Left; 2: Front-Right; 3: Center;    4: Back-Left      5: Back-Right
			7 => '5.1',       // 1: Front-Left; 2: Front-Right; 3: Center;    4: Low-Frequency; 5: Back-Left;  6: Back-Right
		);
		return (isset($DSFchannelTypeLookup[$channel_type_id]) ? $DSFchannelTypeLookup[$channel_type_id] : '');
	}

}
