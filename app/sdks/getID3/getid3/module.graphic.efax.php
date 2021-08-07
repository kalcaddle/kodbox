<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.archive.efax.php                                     //
// module for analyzing eFax files                             //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_efax extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$efaxheader = $this->fread(1024);

		$info['efax']['header']['magic'] = substr($efaxheader, 0, 2);
		if ($info['efax']['header']['magic'] != "\xDC\xFE") {
			$this->error('Invalid eFax byte order identifier (expecting DC FE, found '.getid3_lib::PrintHexBytes($info['efax']['header']['magic']).') at offset '.$info['avdataoffset']);
			return false;
		}
		$info['fileformat'] = 'efax';

		$info['efax']['header']['filesize'] = getid3_lib::LittleEndian2Int(substr($efaxheader, 2, 4));
		if ($info['efax']['header']['filesize'] != $info['filesize']) {
			$this->error('Probable '.(($info['efax']['header']['filesize'] > $info['filesize']) ? 'truncated' : 'corrupt').' file, expecting '.$info['efax']['header']['filesize'].' bytes, found '.$info['filesize'].' bytes');
		}
		$info['efax']['header']['software1'] =                        rtrim(substr($efaxheader,  26, 32), "\x00");
		$info['efax']['header']['software2'] =                        rtrim(substr($efaxheader,  58, 32), "\x00");
		$info['efax']['header']['software3'] =                        rtrim(substr($efaxheader,  90, 32), "\x00");

		$info['efax']['header']['pages']      = getid3_lib::LittleEndian2Int(substr($efaxheader, 198, 2));
		$info['efax']['header']['data_bytes'] = getid3_lib::LittleEndian2Int(substr($efaxheader, 202, 4));

		$this->error('eFax parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;
	}

}
