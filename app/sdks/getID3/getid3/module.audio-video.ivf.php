<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.ivf.php                                        //
// module for analyzing IVF audio-video files                  //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_ivf extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat']          = 'ivf';
		$info['video']['dataformat'] = 'ivf';

		$this->fseek($info['avdataoffset']);
		$IVFheader = $this->fread(32);

		if (substr($IVFheader, 0, 4) == 'DKIF') {

			// https://wiki.multimedia.cx/index.php/IVF
			$info['ivf']['header']['signature']            =                              substr($IVFheader,  0, 4);
			$info['ivf']['header']['version']              = getid3_lib::LittleEndian2Int(substr($IVFheader,  4, 2)); // should be 0
			$info['ivf']['header']['headersize']           = getid3_lib::LittleEndian2Int(substr($IVFheader,  6, 2));
			$info['ivf']['header']['fourcc']               =                              substr($IVFheader,  8, 4);
			$info['ivf']['header']['resolution_x']         = getid3_lib::LittleEndian2Int(substr($IVFheader, 12, 2));
			$info['ivf']['header']['resolution_y']         = getid3_lib::LittleEndian2Int(substr($IVFheader, 14, 2));
			$info['ivf']['header']['timebase_numerator']   = getid3_lib::LittleEndian2Int(substr($IVFheader, 16, 4));
			$info['ivf']['header']['timebase_denominator'] = getid3_lib::LittleEndian2Int(substr($IVFheader, 20, 4));
			$info['ivf']['header']['frame_count']          = getid3_lib::LittleEndian2Int(substr($IVFheader, 24, 4));
			//$info['ivf']['header']['reserved']             =                              substr($IVFheader, 28, 4);

			$info['ivf']['header']['frame_rate'] = (float) $info['ivf']['header']['timebase_numerator'] / $info['ivf']['header']['timebase_denominator'];

			if ($info['ivf']['header']['version'] > 0) {
				$this->warning('Expecting IVF header version 0, found version '.$info['ivf']['header']['version'].', results may not be accurate');
			}

			$info['video']['resolution_x']    =         $info['ivf']['header']['resolution_x'];
			$info['video']['resolution_y']    =         $info['ivf']['header']['resolution_y'];
			$info['video']['codec']           =         $info['ivf']['header']['fourcc'];

			$info['ivf']['frame_count'] = 0;
			$timestamp                  = 0;
			while (!$this->feof()) {
				if ($frameheader = $this->fread(12)) {
					$framesize = getid3_lib::LittleEndian2Int(substr($frameheader, 0, 4)); // size of frame in bytes (not including the 12-byte header)
					$timestamp = getid3_lib::LittleEndian2Int(substr($frameheader, 4, 8)); // 64-bit presentation timestamp
					$this->fseek($framesize, SEEK_CUR);
					$info['ivf']['frame_count']++;
				}
			}
			if ($info['ivf']['frame_count']) {
				$info['playtime_seconds']    = $timestamp / 100000;
				$info['video']['frame_rate'] = (float) $info['ivf']['frame_count'] / $info['playtime_seconds'];
			}

		} else {
			$this->error('Expecting "DKIF" at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes(substr($IVFheader, 0, 4)).'"');
			return false;
		}

		return true;
	}

}
