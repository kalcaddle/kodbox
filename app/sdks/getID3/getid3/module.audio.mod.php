<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.mod.php                                        //
// module for analyzing MOD Audio files                        //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_mod extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;
		$this->fseek($info['avdataoffset']);
		$fileheader = $this->fread(1088);
		if (preg_match('#^IMPM#', $fileheader)) {
			return $this->getITheaderFilepointer();
		} elseif (preg_match('#^Extended Module#', $fileheader)) {
			return $this->getXMheaderFilepointer();
		} elseif (preg_match('#^.{44}SCRM#', $fileheader)) {
			return $this->getS3MheaderFilepointer();
		} elseif (preg_match('#^.{1080}(M\\.K\\.|M!K!|FLT4|FLT8|[5-9]CHN|[1-3][0-9]CH)#', $fileheader)) {
			return $this->getMODheaderFilepointer();
		}
		$this->error('This is not a known type of MOD file');
		return false;
	}

	/**
	 * @return bool
	 */
	public function getMODheaderFilepointer() {
		$info = &$this->getid3->info;
		$this->fseek($info['avdataoffset'] + 1080);
		$FormatID = $this->fread(4);
		if (!preg_match('#^(M.K.|[5-9]CHN|[1-3][0-9]CH)$#', $FormatID)) {
			$this->error('This is not a known type of MOD file');
			return false;
		}

		$info['fileformat'] = 'mod';

		$this->error('MOD parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;
	}

	/**
	 * @return bool
	 */
	public function getXMheaderFilepointer() {
		$info = &$this->getid3->info;
		$this->fseek($info['avdataoffset']);
		$FormatID = $this->fread(15);
		if (!preg_match('#^Extended Module$#', $FormatID)) {
			$this->error('This is not a known type of XM-MOD file');
			return false;
		}

		$info['fileformat'] = 'xm';

		$this->error('XM-MOD parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;
	}

	/**
	 * @return bool
	 */
	public function getS3MheaderFilepointer() {
		$info = &$this->getid3->info;
		$this->fseek($info['avdataoffset'] + 44);
		$FormatID = $this->fread(4);
		if (!preg_match('#^SCRM$#', $FormatID)) {
			$this->error('This is not a ScreamTracker MOD file');
			return false;
		}

		$info['fileformat'] = 's3m';

		$this->error('ScreamTracker parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;
	}

	/**
	 * @return bool
	 */
	public function getITheaderFilepointer() {
		$info = &$this->getid3->info;
		$this->fseek($info['avdataoffset']);
		$FormatID = $this->fread(4);
		if (!preg_match('#^IMPM$#', $FormatID)) {
			$this->error('This is not an ImpulseTracker MOD file');
			return false;
		}

		$info['fileformat'] = 'it';

		$this->error('ImpulseTracker parsing not enabled in this version of getID3() ['.$this->getid3->version().']');
		return false;
	}

}
