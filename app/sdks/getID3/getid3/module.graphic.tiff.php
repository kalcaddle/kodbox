<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.archive.tiff.php                                     //
// module for analyzing TIFF files                             //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

if (!defined('GETID3_INCLUDEPATH')) { // prevent path-exposing attacks that access modules directly on public webservers
	exit;
}

class getid3_tiff extends getid3_handler
{
	/**
	 * @return bool
	 */
	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);
		$TIFFheader = $this->fread(4);

		switch (substr($TIFFheader, 0, 2)) {
			case 'II':
				$info['tiff']['byte_order'] = 'Intel';
				break;
			case 'MM':
				$info['tiff']['byte_order'] = 'Motorola';
				break;
			default:
				$this->error('Invalid TIFF byte order identifier ('.substr($TIFFheader, 0, 2).') at offset '.$info['avdataoffset']);
				return false;
		}

		$info['fileformat']          = 'tiff';
		$info['video']['dataformat'] = 'tiff';
		$info['video']['lossless']   = true;
		$info['tiff']['ifd']         = array();
		$CurrentIFD                  = array();

		$FieldTypeByteLength = array(1=>1, 2=>1, 3=>2, 4=>4, 5=>8);

		$nextIFDoffset = $this->TIFFendian2Int($this->fread(4), $info['tiff']['byte_order']);

		while ($nextIFDoffset > 0) {

			$CurrentIFD['offset'] = $nextIFDoffset;

			$this->fseek($info['avdataoffset'] + $nextIFDoffset);
			$CurrentIFD['fieldcount'] = $this->TIFFendian2Int($this->fread(2), $info['tiff']['byte_order']);

			for ($i = 0; $i < $CurrentIFD['fieldcount']; $i++) {
				$CurrentIFD['fields'][$i]['raw']['tag']      = $this->TIFFendian2Int($this->fread(2), $info['tiff']['byte_order']);
				$CurrentIFD['fields'][$i]['raw']['type']     = $this->TIFFendian2Int($this->fread(2), $info['tiff']['byte_order']);
				$CurrentIFD['fields'][$i]['raw']['length']   = $this->TIFFendian2Int($this->fread(4), $info['tiff']['byte_order']);
				$CurrentIFD['fields'][$i]['raw']['valoff']   =                       $this->fread(4); // To save time and space the Value Offset contains the Value instead of pointing to the Value if and only if the Value fits into 4 bytes. If the Value is shorter than 4 bytes, it is left-justified within the 4-byte Value Offset, i.e., stored in the lowernumbered bytes. Whether the Value fits within 4 bytes is determined by the Type and Count of the field.
				$CurrentIFD['fields'][$i]['raw']['tag_name'] = $this->TIFFcommentName($CurrentIFD['fields'][$i]['raw']['tag']);

				switch ($CurrentIFD['fields'][$i]['raw']['type']) {
					case 1: // BYTE  An 8-bit unsigned integer.
						if ($CurrentIFD['fields'][$i]['raw']['length'] <= 4) {
							$CurrentIFD['fields'][$i]['value']  = $this->TIFFendian2Int(substr($CurrentIFD['fields'][$i]['raw']['valoff'], 0, 1), $info['tiff']['byte_order']);
						} else {
							$CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int($CurrentIFD['fields'][$i]['raw']['valoff'], $info['tiff']['byte_order']);
						}
						break;

					case 2: // ASCII 8-bit bytes  that store ASCII codes; the last byte must be null.
						if ($CurrentIFD['fields'][$i]['raw']['length'] <= 4) {
							$CurrentIFD['fields'][$i]['value']  = substr($CurrentIFD['fields'][$i]['raw']['valoff'], 3);
						} else {
							$CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int($CurrentIFD['fields'][$i]['raw']['valoff'], $info['tiff']['byte_order']);
						}
						break;

					case 3: // SHORT A 16-bit (2-byte) unsigned integer.
						if ($CurrentIFD['fields'][$i]['raw']['length'] <= 2) {
							$CurrentIFD['fields'][$i]['value']  = $this->TIFFendian2Int(substr($CurrentIFD['fields'][$i]['raw']['valoff'], 0, 2), $info['tiff']['byte_order']);
						} else {
							$CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int($CurrentIFD['fields'][$i]['raw']['valoff'], $info['tiff']['byte_order']);
						}
						break;

					case 4: // LONG  A 32-bit (4-byte) unsigned integer.
						if ($CurrentIFD['fields'][$i]['raw']['length'] <= 4) {
							$CurrentIFD['fields'][$i]['value']  = $this->TIFFendian2Int($CurrentIFD['fields'][$i]['raw']['valoff'], $info['tiff']['byte_order']);
						} else {
							$CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int($CurrentIFD['fields'][$i]['raw']['valoff'], $info['tiff']['byte_order']);
						}
						break;

					case 5: // RATIONAL   Two LONG_s:  the first represents the numerator of a fraction, the second the denominator.
					case 7: // UNDEFINED An 8-bit byte that may contain anything, depending on the definition of the field.
						$CurrentIFD['fields'][$i]['offset'] = $this->TIFFendian2Int($CurrentIFD['fields'][$i]['raw']['valoff'], $info['tiff']['byte_order']);
						break;

					// Warning: It is possible that other TIFF field types will be added in the future. Readers should skip over fields containing an unexpected field type.
					// In TIFF 6.0, some new field types have been defined:
					// These new field types are also governed by the byte order (II or MM) in the TIFF header.
					case 6: // SBYTE An 8-bit signed (twos-complement) integer.
					case 8: // SSHORT A 16-bit (2-byte) signed (twos-complement) integer.
					case 9: // SLONG A 32-bit (4-byte) signed (twos-complement) integer.
					case 10: // SRATIONAL Two SLONGs: the first represents the numerator of a fraction, the second the denominator.
					case 11: // FLOAT Single precision (4-byte) IEEE format
					case 12: // DOUBLE Double precision (8-byte) IEEE format
					default:
						$this->warning('unhandled IFD field type '.$CurrentIFD['fields'][$i]['raw']['type'].' for IFD entry '.$i);
						break;
				}
			}

			$info['tiff']['ifd'][] = $CurrentIFD;
			$CurrentIFD = array();
			$nextIFDoffset = $this->TIFFendian2Int($this->fread(4), $info['tiff']['byte_order']);

		}

		foreach ($info['tiff']['ifd'] as $IFDid => $IFDarray) {
			foreach ($IFDarray['fields'] as $key => $fieldarray) {
				switch ($fieldarray['raw']['tag']) {
					case 256: // ImageWidth
					case 257: // ImageLength
					case 258: // BitsPerSample
					case 259: // Compression
						if (!isset($fieldarray['value'])) {
							$this->fseek($fieldarray['offset']);
							$info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'] = $this->fread($fieldarray['raw']['length'] * $FieldTypeByteLength[$fieldarray['raw']['type']]);

						}
						break;

					case 270: // ImageDescription
					case 271: // Make
					case 272: // Model
					case 305: // Software
					case 306: // DateTime
					case 315: // Artist
					case 316: // HostComputer
						if (isset($fieldarray['value'])) {
							$info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'] = $fieldarray['value'];
						} else {
							$this->fseek($fieldarray['offset']);
							$info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'] = $this->fread($fieldarray['raw']['length'] * $FieldTypeByteLength[$fieldarray['raw']['type']]);

						}
						break;
					case 700:
						$XMPmagic = '<?xpacket';
						$this->fseek($fieldarray['offset']);
						$xmpkey = (isset($info['tiff']['XMP']) ? count($info['tiff']['XMP']) : 0);
						$info['tiff']['XMP'][$xmpkey]['raw'] = $this->fread($fieldarray['raw']['length']);
						if (substr($info['tiff']['XMP'][$xmpkey]['raw'], 0, strlen($XMPmagic)) != $XMPmagic) {
							$this->warning('did not find expected XMP data at offset '.$fieldarray['offset']);
							unset($info['tiff']['XMP'][$xmpkey]['raw']);
						}
						break;
				}
				switch ($fieldarray['raw']['tag']) {
					case 256: // ImageWidth
						$info['video']['resolution_x'] = $fieldarray['value'];
						break;

					case 257: // ImageLength
						$info['video']['resolution_y'] = $fieldarray['value'];
						break;

					case 258: // BitsPerSample
						if (isset($fieldarray['value'])) {
							$info['video']['bits_per_sample'] = $fieldarray['value'];
						} else {
							$info['video']['bits_per_sample'] = 0;
							for ($i = 0; $i < $fieldarray['raw']['length']; $i++) {
								$info['video']['bits_per_sample'] += $this->TIFFendian2Int(substr($info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'], $i * $FieldTypeByteLength[$fieldarray['raw']['type']], $FieldTypeByteLength[$fieldarray['raw']['type']]), $info['tiff']['byte_order']);
							}
						}
						break;

					case 259: // Compression
						$info['video']['codec'] = $this->TIFFcompressionMethod($fieldarray['value']);
						break;

					case 270: // ImageDescription
					case 271: // Make
					case 272: // Model
					case 305: // Software
					case 306: // DateTime
					case 315: // Artist
					case 316: // HostComputer
						$TIFFcommentName = strtolower($fieldarray['raw']['tag_name']);
						if (isset($info['tiff']['comments'][$TIFFcommentName])) {
							$info['tiff']['comments'][$TIFFcommentName][] =       $info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data'];
						} else {
							$info['tiff']['comments'][$TIFFcommentName]   = array($info['tiff']['ifd'][$IFDid]['fields'][$key]['raw']['data']);
						}
						break;

					default:
						break;
				}
			}
		}
		
		// add by warlee; 部分tif图片获取尺寸错误问题处理;
		$imageInfo = getimagesize_io($info['filenamepath']);
		if($imageInfo){
			$info['video']['resolution_x'] = $imageInfo[0];
			$info['video']['resolution_y'] = $imageInfo[1];
		}
		
		return true;
	}

	/**
	 * @param string $bytestring
	 * @param string $byteorder
	 *
	 * @return int|float|false
	 */
	public function TIFFendian2Int($bytestring, $byteorder) {
		if ($byteorder == 'Intel') {
			return getid3_lib::LittleEndian2Int($bytestring);
		} elseif ($byteorder == 'Motorola') {
			return getid3_lib::BigEndian2Int($bytestring);
		}
		return false;
	}

	/**
	 * @param int $id
	 *
	 * @return string
	 */
	public function TIFFcompressionMethod($id) {
		// https://en.wikipedia.org/wiki/TIFF#TIFF_Compression_Tag
		static $TIFFcompressionMethod = array();
		if (empty($TIFFcompressionMethod)) {
			$TIFFcompressionMethod = array(
				0x0001 => 'Uncompressed',
				0x0002 => 'Huffman',
				0x0003 => 'CCITT T.4',
				0x0004 => 'CCITT T.6',
				0x0005 => 'LZW',
				0x0006 => 'JPEG-old',
				0x0007 => 'JPEG',
				0x0008 => 'deflate',
				0x0009 => 'JBIG ITU-T T.85',
				0x000A => 'JBIG ITU-T T.43',
				0x7FFE => 'NeXT RLE 2-bit',
				0x8005 => 'PackBits',
				0x8029 => 'ThunderScan RLE 4-bit',
				0x807F => 'RasterPadding',
				0x8080 => 'RLE-LW',
				0x8081 => 'RLE-CT',
				0x8082 => 'RLE-BL',
				0x80B2 => 'deflate-PK',
				0x80B3 => 'Kodak-DCS',
				0x8765 => 'JBIG',
				0x8798 => 'JPEG2000',
				0x8799 => 'Nikon NEF',
				0x879B => 'JBIG2',
			);
		}
		return (isset($TIFFcompressionMethod[$id]) ? $TIFFcompressionMethod[$id] : 'unknown/invalid ('.$id.')');
	}

	/**
	 * @param int $id
	 *
	 * @return string
	 */
	public function TIFFcommentName($id) {
		// https://www.awaresystems.be/imaging/tiff/tifftags.html
		static $TIFFcommentName = array();
		if (empty($TIFFcommentName)) {
			$TIFFcommentName = array(
				254 => 'NewSubfileType',
				255 => 'SubfileType',
				256 => 'ImageWidth',
				257 => 'ImageLength',
				258 => 'BitsPerSample',
				259 => 'Compression',
				262 => 'PhotometricInterpretation',
				263 => 'Threshholding',
				264 => 'CellWidth',
				265 => 'CellLength',
				266 => 'FillOrder',
				269 => 'DocumentName',
				270 => 'ImageDescription',
				271 => 'Make',
				272 => 'Model',
				273 => 'StripOffsets',
				274 => 'Orientation',
				277 => 'SamplesPerPixel',
				278 => 'RowsPerStrip',
				279 => 'StripByteCounts',
				280 => 'MinSampleValue',
				281 => 'MaxSampleValue',
				282 => 'XResolution',
				283 => 'YResolution',
				284 => 'PlanarConfiguration',
				285 => 'PageName',
				286 => 'XPosition',
				287 => 'YPosition',
				288 => 'FreeOffsets',
				289 => 'FreeByteCounts',
				290 => 'GrayResponseUnit',
				291 => 'GrayResponseCurve',
				292 => 'T4Options',
				293 => 'T6Options',
				296 => 'ResolutionUnit',
				297 => 'PageNumber',
				301 => 'TransferFunction',
				305 => 'Software',
				306 => 'DateTime',
				315 => 'Artist',
				316 => 'HostComputer',
				317 => 'Predictor',
				318 => 'WhitePoint',
				319 => 'PrimaryChromaticities',
				320 => 'ColorMap',
				321 => 'HalftoneHints',
				322 => 'TileWidth',
				323 => 'TileLength',
				324 => 'TileOffsets',
				325 => 'TileByteCounts',
				326 => 'BadFaxLines',
				327 => 'CleanFaxData',
				328 => 'ConsecutiveBadFaxLines',
				330 => 'SubIFDs',
				332 => 'InkSet',
				333 => 'InkNames',
				334 => 'NumberOfInks',
				336 => 'DotRange',
				337 => 'TargetPrinter',
				338 => 'ExtraSamples',
				339 => 'SampleFormat',
				340 => 'SMinSampleValue',
				341 => 'SMaxSampleValue',
				342 => 'TransferRange',
				343 => 'ClipPath',
				344 => 'XClipPathUnits',
				345 => 'YClipPathUnits',
				346 => 'Indexed',
				347 => 'JPEGTables',
				351 => 'OPIProxy',
				400 => 'GlobalParametersIFD',
				401 => 'ProfileType',
				402 => 'FaxProfile',
				403 => 'CodingMethods',
				404 => 'VersionYear',
				405 => 'ModeNumber',
				433 => 'Decode',
				434 => 'DefaultImageColor',
				512 => 'JPEGProc',
				513 => 'JPEGInterchangeFormat',
				514 => 'JPEGInterchangeFormatLngth',
				515 => 'JPEGRestartInterval',
				517 => 'JPEGLosslessPredictors',
				518 => 'JPEGPointTransforms',
				519 => 'JPEGQTables',
				520 => 'JPEGDCTables',
				521 => 'JPEGACTables',
				529 => 'YCbCrCoefficients',
				530 => 'YCbCrSubSampling',
				531 => 'YCbCrPositioning',
				532 => 'ReferenceBlackWhite',
				559 => 'StripRowCounts',
				700 => 'XMP',

				32781 => 'ImageID',
				33432 => 'Copyright',
				34732 => 'ImageLayer',

				// Private Tags - https://www.awaresystems.be/imaging/tiff/tifftags/private.html
				32932 => 'Wang Annotation',                    // Annotation data, as used in 'Imaging for Windows'.
				33445 => 'MD FileTag',                         // Specifies the pixel data format encoding in the Molecular Dynamics GEL file format.
				33446 => 'MD ScalePixel',                      // Specifies a scale factor in the Molecular Dynamics GEL file format.
				33447 => 'MD ColorTable',                      // Used to specify the conversion from 16bit to 8bit in the Molecular Dynamics GEL file format.
				33448 => 'MD LabName',                         // Name of the lab that scanned this file, as used in the Molecular Dynamics GEL file format.
				33449 => 'MD SampleInfo',                      // Information about the sample, as used in the Molecular Dynamics GEL file format.
				33450 => 'MD PrepDate',                        // Date the sample was prepared, as used in the Molecular Dynamics GEL file format.
				33451 => 'MD PrepTime',                        // Time the sample was prepared, as used in the Molecular Dynamics GEL file format.
				33452 => 'MD FileUnits',                       // Units for data in this file, as used in the Molecular Dynamics GEL file format.
				33550 => 'ModelPixelScaleTag',                 // Used in interchangeable GeoTIFF files.
				33723 => 'IPTC',                               // IPTC (International Press Telecommunications Council) metadata.
				33918 => 'INGR Packet Data Tag',               // Intergraph Application specific storage.
				33919 => 'INGR Flag Registers',                // Intergraph Application specific flags.
				33920 => 'IrasB Transformation Matrix',        // Originally part of Intergraph's GeoTIFF tags, but likely understood by IrasB only.
				33922 => 'ModelTiepointTag',                   // Originally part of Intergraph's GeoTIFF tags, but now used in interchangeable GeoTIFF files.
				34264 => 'ModelTransformationTag',             // Used in interchangeable GeoTIFF files.
				34377 => 'Photoshop',                          // Collection of Photoshop 'Image Resource Blocks'.
				34665 => 'Exif IFD',                           // A pointer to the Exif IFD.
				34675 => 'ICC Profile',                        // ICC profile data.
				34735 => 'GeoKeyDirectoryTag',                 // Used in interchangeable GeoTIFF files.
				34736 => 'GeoDoubleParamsTag',                 // Used in interchangeable GeoTIFF files.
				34737 => 'GeoAsciiParamsTag',                  // Used in interchangeable GeoTIFF files.
				34853 => 'GPS IFD',                            // A pointer to the Exif-related GPS Info IFD.
				34908 => 'HylaFAX FaxRecvParams',              // Used by HylaFAX.
				34909 => 'HylaFAX FaxSubAddress',              // Used by HylaFAX.
				34910 => 'HylaFAX FaxRecvTime',                // Used by HylaFAX.
				37724 => 'ImageSourceData',                    // Used by Adobe Photoshop.
				40965 => 'Interoperability IFD',               // A pointer to the Exif-related Interoperability IFD.
				42112 => 'GDAL_METADATA',                      // Used by the GDAL library, holds an XML list of name=value 'metadata' values about the image as a whole, and about specific samples.
				42113 => 'GDAL_NODATA',                        // Used by the GDAL library, contains an ASCII encoded nodata or background pixel value.
				50215 => 'Oce Scanjob Description',            // Used in the Oce scanning process.
				50216 => 'Oce Application Selector',           // Used in the Oce scanning process.
				50217 => 'Oce Identification Number',          // Used in the Oce scanning process.
				50218 => 'Oce ImageLogic Characteristics',     // Used in the Oce scanning process.
				50706 => 'DNGVersion',                         // Used in IFD 0 of DNG files.
				50707 => 'DNGBackwardVersion',                 // Used in IFD 0 of DNG files.
				50708 => 'UniqueCameraModel',                  // Used in IFD 0 of DNG files.
				50709 => 'LocalizedCameraModel',               // Used in IFD 0 of DNG files.
				50710 => 'CFAPlaneColor',                      // Used in Raw IFD of DNG files.
				50711 => 'CFALayout',                          // Used in Raw IFD of DNG files.
				50712 => 'LinearizationTable',                 // Used in Raw IFD of DNG files.
				50713 => 'BlackLevelRepeatDim',                // Used in Raw IFD of DNG files.
				50714 => 'BlackLevel',                         // Used in Raw IFD of DNG files.
				50715 => 'BlackLevelDeltaH',                   // Used in Raw IFD of DNG files.
				50716 => 'BlackLevelDeltaV',                   // Used in Raw IFD of DNG files.
				50717 => 'WhiteLevel',                         // Used in Raw IFD of DNG files.
				50718 => 'DefaultScale',                       // Used in Raw IFD of DNG files.
				50719 => 'DefaultCropOrigin',                  // Used in Raw IFD of DNG files.
				50720 => 'DefaultCropSize',                    // Used in Raw IFD of DNG files.
				50721 => 'ColorMatrix1',                       // Used in IFD 0 of DNG files.
				50722 => 'ColorMatrix2',                       // Used in IFD 0 of DNG files.
				50723 => 'CameraCalibration1',                 // Used in IFD 0 of DNG files.
				50724 => 'CameraCalibration2',                 // Used in IFD 0 of DNG files.
				50725 => 'ReductionMatrix1',                   // Used in IFD 0 of DNG files.
				50726 => 'ReductionMatrix2',                   // Used in IFD 0 of DNG files.
				50727 => 'AnalogBalance',                      // Used in IFD 0 of DNG files.
				50728 => 'AsShotNeutral',                      // Used in IFD 0 of DNG files.
				50729 => 'AsShotWhiteXY',                      // Used in IFD 0 of DNG files.
				50730 => 'BaselineExposure',                   // Used in IFD 0 of DNG files.
				50731 => 'BaselineNoise',                      // Used in IFD 0 of DNG files.
				50732 => 'BaselineSharpness',                  // Used in IFD 0 of DNG files.
				50733 => 'BayerGreenSplit',                    // Used in Raw IFD of DNG files.
				50734 => 'LinearResponseLimit',                // Used in IFD 0 of DNG files.
				50735 => 'CameraSerialNumber',                 // Used in IFD 0 of DNG files.
				50736 => 'LensInfo',                           // Used in IFD 0 of DNG files.
				50737 => 'ChromaBlurRadius',                   // Used in Raw IFD of DNG files.
				50738 => 'AntiAliasStrength',                  // Used in Raw IFD of DNG files.
				50740 => 'DNGPrivateData',                     // Used in IFD 0 of DNG files.
				50741 => 'MakerNoteSafety',                    // Used in IFD 0 of DNG files.
				50778 => 'CalibrationIlluminant1',             // Used in IFD 0 of DNG files.
				50779 => 'CalibrationIlluminant2',             // Used in IFD 0 of DNG files.
				50780 => 'BestQualityScale',                   // Used in Raw IFD of DNG files.
				50784 => 'Alias Layer Metadata',               // Alias Sketchbook Pro layer usage description.
				50908 => 'TIFF_RSID',                          // This private tag is used in a GEOTIFF standard by DGIWG.
				50909 => 'GEO_METADATA',                       // This private tag is used in a GEOTIFF standard by DGIWG.

				// EXIF tags - https://www.awaresystems.be/imaging/tiff/tifftags/privateifd/exif.html
				33434 => 'ExposureTime',                               // Exposure time, given in seconds.
				33437 => 'FNumber',                                    // The F number.
				34850 => 'ExposureProgram',                            // The class of the program used by the camera to set exposure when the picture is taken.
				34852 => 'SpectralSensitivity',                        // Indicates the spectral sensitivity of each channel of the camera used.
				34855 => 'ISOSpeedRatings',                            // Indicates the ISO Speed and ISO Latitude of the camera or input device as specified in ISO 12232.
				34856 => 'OECF',                                       // Indicates the Opto-Electric Conversion Function (OECF) specified in ISO 14524.
				36864 => 'ExifVersion',                                // The version of the supported Exif standard.
				36867 => 'DateTimeOriginal',                           // The date and time when the original image data was generated.
				36868 => 'DateTimeDigitized',                          // The date and time when the image was stored as digital data.
				37121 => 'ComponentsConfiguration',                    // Specific to compressed data; specifies the channels and complements PhotometricInterpretation
				37122 => 'CompressedBitsPerPixel',                     // Specific to compressed data; states the compressed bits per pixel.
				37377 => 'ShutterSpeedValue',                          // Shutter speed.
				37378 => 'ApertureValue',                              // The lens aperture.
				37379 => 'BrightnessValue',                            // The value of brightness.
				37380 => 'ExposureBiasValue',                          // The exposure bias.
				37381 => 'MaxApertureValue',                           // The smallest F number of the lens.
				37382 => 'SubjectDistance',                            // The distance to the subject, given in meters.
				37383 => 'MeteringMode',                               // The metering mode.
				37384 => 'LightSource',                                // The kind of light source.
				37385 => 'Flash',                                      // Indicates the status of flash when the image was shot.
				37386 => 'FocalLength',                                // The actual focal length of the lens, in mm.
				37396 => 'SubjectArea',                                // Indicates the location and area of the main subject in the overall scene.
				37500 => 'MakerNote',                                  // Manufacturer specific information.
				37510 => 'UserComment',                                // Keywords or comments on the image; complements ImageDescription.
				37520 => 'SubsecTime',                                 // A tag used to record fractions of seconds for the DateTime tag.
				37521 => 'SubsecTimeOriginal',                         // A tag used to record fractions of seconds for the DateTimeOriginal tag.
				37522 => 'SubsecTimeDigitized',                        // A tag used to record fractions of seconds for the DateTimeDigitized tag.
				40960 => 'FlashpixVersion',                            // The Flashpix format version supported by a FPXR file.
				40961 => 'ColorSpace',                                 // The color space information tag is always recorded as the color space specifier.
				40962 => 'PixelXDimension',                            // Specific to compressed data; the valid width of the meaningful image.
				40963 => 'PixelYDimension',                            // Specific to compressed data; the valid height of the meaningful image.
				40964 => 'RelatedSoundFile',                           // Used to record the name of an audio file related to the image data.
				41483 => 'FlashEnergy',                                // Indicates the strobe energy at the time the image is captured, as measured in Beam Candle Power Seconds
				41484 => 'SpatialFrequencyResponse',                   // Records the camera or input device spatial frequency table and SFR values in the direction of image width, image height, and diagonal direction, as specified in ISO 12233.
				41486 => 'FocalPlaneXResolution',                      // Indicates the number of pixels in the image width (X) direction per FocalPlaneResolutionUnit on the camera focal plane.
				41487 => 'FocalPlaneYResolution',                      // Indicates the number of pixels in the image height (Y) direction per FocalPlaneResolutionUnit on the camera focal plane.
				41488 => 'FocalPlaneResolutionUnit',                   // Indicates the unit for measuring FocalPlaneXResolution and FocalPlaneYResolution.
				41492 => 'SubjectLocation',                            // Indicates the location of the main subject in the scene.
				41493 => 'ExposureIndex',                              // Indicates the exposure index selected on the camera or input device at the time the image is captured.
				41495 => 'SensingMethod',                              // Indicates the image sensor type on the camera or input device.
				41728 => 'FileSource',                                 // Indicates the image source.
				41729 => 'SceneType',                                  // Indicates the type of scene.
				41730 => 'CFAPattern',                                 // Indicates the color filter array (CFA) geometric pattern of the image sensor when a one-chip color area sensor is used.
				41985 => 'CustomRendered',                             // Indicates the use of special processing on image data, such as rendering geared to output.
				41986 => 'ExposureMode',                               // Indicates the exposure mode set when the image was shot.
				41987 => 'WhiteBalance',                               // Indicates the white balance mode set when the image was shot.
				41988 => 'DigitalZoomRatio',                           // Indicates the digital zoom ratio when the image was shot.
				41989 => 'FocalLengthIn35mmFilm',                      // Indicates the equivalent focal length assuming a 35mm film camera, in mm.
				41990 => 'SceneCaptureType',                           // Indicates the type of scene that was shot.
				41991 => 'GainControl',                                // Indicates the degree of overall image gain adjustment.
				41992 => 'Contrast',                                   // Indicates the direction of contrast processing applied by the camera when the image was shot.
				41993 => 'Saturation',                                 // Indicates the direction of saturation processing applied by the camera when the image was shot.
				41994 => 'Sharpness',                                  // Indicates the direction of sharpness processing applied by the camera when the image was shot.
				41995 => 'DeviceSettingDescription',                   // This tag indicates information on the picture-taking conditions of a particular camera model.
				41996 => 'SubjectDistanceRange',                       // Indicates the distance to the subject.
				42016 => 'ImageUniqueID',                              // Indicates an identifier assigned uniquely to each image.
			);
		}
		return (isset($TIFFcommentName[$id]) ? $TIFFcommentName[$id] : 'unknown/invalid ('.$id.')');
	}


}
