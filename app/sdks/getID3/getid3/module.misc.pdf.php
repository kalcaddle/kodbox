<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.misc.pdf.php                                         //
// module for analyzing PDF files                              //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_pdf extends getid3_handler
{
	/** misc.pdf
	 * return full details of PDF Cross-Reference Table (XREF)
	 *
	 * @var bool
	 */
	public $returnXREF = false;

	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;
		$this->fseek(0);
		if (preg_match('#^%PDF-([0-9\\.]+)$#', rtrim($this->fgets()), $matches)) {
			$info['pdf']['header']['version'] = floatval($matches[1]);
			$info['fileformat'] = 'pdf';

			// the PDF Cross-Reference Table (XREF) is located near the end of the file
			// the starting offset is specified in the penultimate section, on the two lines just before "%%EOF"
			// the first line is "startxref", the second line is the byte offset of the XREF.
			// We know the length of "%%EOF" and "startxref", but the offset could be 2-10 bytes,
			// and we're not sure if the line ends are one or two bytes, so we might find "startxref" as little as 18(?) bytes
			// from EOF, but it could 30 bytes, so we start 40 bytes back just to be safe and do a search for the data we want.
			$this->fseek(-40, SEEK_END);
			if (preg_match('#[\r\n]startxref[ \r\n]+([0-9]+)[ \r\n]+#', $this->fread(40), $matches)) {
				$info['pdf']['trailer']['startxref'] = intval($matches[1]);
				$this->parseXREF($info['pdf']['trailer']['startxref']);
				if (!empty($info['pdf']['xref']['offset'])) {
					while (!$this->feof() && (max(array_keys($info['pdf']['xref']['offset'])) > $info['pdf']['xref']['count'])) {
						// suspect that there may be another XREF entry somewhere in the file, brute-force scan for it
						/*
						// starting at last known entry of main XREF table
						$this->fseek(max($info['pdf']['xref']['offset']));
						*/
						// starting at the beginning of the file
						$this->fseek(0);
						while (!$this->feof()) {
							$XREFoffset = $this->ftell();
							if (rtrim($this->fgets()) == 'xref') {
								if (empty($info['pdf']['xref']['xref_offsets']) || !in_array($XREFoffset, $info['pdf']['xref']['xref_offsets'])) {
									$this->parseXREF($XREFoffset);
									break;
								}
							}
						}
					}

					asort($info['pdf']['xref']['offset']);
					$maxObjLengths = array();
					$prevOffset = 0;
					$prevObjNum = 0;
					foreach ($info['pdf']['xref']['offset'] as $objectNumber => $offset) {
						// walk through all listed offsets to calculate the maximum possible length for each known object
						if ($prevObjNum) {
							$maxObjLengths[$prevObjNum] = $offset - $prevOffset;
						}
						$prevOffset = $offset;
						$prevObjNum = $objectNumber;
					}
					ksort($maxObjLengths);
					foreach ($info['pdf']['xref']['offset'] as $objectNumber => $offset) {
						if ($info['pdf']['xref']['entry'][$objectNumber] == 'f') {
							// "free" object means "deleted", ignore
							continue;
						}
						if (!empty($maxObjLengths[$objectNumber]) && ($maxObjLengths[$objectNumber] < $this->getid3->option_fread_buffer_size)) {
							// ignore object that are zero-size or >32kB, they are unlikely to contain information we're interested in
							$this->fseek($offset);
							$objBlob = $this->fread($maxObjLengths[$objectNumber]);
							if (preg_match('#^'.$objectNumber.'[\\x00 \\r\\n\\t]*([0-9]+)[\\x00 \\r\\n\\t]*obj[\\x00 \\r\\n\\t]*(.*)(endobj)?[\\x00 \\r\\n\\t]*$#s', $objBlob, $matches)) {
								list($dummy, $generation, $objectData) = $matches;
								if (preg_match('#^<<[\r\n\s]*(/Type|/Pages|/Parent [0-9]+ [0-9]+ [A-Z]|/Count [0-9]+|/Kids *\\[[0-9A-Z ]+\\]|[\r\n\s])+[\r\n\s]*>>#', $objectData, $matches)) {
									if (preg_match('#/Count ([0-9]+)#', $objectData, $matches)) {
										$info['pdf']['pages'] = (int) $matches[1];
										break; // for now this is the only data we're looking for in the PDF not need to loop through every object in the file (and a large PDF may contain MANY objects). And it MAY be possible that there are other objects elsewhere in the file that define additional (or removed?) pages
									}
								}
							} else {
								$this->error('Unexpected structure "'.substr($objBlob, 0, 100).'" at offset '.$offset);
								break;
							}
						}
					}
					if (!$this->returnXREF) {
						unset($info['pdf']['xref']['offset'], $info['pdf']['xref']['generation'], $info['pdf']['xref']['entry'], $info['pdf']['xref']['xref_offsets']);
					}

				} else {
					$this->error('Did not find "xref" at offset '.$info['pdf']['trailer']['startxref']);
				}
			} else {
				$this->error('Did not find "startxref" in the last 40 bytes of the PDF');
			}

			$this->warning('PDF parsing incomplete in this version of getID3() ['.$this->getid3->version().']');
			return true;
		}
		$this->error('Did not find "%PDF" at the beginning of the PDF');
		return false;

	}

	/**
	 * @return bool
	 */
	private function parseXREF($XREFoffset) {
		$info = &$this->getid3->info;

		$this->fseek($XREFoffset);
		if (rtrim($this->fgets()) == 'xref') {

			$info['pdf']['xref']['xref_offsets'][$XREFoffset] = $XREFoffset;
			list($firstObjectNumber, $XREFcount) = explode(' ', rtrim($this->fgets()));
			$firstObjectNumber = (int) $firstObjectNumber;
			$XREFcount = (int) $XREFcount;
			$info['pdf']['xref']['count'] = $XREFcount + (!empty($info['pdf']['xref']['count']) ? $info['pdf']['xref']['count'] : 0);
			for ($i = 0; $i < $XREFcount; $i++) {
				$line = rtrim($this->fgets());
				if (preg_match('#^([0-9]+) ([0-9]+) ([nf])$#', $line, $matches)) {
					$info['pdf']['xref']['offset'][($firstObjectNumber + $i)]     = (int) $matches[1];
					$info['pdf']['xref']['generation'][($firstObjectNumber + $i)] = (int) $matches[2];
					$info['pdf']['xref']['entry'][($firstObjectNumber + $i)]      =       $matches[3];
				} else {
					$this->error('failed to parse XREF entry #'.$i.' in XREF table at offset '.$XREFoffset);
					return false;
				}
			}
			sort($info['pdf']['xref']['xref_offsets']);
			return true;

		}
		$this->warning('failed to find expected XREF structure at offset '.$XREFoffset);
		return false;
	}

}
