<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.bink.php                                       //
// module for analyzing Bink or Smacker audio-video files      //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_bink extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->error('Bink / Smacker files not properly processed by this version of getID3() ['.$this->getid3->version().']');

		$this->fseek($info['avdataoffset']);
		$fileTypeID = $this->fread(3);
		switch ($fileTypeID) {
			case 'BIK':
				return $this->ParseBink();

			case 'SMK':
				return $this->ParseSmacker();

			default:
				$this->error('Expecting "BIK" or "SMK" at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes($fileTypeID).'"');
				return false;
		}
	}

	/**
	 * @return bool
	 */
	public function ParseBink() {
		$info = &$this->getid3->info;
		$info['fileformat']          = 'bink';
		$info['video']['dataformat'] = 'bink';

		$fileData = 'BIK'.$this->fread(13);

		$info['bink']['data_size']   = getid3_lib::LittleEndian2Int(substr($fileData, 4, 4));
		$info['bink']['frame_count'] = getid3_lib::LittleEndian2Int(substr($fileData, 8, 2));

		if (($info['avdataend'] - $info['avdataoffset']) != ($info['bink']['data_size'] + 8)) {
			$this->error('Probably truncated file: expecting '.$info['bink']['data_size'].' bytes, found '.($info['avdataend'] - $info['avdataoffset']));
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function ParseSmacker() {
		$info = &$this->getid3->info;
		$info['fileformat']          = 'smacker';
		$info['video']['dataformat'] = 'smacker';

		return true;
	}

}
