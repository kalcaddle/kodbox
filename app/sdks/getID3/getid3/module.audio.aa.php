<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.aa.php                                         //
// module for analyzing Audible Audiobook files                //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_aa extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$AAheader  = $this->fread(8);

		$magic = "\x57\x90\x75\x36";
		if (substr($AAheader, 4, 4) != $magic) {
			$this->error('Expecting "'.getid3_lib::PrintHexBytes($magic).'" at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes(substr($AAheader, 4, 4)).'"');
			return false;
		}

		// shortcut
		$info['aa'] = array();
		$thisfile_aa = &$info['aa'];

		$info['fileformat']            = 'aa';
		$info['audio']['dataformat']   = 'aa';
		$this->error('Audible Audiobook (.aa) parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;
		$info['audio']['bitrate_mode'] = 'cbr'; // is it?
		$thisfile_aa['encoding']       = 'ISO-8859-1';

		$thisfile_aa['filesize'] = getid3_lib::BigEndian2Int(substr($AAheader,  0, 4));
		if ($thisfile_aa['filesize'] > ($info['avdataend'] - $info['avdataoffset'])) {
			$this->warning('Possible truncated file - expecting "'.$thisfile_aa['filesize'].'" bytes of data, only found '.($info['avdataend'] - $info['avdataoffset']).' bytes"');
		}

		$info['audio']['bits_per_sample'] = 16; // is it?
		$info['audio']['sample_rate'] = $thisfile_aa['sample_rate'];
		$info['audio']['channels']    = $thisfile_aa['channels'];

		//$info['playtime_seconds'] = 0;
		//$info['audio']['bitrate'] = 0;

		return true;
	}

}
