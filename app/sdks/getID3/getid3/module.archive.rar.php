<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.archive.rar.php                                      //
// module for analyzing RAR files                              //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_rar extends getid3_handler
{
	/**
	 * if true use PHP RarArchive extension, if false (non-extension parsing not yet written in getID3)
	 *
	 * @var bool
	 */
	public $use_php_rar_extension = true;

	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$info['fileformat'] = 'rar';

		if ($this->use_php_rar_extension === true) {
			if (function_exists('rar_open')) {
				if ($rp = rar_open($info['filenamepath'])) {
					$info['rar']['files'] = array();
					$entries = rar_list($rp);
					foreach ($entries as $entry) {
						$info['rar']['files'] = getid3_lib::array_merge_clobber($info['rar']['files'], getid3_lib::CreateDeepArray($entry->getName(), '/', $entry->getUnpackedSize()));
					}
					rar_close($rp);
					return true;
				} else {
					$this->error('failed to rar_open('.$info['filename'].')');
				}
			} else {
				$this->error('RAR support does not appear to be available in this PHP installation');
			}
		} else {
			$this->error('PHP-RAR processing has been disabled (set $getid3_rar->use_php_rar_extension=true to enable)');
		}
		return false;

	}

}
