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
		} elseif (preg_match('#^.{44}SCRM#s', $fileheader)) {
			return $this->getS3MheaderFilepointer();
		//} elseif (preg_match('#^.{1080}(M\\.K\\.|M!K!|FLT4|FLT8|[5-9]CHN|[1-3][0-9]CH)#s', $fileheader)) {
		} elseif (preg_match('#^.{1080}(M\\.K\\.)#s', $fileheader)) {
			/*
			The four letters "M.K." - This is something Mahoney & Kaktus inserted when they
			increased the number of samples from 15 to 31. If it's not there, the module/song
			uses 15 samples or the text has been removed to make the module harder to rip.
			Startrekker puts "FLT4" or "FLT8" there instead.
			If there are more than 64 patterns, PT2.3 will insert M!K! here.
			*/
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
		$this->fseek($info['avdataoffset']);
		$filedata = $this->fread(1084);
		//if (!preg_match('#^(M.K.|[5-9]CHN|[1-3][0-9]CH)$#', $FormatID)) {
		if (substr($filedata, 1080, 4) == 'M.K.') {

			// + 0                song/module working title
			// + 20               15 sample headers (see below)
			// + 470              song length (number of steps in pattern table)
			// + 471              song speed in beats per minute (see below)
			// + 472              pattern step table
			$offset = 0;
 			$info['mod']['title'] = rtrim(substr($filedata, $offset, 20), "\x00");  $offset += 20;

 			$info['tags']['mod']['title'] = array($info['mod']['title']);

 			for ($samplenumber = 0; $samplenumber <= 30; $samplenumber++) {
 				$sampledata = array();
 				$sampledata['name']          =                           substr($filedata, $offset, 22);   $offset += 22;
 				$sampledata['length']        = getid3_lib::BigEndian2Int(substr($filedata, $offset,  2));  $offset +=  2;
 				$sampledata['volume']        = getid3_lib::BigEndian2Int(substr($filedata, $offset,  2));  $offset +=  2;
 				$sampledata['repeat_offset'] = getid3_lib::BigEndian2Int(substr($filedata, $offset,  2));  $offset +=  2;
 				$sampledata['repeat_length'] = getid3_lib::BigEndian2Int(substr($filedata, $offset,  2));  $offset +=  2;
 				$info['mod']['samples'][$samplenumber] = $sampledata;
 			}

 			$info['mod']['song_length'] = getid3_lib::BigEndian2Int(substr($filedata, $offset++,  1));// Songlength. Range is 1-128.
 			$info['mod']['bpm']         = getid3_lib::BigEndian2Int(substr($filedata, $offset++,  1));// This byte is set to 127, so that old trackers will search through all patterns when loading. Noisetracker uses this byte for restart, ProTracker doesn't.

 			for ($songposition = 0; $songposition <= 127; $songposition++) {
 				// Song positions 0-127.  Each hold a number from 0-63 (or 0-127)
 				// that tells the tracker what pattern to play at that position.
				$info['mod']['song_positions'][$songposition] = getid3_lib::BigEndian2Int(substr($filedata, $offset++, 1));
 			}

		} else {
			$this->error('unknown MOD ID at offset 1080: '.getid3_lib::PrintHexBytes(substr($filedata, 1080, 4)));
			return false;
		}
		$info['fileformat'] = 'mod';

$this->warning('MOD (SoundTracker) parsing incomplete in this version of getID3() ['.$this->getid3->version().']');
		return true;
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
