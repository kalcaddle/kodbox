<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.misc.msoffice.php                                    //
// module for analyzing MS Office (.doc, .xls, etc) files      //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_msoffice extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$DOCFILEheader = $this->fread(8);
		$magic = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
		if (substr($DOCFILEheader, 0, 8) != $magic) {
			$this->error('Expecting "'.getid3_lib::PrintHexBytes($magic).'" at '.$info['avdataoffset'].', found '.getid3_lib::PrintHexBytes(substr($DOCFILEheader, 0, 8)).' instead.');
			return false;
		}
		$info['fileformat'] = 'msoffice';

		$this->error('MS Office (.doc, .xls, etc) parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;

	}

}
