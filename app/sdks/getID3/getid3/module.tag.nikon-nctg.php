<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//  see readme.txt for more details                            //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.tag.nikon-nctg.php                                   //
//                                                             //
/////////////////////////////////////////////////////////////////

/**
 * Module for analyzing Nikon NCTG metadata in MOV files
 *
 * @author Pavel Starosek <starosekpd@gmail.com>
 * @author Phil Harvey <philharvey66@gmail.com>
 *
 * @link https://exiftool.org/TagNames/Nikon.html#NCTG
 * @link https://github.com/exiftool/exiftool/blob/master/lib/Image/ExifTool/Nikon.pm
 * @link https://leo-van-stee.github.io/
 */
class getid3_tag_nikon_nctg
{
	const EXIF_TYPE_UINT8 = 0x0001;
	const EXIF_TYPE_CHAR = 0x0002;
	const EXIF_TYPE_UINT16 = 0x0003;
	const EXIF_TYPE_UINT32 = 0x0004;
	const EXIF_TYPE_URATIONAL = 0x0005;
	const EXIF_TYPE_INT8 = 0x0006;
	const EXIF_TYPE_RAW = 0x0007;
	const EXIF_TYPE_INT16 = 0x0008;
	const EXIF_TYPE_INT32 = 0x0009;
	const EXIF_TYPE_RATIONAL = 0x000A;

	protected static $exifTypeSizes = array(
		self::EXIF_TYPE_UINT8 => 1,
		self::EXIF_TYPE_CHAR => 1,
		self::EXIF_TYPE_UINT16 => 2,
		self::EXIF_TYPE_UINT32 => 4,
		self::EXIF_TYPE_URATIONAL => 8,
		self::EXIF_TYPE_INT8 => 1,
		self::EXIF_TYPE_RAW => 1,
		self::EXIF_TYPE_INT16 => 2,
		self::EXIF_TYPE_INT32 => 4,
		self::EXIF_TYPE_RATIONAL => 8,
	);

	protected static $exposurePrograms = array(
		0 => 'Not Defined',
		1 => 'Manual',
		2 => 'Program AE',
		3 => 'Aperture-priority AE',
		4 => 'Shutter speed priority AE',
		5 => 'Creative (Slow speed)',
		6 => 'Action (High speed)',
		7 => 'Portrait',
		8 => 'Landscape'
	);

	protected static $meteringModes = array(
		0 => 'Unknown',
		1 => 'Average',
		2 => 'Center-weighted average',
		3 => 'Spot',
		4 => 'Multi-spot',
		5 => 'Multi-segment',
		6 => 'Partial',
		255 => 'Other'
	);

	protected static $cropHiSpeeds = array(
		0  => 'Off',
		1  => '1.3x Crop',
		2  => 'DX Crop',
		3  => '5:4 Crop',
		4  => '3:2 Crop',
		6  => '16:9 Crop',
		8  => '2.7x Crop',
		9  => 'DX Movie Crop',
		10 => '1.3x Movie Crop',
		11 => 'FX Uncropped',
		12 => 'DX Uncropped',
		15 => '1.5x Movie Crop',
		17 => '1:1 Crop'
	);

	protected static $colorSpaces = array(
		1 => 'sRGB',
		2 => 'Adobe RGB'
	);

	protected static $vibrationReductions = array(
		1 => 'On',
		2 => 'Off'
	);

	protected static $VRModes = array(
		0 => 'Normal',
		1 => 'On (1)',
		2 => 'Active',
		3 => 'Sport'
	);

	protected static $activeDLightnings = array(
		0  => 'Off',
		1  => 'Low',
		3  => 'Normal',
		5  => 'High',
		7  => 'Extra High',
		8  => 'Extra High 1',
		9  => 'Extra High 2',
		10 => 'Extra High 3',
		11 => 'Extra High 4',
		65535 => 'Auto'
	);

	protected static $pictureControlDataAdjusts = array(
		0 => 'default',
		1 => 'quick',
		2 => 'full'
	);

	protected static $pictureControlDataFilterEffects = array(
		0x80 => 'off',
		0x81 => 'yellow',
		0x82 => 'orange',
		0x83 => 'red',
		0x84 => 'green',
		0xff => 'n/a'
	);

	protected static $pictureControlDataToningEffects = array(
		0x80 => 'b&w',
		0x81 => 'sepia',
		0x82 => 'cyanotype',
		0x83 => 'red',
		0x84 => 'yellow',
		0x85 => 'green',
		0x86 => 'blue-green',
		0x87 => 'blue',
		0x88 => 'purple-blue',
		0x89 => 'red-purple',
		0xff => 'n/a'
	);

	protected static $isoInfoExpansions = array(
		0x0000 => 'Off',
		0x0101 => 'Hi 0.3',
		0x0102 => 'Hi 0.5',
		0x0103 => 'Hi 0.7',
		0x0104 => 'Hi 1.0',
		0x0105 => 'Hi 1.3',
		0x0106 => 'Hi 1.5',
		0x0107 => 'Hi 1.7',
		0x0108 => 'Hi 2.0',
		0x0109 => 'Hi 2.3',
		0x010a => 'Hi 2.5',
		0x010b => 'Hi 2.7',
		0x010c => 'Hi 3.0',
		0x010d => 'Hi 3.3',
		0x010e => 'Hi 3.5',
		0x010f => 'Hi 3.7',
		0x0110 => 'Hi 4.0',
		0x0111 => 'Hi 4.3',
		0x0112 => 'Hi 4.5',
		0x0113 => 'Hi 4.7',
		0x0114 => 'Hi 5.0',
		0x0201 => 'Lo 0.3',
		0x0202 => 'Lo 0.5',
		0x0203 => 'Lo 0.7',
		0x0204 => 'Lo 1.0',
	);

	protected static $isoInfoExpansions2 = array(
		0x0000 => 'Off',
		0x0101 => 'Hi 0.3',
		0x0102 => 'Hi 0.5',
		0x0103 => 'Hi 0.7',
		0x0104 => 'Hi 1.0',
		0x0105 => 'Hi 1.3',
		0x0106 => 'Hi 1.5',
		0x0107 => 'Hi 1.7',
		0x0108 => 'Hi 2.0',
		0x0201 => 'Lo 0.3',
		0x0202 => 'Lo 0.5',
		0x0203 => 'Lo 0.7',
		0x0204 => 'Lo 1.0',
	);

	protected static $vignetteControls = array(
		0 => 'Off',
		1 => 'Low',
		3 => 'Normal',
		5 => 'High'
	);

	protected static $flashModes = array(
		0 => 'Did Not Fire',
		1 => 'Fired, Manual',
		3 => 'Not Ready',
		7 => 'Fired, External',
		8 => 'Fired, Commander Mode',
		9 => 'Fired, TTL Mode',
		18 => 'LED Light'
	);

	protected static $flashInfoSources = array(
		0 => 'None',
		1 => 'External',
		2 => 'Internal'
	);

	protected static $flashInfoExternalFlashFirmwares = array(
		'0 0' => 'n/a',
		'1 1' => '1.01 (SB-800 or Metz 58 AF-1)',
		'1 3' => '1.03 (SB-800)',
		'2 1' => '2.01 (SB-800)',
		'2 4' => '2.04 (SB-600)',
		'2 5' => '2.05 (SB-600)',
		'3 1' => '3.01 (SU-800 Remote Commander)',
		'4 1' => '4.01 (SB-400)',
		'4 2' => '4.02 (SB-400)',
		'4 4' => '4.04 (SB-400)',
		'5 1' => '5.01 (SB-900)',
		'5 2' => '5.02 (SB-900)',
		'6 1' => '6.01 (SB-700)',
		'7 1' => '7.01 (SB-910)',
	);

	protected static $flashInfoExternalFlashFlags = array(
		0 => 'Fired',
		2 => 'Bounce Flash',
		4 => 'Wide Flash Adapter',
		5 => 'Dome Diffuser',
	);

	protected static $flashInfoExternalFlashStatuses = array(
		0 => 'Flash Not Attached',
		1 => 'Flash Attached',
	);

	protected static $flashInfoExternalFlashReadyStates = array(
		0 => 'n/a',
		1 => 'Ready',
		6 => 'Not Ready',
	);

	protected static $flashInfoGNDistances = array(
		0 => 0,        19 => '2.8 m',
		1 => '0.1 m',  20 => '3.2 m',
		2 => '0.2 m',  21 => '3.6 m',
		3 => '0.3 m',  22 => '4.0 m',
		4 => '0.4 m',  23 => '4.5 m',
		5 => '0.5 m',  24 => '5.0 m',
		6 => '0.6 m',  25 => '5.6 m',
		7 => '0.7 m',  26 => '6.3 m',
		8 => '0.8 m',  27 => '7.1 m',
		9 => '0.9 m',  28 => '8.0 m',
		10 => '1.0 m',  29 => '9.0 m',
		11 => '1.1 m',  30 => '10.0 m',
		12 => '1.3 m',  31 => '11.0 m',
		13 => '1.4 m',  32 => '13.0 m',
		14 => '1.6 m',  33 => '14.0 m',
		15 => '1.8 m',  34 => '16.0 m',
		16 => '2.0 m',  35 => '18.0 m',
		17 => '2.2 m',  36 => '20.0 m',
		18 => '2.5 m',  255 => 'n/a'
	);

	protected static $flashInfoControlModes = array(
		0x00 => 'Off',
		0x01 => 'iTTL-BL',
		0x02 => 'iTTL',
		0x03 => 'Auto Aperture',
		0x04 => 'Automatic',
		0x05 => 'GN (distance priority)',
		0x06 => 'Manual',
		0x07 => 'Repeating Flash',
	);

	protected static $flashInfoColorFilters = array(
		0 => 'None',
		1 => 'FL-GL1 or SZ-2FL Fluorescent',
		2 => 'FL-GL2',
		9 => 'TN-A1 or SZ-2TN Incandescent',
		10 => 'TN-A2',
		65 => 'Red',
		66 => 'Blue',
		67 => 'Yellow',
		68 => 'Amber',
	);

	protected static $highISONoiseReductions = array(
		0 => 'Off',
		1 => 'Minimal',
		2 => 'Low',
		3 => 'Medium Low',
		4 => 'Normal',
		5 => 'Medium High',
		6 => 'High'
	);

	protected static $AFInfo2ContrastDetectAFChoices = array(
		0 => 'Off',
		1 => 'On',
		2 => 'On (2)'
	);

	protected static $AFInfo2AFAreaModesWithoutContrastDetectAF = array(
		0 => 'Single Area',
		1 => 'Dynamic Area',
		2 => 'Dynamic Area (closest subject)',
		3 => 'Group Dynamic',
		4 => 'Dynamic Area (9 points)',
		5 => 'Dynamic Area (21 points)',
		6 => 'Dynamic Area (51 points)',
		7 => 'Dynamic Area (51 points, 3D-tracking)',
		8 => 'Auto-area',
		9 => 'Dynamic Area (3D-tracking)',
		10 => 'Single Area (wide)',
		11 => 'Dynamic Area (wide)',
		12 => 'Dynamic Area (wide, 3D-tracking)',
		13 => 'Group Area',
		14 => 'Dynamic Area (25 points)',
		15 => 'Dynamic Area (72 points)',
		16 => 'Group Area (HL)',
		17 => 'Group Area (VL)',
		18 => 'Dynamic Area (49 points)',
		128 => 'Single',
		129 => 'Auto (41 points)',
		130 => 'Subject Tracking (41 points)',
		131 => 'Face Priority (41 points)',
		192 => 'Pinpoint',
		193 => 'Single',
		195 => 'Wide (S)',
		196 => 'Wide (L)',
		197 => 'Auto',
	);

	protected static $AFInfo2AFAreaModesWithContrastDetectAF = array(
		0 => 'Contrast-detect',
		1 => 'Contrast-detect (normal area)',
		2 => 'Contrast-detect (wide area)',
		3 => 'Contrast-detect (face priority)',
		4 => 'Contrast-detect (subject tracking)',
		128 => 'Single',
		129 => 'Auto (41 points)',
		130 => 'Subject Tracking (41 points)',
		131 => 'Face Priority (41 points)',
		192 => 'Pinpoint',
		193 => 'Single',
		194 => 'Dynamic',
		195 => 'Wide (S)',
		196 => 'Wide (L)',
		197 => 'Auto',
		198 => 'Auto (People)',
		199 => 'Auto (Animal)',
		200 => 'Normal-area AF',
		201 => 'Wide-area AF',
		202 => 'Face-priority AF',
		203 => 'Subject-tracking AF',
	);

	protected static $AFInfo2PhaseDetectAFChoices = array(
		0 => 'Off',
		1 => 'On (51-point)',
		2 => 'On (11-point)',
		3 => 'On (39-point)',
		4 => 'On (73-point)',
		5 => 'On (5)',
		6 => 'On (105-point)',
		7 => 'On (153-point)',
		8 => 'On (81-point)',
		9 => 'On (105-point)',
	);

	protected static $NikkorZLensIDS = array(
		1 => 'Nikkor Z 24-70mm f/4 S',
		2 => 'Nikkor Z 14-30mm f/4 S',
		4 => 'Nikkor Z 35mm f/1.8 S',
		8 => 'Nikkor Z 58mm f/0.95 S Noct',
		9 => 'Nikkor Z 50mm f/1.8 S',
		11 => 'Nikkor Z DX 16-50mm f/3.5-6.3 VR',
		12 => 'Nikkor Z DX 50-250mm f/4.5-6.3 VR',
		13 => 'Nikkor Z 24-70mm f/2.8 S',
		14 => 'Nikkor Z 85mm f/1.8 S',
		15 => 'Nikkor Z 24mm f/1.8 S',
		16 => 'Nikkor Z 70-200mm f/2.8 VR S',
		17 => 'Nikkor Z 20mm f/1.8 S',
		18 => 'Nikkor Z 24-200mm f/4-6.3 VR',
		21 => 'Nikkor Z 50mm f/1.2 S',
		22 => 'Nikkor Z 24-50mm f/4-6.3',
		23 => 'Nikkor Z 14-24mm f/2.8 S',
	);

	protected static $nikonTextEncodings = array(
		1 => 'UTF-8',
		2 => 'UTF-16'
	);

	/**
	 * Ref 4
	 *
	 * @var int[][]
	 */
	protected static $decodeTables = array(
		array(
			0xc1,0xbf,0x6d,0x0d,0x59,0xc5,0x13,0x9d,0x83,0x61,0x6b,0x4f,0xc7,0x7f,0x3d,0x3d,
			0x53,0x59,0xe3,0xc7,0xe9,0x2f,0x95,0xa7,0x95,0x1f,0xdf,0x7f,0x2b,0x29,0xc7,0x0d,
			0xdf,0x07,0xef,0x71,0x89,0x3d,0x13,0x3d,0x3b,0x13,0xfb,0x0d,0x89,0xc1,0x65,0x1f,
			0xb3,0x0d,0x6b,0x29,0xe3,0xfb,0xef,0xa3,0x6b,0x47,0x7f,0x95,0x35,0xa7,0x47,0x4f,
			0xc7,0xf1,0x59,0x95,0x35,0x11,0x29,0x61,0xf1,0x3d,0xb3,0x2b,0x0d,0x43,0x89,0xc1,
			0x9d,0x9d,0x89,0x65,0xf1,0xe9,0xdf,0xbf,0x3d,0x7f,0x53,0x97,0xe5,0xe9,0x95,0x17,
			0x1d,0x3d,0x8b,0xfb,0xc7,0xe3,0x67,0xa7,0x07,0xf1,0x71,0xa7,0x53,0xb5,0x29,0x89,
			0xe5,0x2b,0xa7,0x17,0x29,0xe9,0x4f,0xc5,0x65,0x6d,0x6b,0xef,0x0d,0x89,0x49,0x2f,
			0xb3,0x43,0x53,0x65,0x1d,0x49,0xa3,0x13,0x89,0x59,0xef,0x6b,0xef,0x65,0x1d,0x0b,
			0x59,0x13,0xe3,0x4f,0x9d,0xb3,0x29,0x43,0x2b,0x07,0x1d,0x95,0x59,0x59,0x47,0xfb,
			0xe5,0xe9,0x61,0x47,0x2f,0x35,0x7f,0x17,0x7f,0xef,0x7f,0x95,0x95,0x71,0xd3,0xa3,
			0x0b,0x71,0xa3,0xad,0x0b,0x3b,0xb5,0xfb,0xa3,0xbf,0x4f,0x83,0x1d,0xad,0xe9,0x2f,
			0x71,0x65,0xa3,0xe5,0x07,0x35,0x3d,0x0d,0xb5,0xe9,0xe5,0x47,0x3b,0x9d,0xef,0x35,
			0xa3,0xbf,0xb3,0xdf,0x53,0xd3,0x97,0x53,0x49,0x71,0x07,0x35,0x61,0x71,0x2f,0x43,
			0x2f,0x11,0xdf,0x17,0x97,0xfb,0x95,0x3b,0x7f,0x6b,0xd3,0x25,0xbf,0xad,0xc7,0xc5,
			0xc5,0xb5,0x8b,0xef,0x2f,0xd3,0x07,0x6b,0x25,0x49,0x95,0x25,0x49,0x6d,0x71,0xc7
		),
		array(
			0xa7,0xbc,0xc9,0xad,0x91,0xdf,0x85,0xe5,0xd4,0x78,0xd5,0x17,0x46,0x7c,0x29,0x4c,
			0x4d,0x03,0xe9,0x25,0x68,0x11,0x86,0xb3,0xbd,0xf7,0x6f,0x61,0x22,0xa2,0x26,0x34,
			0x2a,0xbe,0x1e,0x46,0x14,0x68,0x9d,0x44,0x18,0xc2,0x40,0xf4,0x7e,0x5f,0x1b,0xad,
			0x0b,0x94,0xb6,0x67,0xb4,0x0b,0xe1,0xea,0x95,0x9c,0x66,0xdc,0xe7,0x5d,0x6c,0x05,
			0xda,0xd5,0xdf,0x7a,0xef,0xf6,0xdb,0x1f,0x82,0x4c,0xc0,0x68,0x47,0xa1,0xbd,0xee,
			0x39,0x50,0x56,0x4a,0xdd,0xdf,0xa5,0xf8,0xc6,0xda,0xca,0x90,0xca,0x01,0x42,0x9d,
			0x8b,0x0c,0x73,0x43,0x75,0x05,0x94,0xde,0x24,0xb3,0x80,0x34,0xe5,0x2c,0xdc,0x9b,
			0x3f,0xca,0x33,0x45,0xd0,0xdb,0x5f,0xf5,0x52,0xc3,0x21,0xda,0xe2,0x22,0x72,0x6b,
			0x3e,0xd0,0x5b,0xa8,0x87,0x8c,0x06,0x5d,0x0f,0xdd,0x09,0x19,0x93,0xd0,0xb9,0xfc,
			0x8b,0x0f,0x84,0x60,0x33,0x1c,0x9b,0x45,0xf1,0xf0,0xa3,0x94,0x3a,0x12,0x77,0x33,
			0x4d,0x44,0x78,0x28,0x3c,0x9e,0xfd,0x65,0x57,0x16,0x94,0x6b,0xfb,0x59,0xd0,0xc8,
			0x22,0x36,0xdb,0xd2,0x63,0x98,0x43,0xa1,0x04,0x87,0x86,0xf7,0xa6,0x26,0xbb,0xd6,
			0x59,0x4d,0xbf,0x6a,0x2e,0xaa,0x2b,0xef,0xe6,0x78,0xb6,0x4e,0xe0,0x2f,0xdc,0x7c,
			0xbe,0x57,0x19,0x32,0x7e,0x2a,0xd0,0xb8,0xba,0x29,0x00,0x3c,0x52,0x7d,0xa8,0x49,
			0x3b,0x2d,0xeb,0x25,0x49,0xfa,0xa3,0xaa,0x39,0xa7,0xc5,0xa7,0x50,0x11,0x36,0xfb,
			0xc6,0x67,0x4a,0xf5,0xa5,0x12,0x65,0x7e,0xb0,0xdf,0xaf,0x4e,0xb3,0x61,0x7f,0x2f
		)
	);

	/**
	 * @var getID3
	 */
	private $getid3;

	public function __construct(getID3 $getid3)
	{
		$this->getid3 = $getid3;
	}

	/**
	 * Get a copy of all NCTG tags extracted from the video
	 *
	 * @param string $atomData
	 *
	 * @return array<string, mixed>
	 */
	public function parse($atomData) {
		// Nikon-specific QuickTime tags found in the NCDT atom of MOV videos from some Nikon cameras such as the Coolpix S8000 and D5100
		// Data is stored as records of:
		// * 4 bytes record type
		// * 2 bytes size of data field type:
		//     0x0001 = flag / unsigned byte      (size field *= 1-byte)
		//     0x0002 = char / ascii strings      (size field *= 1-byte)
		//     0x0003 = DWORD+ / unsigned short   (size field *= 2-byte), values are stored CDAB
		//     0x0004 = QWORD+ / unsigned long    (size field *= 4-byte), values are stored EFGHABCD
		//     0x0005 = float / unsigned rational (size field *= 8-byte), values are stored aaaabbbb where value is aaaa/bbbb; possibly multiple sets of values appended together
		//     0x0006 = signed byte               (size field *= 1-byte)
		//     0x0007 = raw bytes                 (size field *= 1-byte)
		//     0x0008 = signed short              (size field *= 2-byte), values are stored as CDAB
		//     0x0009 = signed long               (size field *= 4-byte), values are stored as EFGHABCD
		//     0x000A = float / signed rational   (size field *= 8-byte), values are stored aaaabbbb where value is aaaa/bbbb; possibly multiple sets of values appended together
		// * 2 bytes data size field
		// * ? bytes data (string data may be null-padded; datestamp fields are in the format "2011:05:25 20:24:15")
		// all integers are stored BigEndian

		$NCTGtagName = array(
			0x00000001 => 'Make',
			0x00000002 => 'Model',
			0x00000003 => 'Software',
			0x00000011 => 'CreateDate',
			0x00000012 => 'DateTimeOriginal',
			0x00000013 => 'FrameCount',
			0x00000016 => 'FrameRate',
			0x00000019 => 'TimeZone',
			0x00000022 => 'FrameWidth',
			0x00000023 => 'FrameHeight',
			0x00000032 => 'AudioChannels',
			0x00000033 => 'AudioBitsPerSample',
			0x00000034 => 'AudioSampleRate',
			0x00001002 => 'NikonDateTime',
			0x00001013 => 'ElectronicVR',
			0x0110829a => 'ExposureTime',
			0x0110829d => 'FNumber',
			0x01108822 => 'ExposureProgram',
			0x01109204 => 'ExposureCompensation',
			0x01109207 => 'MeteringMode',
			0x0110920a => 'FocalLength', // mm
			0x0110a431 => 'SerialNumber',
			0x0110a432 => 'LensInfo',
			0x0110a433 => 'LensMake',
			0x0110a434 => 'LensModel',
			0x0110a435 => 'LensSerialNumber',
			0x01200000 => 'GPSVersionID',
			0x01200001 => 'GPSLatitudeRef',
			0x01200002 => 'GPSLatitude',
			0x01200003 => 'GPSLongitudeRef',
			0x01200004 => 'GPSLongitude',
			0x01200005 => 'GPSAltitudeRef', // 0 = Above Sea Level, 1 = Below Sea Level
			0x01200006 => 'GPSAltitude',
			0x01200007 => 'GPSTimeStamp',
			0x01200008 => 'GPSSatellites',
			0x01200010 => 'GPSImgDirectionRef', // M = Magnetic North, T = True North
			0x01200011 => 'GPSImgDirection',
			0x01200012 => 'GPSMapDatum',
			0x0120001d => 'GPSDateStamp',
			0x02000001 => 'MakerNoteVersion',
			0x02000005 => 'WhiteBalance',
			0x02000007 => 'FocusMode',
			0x0200000b => 'WhiteBalanceFineTune',
			0x0200001b => 'CropHiSpeed',
			0x0200001e => 'ColorSpace',
			0x0200001f => 'VRInfo',
			0x02000022 => 'ActiveDLighting',
			0x02000023 => 'PictureControlData',
			0x02000024 => 'WorldTime',
			0x02000025 => 'ISOInfo',
			0x0200002a => 'VignetteControl',
			0x0200002c => 'UnknownInfo',
			0x02000032 => 'UnknownInfo2',
			0x02000039 => 'LocationInfo',
			0x02000083 => 'LensType',
			0x02000084 => 'Lens',
			0x02000087 => 'FlashMode',
			0x02000098 => 'LensData',
			0x020000a7 => 'ShutterCount',
			0x020000a8 => 'FlashInfo',
			0x020000ab => 'VariProgram',
			0x020000b1 => 'HighISONoiseReduction',
			0x020000b7 => 'AFInfo2',
			0x020000c3 => 'BarometerInfo',
		);

		$firstPassNeededTags = array(
			0x00000002, // Model
			0x0110a431, // SerialNumber
			0x020000a7, // ShutterCount
		);

		$datalength = strlen($atomData);
		$parsed = array();
		$model = $serialNumber = $shutterCount = null;
		for ($pass = 0; $pass < 2; ++$pass) {
			$offset = 0;
			$parsed = array();
			$data = null;
			while ($offset < $datalength) {
				$record_type = getid3_lib::BigEndian2Int(substr($atomData, $offset, 4));
				$offset += 4;
				$data_size_type = getid3_lib::BigEndian2Int(substr($atomData, $offset, 2));
				$data_size = static::$exifTypeSizes[$data_size_type];
				$offset += 2;
				$data_count = getid3_lib::BigEndian2Int(substr($atomData, $offset, 2));
				$offset += 2;
				$data = array();

				if ($pass === 0 && !in_array($record_type, $firstPassNeededTags, true)) {
					$offset += $data_count * $data_size;
					continue;
				}

				switch ($data_size_type) {
					case self::EXIF_TYPE_UINT8: // 0x0001 = flag / unsigned byte   (size field *= 1-byte)
						for ($i = 0; $i < $data_count; ++$i) {
							$data[] = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size), $data_size));
						}
						$offset += ($data_count * $data_size);
						break;
					case self::EXIF_TYPE_CHAR: // 0x0002 = char / ascii strings  (size field *= 1-byte)
						$data = substr($atomData, $offset, $data_count * $data_size);
						$offset += ($data_count * $data_size);
						$data = rtrim($data, "\x00");
						break;
					case self::EXIF_TYPE_UINT16: // 0x0003 = DWORD+ / unsigned short (size field *= 2-byte), values are stored CDAB
						for ($i = 0; $i < $data_count; ++$i) {
							$data[] = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size), $data_size));
						}
						$offset += ($data_count * $data_size);
						break;
					case self::EXIF_TYPE_UINT32: // 0x0004 = QWORD+ / unsigned long (size field *= 4-byte), values are stored EFGHABCD
						// нужно проверить FrameCount
						for ($i = 0; $i < $data_count; ++$i) {
							$data[] = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size), $data_size));
						}
						$offset += ($data_count * $data_size);
						break;
					case self::EXIF_TYPE_URATIONAL: // 0x0005 = float / unsigned rational (size field *= 8-byte), values are stored aaaabbbb where value is aaaa/bbbb; possibly multiple sets of values appended together
						for ($i = 0; $i < $data_count; ++$i) {
							$numerator = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size) + 0, 4));
							$denomninator = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size) + 4, 4));
							if ($denomninator == 0) {
								$data[] = false;
							} else {
								$data[] = (float)$numerator / $denomninator;
							}
						}
						$offset += ($data_size * $data_count);
						break;
					case self::EXIF_TYPE_INT8: // 0x0006 = bytes / signed byte  (size field *= 1-byte)
						// NOT TESTED
						for ($i = 0; $i < $data_count; ++$i) {
							$data[] = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size), $data_size), false, true);
						}
						$offset += ($data_count * $data_size);
						break;
					case self::EXIF_TYPE_RAW: // 0x0007 = raw bytes  (size field *= 1-byte)
						$data = substr($atomData, $offset, $data_count * $data_size);
						$offset += ($data_count * $data_size);
						break;
					case self::EXIF_TYPE_INT16: // 0x0008 = signed short (size field *= 2-byte), values are stored as CDAB
						for ($i = 0; $i < $data_count; ++$i) {
							$value = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size), $data_size));
							if ($value >= 0x8000) {
								$value -= 0x10000;
							}
							$data[] = $value;
						}
						$offset += ($data_count * $data_size);
						break;
					case self::EXIF_TYPE_INT32: // 0x0009 = signed long (size field *= 4-byte), values are stored as EFGHABCD
						// NOT TESTED
						for ($i = 0; $i < $data_count; ++$i) {
							$data = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size), $data_size), false, true);
						}
						$offset += ($data_count * $data_size);
						break;
					case self::EXIF_TYPE_RATIONAL: // 0x000A = float / signed rational (size field *= 8-byte), values are stored aaaabbbb where value is aaaa/bbbb; possibly multiple sets of values appended together
						// NOT TESTED
						for ($i = 0; $i < $data_count; ++$i) {
							$numerator = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size) + 0, 4), false, true);
							$denomninator = getid3_lib::BigEndian2Int(substr($atomData, $offset + ($i * $data_size) + 4, 4), false, true);
							if ($denomninator == 0) {
								$data[] = false;
							} else {
								$data[] = (float)$numerator / $denomninator;
							}
						}
						$offset += ($data_size * $data_count);
						if (count($data) == 1) {
							$data = $data[0];
						}
						break;
					default:
						$this->getid3->warning('QuicktimeParseNikonNCTG()::unknown $data_size_type: ' . $data_size_type);
						break 2;
				}

				if (is_array($data) && count($data) === 1) {
					$data = $data[0];
				}

				switch ($record_type) {
					case 0x00000002:
						$model = $data;
						break;
					case 0x00000013: // FrameCount
						if (is_array($data) && count($data) === 2 && $data[1] == 0) {
							$data = $data[0];
						}
						break;
					case 0x00000011: // CreateDate
					case 0x00000012: // DateTimeOriginal
					case 0x00001002: // NikonDateTime
						$data = strtotime($data);
						break;
					case 0x00001013: // ElectronicVR
						$data = (bool) $data;
						break;
					case 0x0110829a: // ExposureTime
						// Print exposure time as a fraction
						/** @var float $data */
						if ($data < 0.25001 && $data > 0) {
							$data = sprintf("1/%d", intval(0.5 + 1 / $data));
						}
						break;
					case 0x01109204: // ExposureCompensation
						$data = $this->printFraction($data);
						break;
					case 0x01108822: // ExposureProgram
						$data = isset(static::$exposurePrograms[$data]) ? static::$exposurePrograms[$data] : $data;
						break;
					case 0x01109207: // MeteringMode
						$data = isset(static::$meteringModes[$data]) ? static::$meteringModes[$data] : $data;
						break;
					case 0x0110a431: // SerialNumber
						$serialNumber = $this->serialKey($data, $model);
						break;
					case 0x01200000: // GPSVersionID
						$parsed['GPS']['computed']['version'] = 'v'.implode('.', $data);
						break;
					case 0x01200002: // GPSLatitude
						if (is_array($data)) {
							$direction_multiplier = ((isset($parsed['GPSLatitudeRef']) && ($parsed['GPSLatitudeRef'] === 'S')) ? -1 : 1);
							$parsed['GPS']['computed']['latitude'] = $direction_multiplier * ($data[0] + ($data[1] / 60) + ($data[2] / 3600));
						}
						break;
					case 0x01200004: // GPSLongitude
						if (is_array($data)) {
							$direction_multiplier = ((isset($parsed['GPSLongitudeRef']) && ($parsed['GPSLongitudeRef'] === 'W')) ? -1 : 1);
							$parsed['GPS']['computed']['longitude'] = $direction_multiplier * ($data[0] + ($data[1] / 60) + ($data[2] / 3600));
						}
						break;
					case 0x01200006:  // GPSAltitude
						if (isset($parsed['GPSAltitudeRef'])) {
							$direction_multiplier = (!empty($parsed['GPSAltitudeRef']) ? -1 : 1); // 0 = above sea level; 1 = below sea level
							$parsed['GPS']['computed']['altitude'] = $direction_multiplier * $data;
						}
						break;
					case 0x0120001d: // GPSDateStamp
						if (isset($parsed['GPSTimeStamp']) && is_array($parsed['GPSTimeStamp']) && $data !== '') {
							$explodedDate = explode(':', $data);
							$parsed['GPS']['computed']['timestamp'] = gmmktime($parsed['GPSTimeStamp'][0], $parsed['GPSTimeStamp'][1], $parsed['GPSTimeStamp'][2], $explodedDate[1], $explodedDate[2], $explodedDate[0]);
						}
						break;
					case 0x02000001: // MakerNoteVersion
						$data = ltrim(substr($data, 0, 2) . '.' . substr($data, 2, 2), '0');
						break;
					case 0x0200001b: // CropHiSpeed
						if (is_array($data) && count($data) === 7) {
							$name = isset(static::$cropHiSpeeds[$data[0]]) ? static::$cropHiSpeeds[$data[0]] : sprintf('Unknown (%d)', $data[0]);
							$data = array(
								'Name' => $name,
								'OriginalWidth' => $data[1],
								'OriginalHeight' => $data[2],
								'CroppedWidth' => $data[3],
								'CroppedHeight' => $data[4],
								'PixelXPosition' => $data[5],
								'PixelYPosition' => $data[6],
							);
						}
						break;
					case 0x0200001e: // ColorSpace
						$data = isset(static::$colorSpaces[$data]) ? static::$colorSpaces[$data] : $data;
						break;
					case 0x0200001f: // VRInfo
						$data = array(
							'VRInfoVersion' => substr($data, 0, 4),
							'VibrationReduction' => isset(static::$vibrationReductions[ord(substr($data, 4, 1))])
								? static::$vibrationReductions[ord(substr($data, 4, 1))]
								: null,
							'VRMode' => static::$VRModes[ord(substr($data, 6, 1))],
						);
						break;
					case 0x02000022: // ActiveDLighting
						$data = isset(static::$activeDLightnings[$data]) ? static::$activeDLightnings[$data] : $data;
						break;
					case 0x02000023: // PictureControlData
						switch (substr($data, 0, 2)) {
							case '01':
								$data = array(
									'PictureControlVersion' => substr($data, 0, 4),
									'PictureControlName' => rtrim(substr($data, 4, 20), "\x00"),
									'PictureControlBase' => rtrim(substr($data, 24, 20), "\x00"),
									//'?'                       =>                            substr($data, 44,  4),
									'PictureControlAdjust' => static::$pictureControlDataAdjusts[ord(substr($data, 48, 1))],
									'PictureControlQuickAdjust' => $this->printPC(ord(substr($data, 49, 1)) - 0x80),
									'Sharpness' => $this->printPC(ord(substr($data, 50, 1)) - 0x80, 'No Sharpening', '%d'),
									'Contrast' => $this->printPC(ord(substr($data, 51, 1)) - 0x80),
									'Brightness' => $this->printPC(ord(substr($data, 52, 1)) - 0x80),
									'Saturation' => $this->printPC(ord(substr($data, 53, 1)) - 0x80),
									'HueAdjustment' => $this->printPC(ord(substr($data, 54, 1)) - 0x80, 'None'),
									'FilterEffect' => static::$pictureControlDataFilterEffects[ord(substr($data, 55, 1))],
									'ToningEffect' => static::$pictureControlDataToningEffects[ord(substr($data, 56, 1))],
									'ToningSaturation' => $this->printPC(ord(substr($data, 57, 1)) - 0x80),
								);
								break;
							case '02':
								$data = array(
									'PictureControlVersion' => substr($data, 0, 4),
									'PictureControlName' => rtrim(substr($data, 4, 20), "\x00"),
									'PictureControlBase' => rtrim(substr($data, 24, 20), "\x00"),
									//'?'                       =>                            substr($data, 44,  4),
									'PictureControlAdjust' => static::$pictureControlDataAdjusts[ord(substr($data, 48, 1))],
									'PictureControlQuickAdjust' => $this->printPC(ord(substr($data, 49, 1)) - 0x80),
									'Sharpness' => $this->printPC(ord(substr($data, 51, 1)) - 0x80, 'None', '%.2f', 4),
									'Clarity' => $this->printPC(ord(substr($data, 53, 1)) - 0x80, 'None', '%.2f', 4),
									'Contrast' => $this->printPC(ord(substr($data, 55, 1)) - 0x80, 'None', '%.2f', 4),
									'Brightness' => $this->printPC(ord(substr($data, 57, 1)) - 0x80, 'Normal', '%.2f', 4),
									'Saturation' => $this->printPC(ord(substr($data, 59, 1)) - 0x80, 'None', '%.2f', 4),
									'Hue' => $this->printPC(ord(substr($data, 61, 1)) - 0x80, 'None', '%.2f', 4),
									'FilterEffect' => static::$pictureControlDataFilterEffects[ord(substr($data, 63, 1))],
									'ToningEffect' => static::$pictureControlDataToningEffects[ord(substr($data, 64, 1))],
									'ToningSaturation' => $this->printPC(ord(substr($data, 65, 1)) - 0x80, 'None', '%.2f', 4),
								);
								break;
							case '03':
								$data = array(
									'PictureControlVersion' => substr($data, 0, 4),
									'PictureControlName' => rtrim(substr($data, 8, 20), "\x00"),
									'PictureControlBase' => rtrim(substr($data, 28, 20), "\x00"),
									'PictureControlAdjust' => static::$pictureControlDataAdjusts[ord(substr($data, 54, 1))],
									'PictureControlQuickAdjust' => $this->printPC(ord(substr($data, 55, 1)) - 0x80),
									'Sharpness' => $this->printPC(ord(substr($data, 57, 1)) - 0x80, 'None', '%.2f', 4),
									'MidRangeSharpness' => $this->printPC(ord(substr($data, 59, 1)) - 0x80, 'None', '%.2f', 4),
									'Clarity' => $this->printPC(ord(substr($data, 61, 1)) - 0x80, 'None', '%.2f', 4),
									'Contrast' => $this->printPC(ord(substr($data, 63, 1)) - 0x80, 'None', '%.2f', 4),
									'Brightness' => $this->printPC(ord(substr($data, 65, 1)) - 0x80, 'Normal', '%.2f', 4),
									'Saturation' => $this->printPC(ord(substr($data, 67, 1)) - 0x80, 'None', '%.2f', 4),
									'Hue' => $this->printPC(ord(substr($data, 69, 1)) - 0x80, 'None', '%.2f', 4),
									'FilterEffect' => static::$pictureControlDataFilterEffects[ord(substr($data, 71, 1))],
									'ToningEffect' => static::$pictureControlDataToningEffects[ord(substr($data, 72, 1))],
									'ToningSaturation' => $this->printPC(ord(substr($data, 73, 1)) - 0x80, 'None', '%.2f', 4),
								);
								break;
							default:
								$data = array(
									'PictureControlVersion' => substr($data, 0, 4),
								);
								break;
						}
						break;
					case 0x02000024: // WorldTime
						// https://exiftool.org/TagNames/Nikon.html#WorldTime
						// timezone is stored as offset from GMT in minutes
						$timezone = getid3_lib::BigEndian2Int(substr($data, 0, 2));
						if ($timezone & 0x8000) {
							$timezone = 0 - (0x10000 - $timezone);
						}
						$hours = (int)abs($timezone / 60);
						$minutes = abs($timezone) - $hours * 60;

						$dst = (bool)getid3_lib::BigEndian2Int(substr($data, 2, 1));
						switch (getid3_lib::BigEndian2Int(substr($data, 3, 1))) {
							case 2:
								$datedisplayformat = 'D/M/Y';
								break;
							case 1:
								$datedisplayformat = 'M/D/Y';
								break;
							case 0:
							default:
								$datedisplayformat = 'Y/M/D';
								break;
						}

						$data = array(
							'timezone' => sprintf('%s%02d:%02d', $timezone >= 0 ? '+' : '-', $hours, $minutes),
							'dst' => $dst,
							'display' => $datedisplayformat
						);
						break;
					case 0x02000025: // ISOInfo
						$data = array(
							'ISO' => (int)ceil(100 * pow(2, ord(substr($data, 0, 1)) / 12 - 5)),
							'ISOExpansion' => static::$isoInfoExpansions[getid3_lib::BigEndian2Int(substr($data, 4, 2))],
							'ISO2' => (int)ceil(100 * pow(2, ord(substr($data, 6, 1)) / 12 - 5)),
							'ISOExpansion2' => static::$isoInfoExpansions2[getid3_lib::BigEndian2Int(substr($data, 10, 2))]
						);
						break;
					case 0x0200002a: // VignetteControl
						$data = isset(static::$vignetteControls[$data]) ? static::$vignetteControls[$data] : $data;
						break;
					case 0x0200002c: // UnknownInfo
						$data = array(
							'UnknownInfoVersion' => substr($data, 0, 4),
						);
						break;
					case 0x02000032: // UnknownInfo2
						$data = array(
							'UnknownInfo2Version' => substr($data, 0, 4),
						);
						break;
					case 0x02000039: // LocationInfo
						$encoding = isset(static::$nikonTextEncodings[ord(substr($data, 4, 1))])
							? static::$nikonTextEncodings[ord(substr($data, 4, 1))]
							: null;
						$data = array(
							'LocationInfoVersion' => substr($data, 0, 4),
							'TextEncoding' => $encoding,
							'CountryCode' => trim(substr($data, 5, 3), "\x00"),
							'POILevel' => ord(substr($data, 8, 1)),
							'Location' => getid3_lib::iconv_fallback($encoding, $this->getid3->info['encoding'], substr($data, 9, 70)),
						);
						break;
					case 0x02000083: // LensType
						if ($data) {
							$decodedBits = array(
								'1' => (bool) (($data >> 4) & 1),
								'MF' => (bool) (($data >> 0) & 1),
								'D' => (bool) (($data >> 1) & 1),
								'E' => (bool) (($data >> 6) & 1),
								'G' => (bool) (($data >> 2) & 1),
								'VR' => (bool) (($data >> 3) & 1),
								'[7]' => (bool) (($data >> 7) & 1), // AF-P?
								'[8]' => (bool) (($data >> 5) & 1) // FT-1?
							);
							if ($decodedBits['D'] === true && $decodedBits['G'] === true) {
								$decodedBits['D'] = false;
							}
						} else {
							$decodedBits = array('AF' => true);
						}
						$data = $decodedBits;
						break;
					case 0x0110a432: // LensInfo
					case 0x02000084: // Lens
						if (count($data) !== 4) {
							break;
						}

						$value = $data[0];
						if ($data[1] && $data[1] !== $data[0]) {
							$value .= '-' . $data[1];
						}
						$value .= 'mm f/' . $data[2];
						if ($data[3] && $data[3] !== $data[2]) {
							$value .= '-' . $data[3];
						}
						$data = $value;
						break;
					case 0x02000087: // FlashMode
						$data = isset(static::$flashModes[$data]) ? static::$flashModes[$data] : $data;
						break;
					case 0x02000098: // LensData
						$version = substr($data, 0, 4);

						switch ($version) {
							case '0100':
								$data = array(
									'LensDataVersion'       => $version,
									'LensIDNumber'          => ord(substr($data, 6, 1)),
									'LensFStops'            => ord(substr($data, 7, 1)) / 12,
									'MinFocalLength'        => 5 * pow(2, ord(substr($data, 8, 1)) / 24), // mm
									'MaxFocalLength'        => 5 * pow(2, ord(substr($data, 9, 1)) / 24), // mm
									'MaxApertureAtMinFocal' => pow(2, ord(substr($data, 10, 1)) / 24),
									'MaxApertureAtMaxFocal' => pow(2, ord(substr($data, 11, 1)) / 24),
									'MCUVersion'            => ord(substr($data, 12, 1)),
								);
								break;
							case '0101':
							case '0201':
							case '0202':
							case '0203':
								$isEncrypted = $version !== '0101';
								if ($isEncrypted) {
									$data = $this->decryptLensInfo($data, $serialNumber, $shutterCount, 4);
								}

								$data = array(
									'LensDataVersion' => $version,
									'ExitPupilPosition' => ord(substr($data, 4, 1)) > 0 ? 2048 / ord(substr($data, 4, 1)) : 0, // mm
									'AFAperture' => pow(2, ord(substr($data, 5, 1)) / 24),
									'FocusPosition' => '0x' . str_pad(strtoupper(dechex(ord(substr($data, 8, 1)))), 2, '0', STR_PAD_LEFT),
									'FocusDistance' => 0.01 * pow(10, ord(substr($data, 9, 1)) / 40), // m
									'FocalLength' => 5 * pow(2, ord(substr($data, 10, 1)) / 24), // mm
									'LensIDNumber' => ord(substr($data, 11, 1)),
									'LensFStops' => ord(substr($data, 12, 1)) / 12,
									'MinFocalLength' => 5 * pow(2, ord(substr($data, 13, 1)) / 24), // mm
									'MaxFocalLength' => 5 * pow(2, ord(substr($data, 14, 1)) / 24), // mm
									'MaxApertureAtMinFocal' => pow(2, ord(substr($data, 15, 1)) / 24),
									'MaxApertureAtMaxFocal' => pow(2, ord(substr($data, 16, 1)) / 24),
									'MCUVersion' => ord(substr($data, 17, 1)),
									'EffectiveMaxAperture' => pow(2, ord(substr($data, 18, 1)) / 24),
								);
								break;
							case '0204':
								$data = $this->decryptLensInfo($data, $serialNumber, $shutterCount, 4);

								$data = array(
									'LensDataVersion' => $version,
									'ExitPupilPosition' => ord(substr($data, 4, 1)) > 0 ? 2048 / ord(substr($data, 4, 1)) : 0, // mm
									'AFAperture' => pow(2, ord(substr($data, 5, 1)) / 24),
									'FocusPosition' => '0x' . str_pad(strtoupper(dechex(ord(substr($data, 8, 1)))), 2, '0', STR_PAD_LEFT),
									'FocusDistance' => 0.01 * pow(10, ord(substr($data, 10, 1)) / 40), // m
									'FocalLength' => 5 * pow(2, ord(substr($data, 11, 1)) / 24), // mm
									'LensIDNumber' => ord(substr($data, 12, 1)),
									'LensFStops' => ord(substr($data, 13, 1)) / 12,
									'MinFocalLength' => 5 * pow(2, ord(substr($data, 14, 1)) / 24), // mm
									'MaxFocalLength' => 5 * pow(2, ord(substr($data, 15, 1)) / 24), // mm
									'MaxApertureAtMinFocal' => pow(2, ord(substr($data, 16, 1)) / 24),
									'MaxApertureAtMaxFocal' => pow(2, ord(substr($data, 17, 1)) / 24),
									'MCUVersion' => ord(substr($data, 18, 1)),
									'EffectiveMaxAperture' => pow(2, ord(substr($data, 19, 1)) / 24),
								);
								break;
							case '0400':
							case '0401':
								$data = $this->decryptLensInfo($data, $serialNumber, $shutterCount, 4);

								$data = array(
									'LensDataVersion' => $version,
									'LensModel' => substr($data, 394, 64),
								);
								break;
							case '0402':
								$data = $this->decryptLensInfo($data, $serialNumber, $shutterCount, 4);

								$data = array(
									'LensDataVersion' => $version,
									'LensModel' => substr($data, 395, 64),
								);
								break;
							case '0403':
								$data = $this->decryptLensInfo($data, $serialNumber, $shutterCount, 4);

								$data = array(
									'LensDataVersion' => $version,
									'LensModel' => substr($data, 684, 64),
								);
								break;
							case '0800':
							case '0801':
								$data = $this->decryptLensInfo($data, $serialNumber, $shutterCount, 4);

								$newData = array(
									'LensDataVersion' => $version,
								);

								if (!preg_match('#^.\0+#s', substr($data, 3, 17))) {
									$newData['ExitPupilPosition'] = ord(substr($data, 4, 1)) > 0 ? 2048 / ord(substr($data, 4, 1)) : 0; // mm
									$newData['AFAperture'] = pow(2, ord(substr($data, 5, 1)) / 24);
									$newData['FocusPosition'] = '0x' . str_pad(strtoupper(dechex(ord(substr($data, 9, 1)))), 2, '0', STR_PAD_LEFT);
									$newData['FocusDistance'] = 0.01 * pow(10, ord(substr($data, 11, 1)) / 40); // m
									$newData['FocalLength'] = 5 * pow(2, ord(substr($data, 12, 1)) / 24); // mm
									$newData['LensIDNumber'] = ord(substr($data, 13, 1));
									$newData['LensFStops'] = ord(substr($data, 14, 1)) / 12;
									$newData['MinFocalLength'] = 5 * pow(2, ord(substr($data, 15, 1)) / 24); // mm
									$newData['MaxFocalLength'] = 5 * pow(2, ord(substr($data, 16, 1)) / 24); // mm
									$newData['MaxApertureAtMinFocal'] = pow(2, ord(substr($data, 17, 1)) / 24);
									$newData['MaxApertureAtMaxFocal'] = pow(2, ord(substr($data, 18, 1)) / 24);
									$newData['MCUVersion'] = ord(substr($data, 19, 1));
									$newData['EffectiveMaxAperture'] = pow(2, ord(substr($data, 20, 1)) / 24);
								}

								if (!preg_match('#^.\0+#s', substr($data, 47, 17))) {
									$newData['LensID'] = static::$NikkorZLensIDS[getid3_lib::LittleEndian2Int(substr($data, 48, 2))];
									$newData['MaxAperture'] = pow(2, (getid3_lib::LittleEndian2Int(substr($data, 54, 2)) / 384 - 1));
									$newData['FNumber'] = pow(2, (getid3_lib::LittleEndian2Int(substr($data, 56, 2)) / 384 - 1));
									$newData['FocalLength'] = getid3_lib::LittleEndian2Int(substr($data, 60, 2)); // mm
									$newData['FocusDistance'] = 0.01 * pow(10, ord(substr($data, 79, 1)) / 40); // m
								}

								$data = $newData;
								break;
							default:
								// $data = $this->decryptLensInfo($data, $serialNumber, $shutterCount, 4);

								$data = array(
									'LensDataVersion' => $version,
								);
								break;
						}
						break;
					case 0x020000a7: // ShutterCount
						$shutterCount = $data;
						break;
					case 0x020000a8: // FlashInfo
						$version = substr($data, 0, 4);

						switch ($version) {
							case '0100':
							case '0101':
								$data = array(
									'FlashInfoVersion' => substr($data, 0, 4),
									'FlashSource' => static::$flashInfoSources[ord(substr($data, 4, 1))],
									'ExternalFlashFirmware' => $this->flashFirmwareLookup(ord(substr($data, 6, 1)), ord(substr($data, 7, 1))),
									'ExternalFlashFlags' => $this->externalFlashFlagsLookup(ord(substr($data, 8, 1))),
									'FlashCommanderMode' => (bool)(ord(substr($data, 9, 1)) & 0x80),
									'FlashControlMode' => static::$flashInfoControlModes[ord(substr($data, 9, 1)) & 0x7F],
									'FlashOutput' => (ord(substr($data, 9, 1)) & 0x7F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 10, 1)) / 6) * 100)) : 0,
									'FlashCompensation' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 10, 1), false, true) / 6),
									'FlashFocalLength' => ord(substr($data, 11, 1)), // mm
									'RepeatingFlashRate' => ord(substr($data, 12, 1)), // Hz
									'RepeatingFlashCount' => ord(substr($data, 13, 1)),
									'FlashGNDistance' => static::$flashInfoGNDistances[ord(substr($data, 14, 1))],
									'FlashGroupAControlMode' => static::$flashInfoControlModes[ord(substr($data, 15, 1)) & 0x0F],
									'FlashGroupBControlMode' => static::$flashInfoControlModes[ord(substr($data, 16, 1)) & 0x0F],
									'FlashGroupAOutput' => (ord(substr($data, 15, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 17, 1)) / 6) * 100)) : 0,
									'FlashGroupACompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 17, 1), false, true) / 6),
									'FlashGroupBOutput' => (ord(substr($data, 16, 1)) & 0xF0) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 18, 1)) / 6) * 100)) : 0,
									'FlashGroupBCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 18, 1), false, true) / 6),
								);
								break;
							case '0102':
								$data = array(
									'FlashInfoVersion' => substr($data, 0, 4),
									'FlashSource' => static::$flashInfoSources[ord(substr($data, 4, 1))],
									'ExternalFlashFirmware' => $this->flashFirmwareLookup(ord(substr($data, 6, 1)), ord(substr($data, 7, 1))),
									'ExternalFlashFlags' => $this->externalFlashFlagsLookup(ord(substr($data, 8, 1))),
									'FlashCommanderMode' => (bool)(ord(substr($data, 9, 1)) & 0x80),
									'FlashControlMode' => static::$flashInfoControlModes[ord(substr($data, 9, 1)) & 0x7F],
									'FlashOutput' => (ord(substr($data, 9, 1)) & 0x7F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 10, 1)) / 6) * 100)) : 0,
									'FlashCompensation' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 10, 1), false, true) / 6),
									'FlashFocalLength' => ord(substr($data, 12, 1)), // mm
									'RepeatingFlashRate' => ord(substr($data, 13, 1)), // Hz
									'RepeatingFlashCount' => ord(substr($data, 14, 1)),
									'FlashGNDistance' => static::$flashInfoGNDistances[ord(substr($data, 15, 1))],
									'FlashGroupAControlMode' => static::$flashInfoControlModes[ord(substr($data, 16, 1)) & 0x0F],
									'FlashGroupBControlMode' => static::$flashInfoControlModes[ord(substr($data, 17, 1)) & 0xF0],
									'FlashGroupCControlMode' => static::$flashInfoControlModes[ord(substr($data, 17, 1)) & 0x0F],
									'FlashGroupAOutput' => (ord(substr($data, 16, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 18, 1)) / 6) * 100)) : 0,
									'FlashGroupACompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 18, 1), false, true) / 6),
									'FlashGroupBOutput' => (ord(substr($data, 17, 1)) & 0xF0) >= 0x60 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 19, 1)) / 6) * 100)) : 0,
									'FlashGroupBCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 19, 1), false, true) / 6),
									'FlashGroupCOutput' => (ord(substr($data, 17, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 20, 1)) / 6) * 100)) : 0,
									'FlashGroupCCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 20, 1), false, true) / 6),
								);
								break;
							case '0103':
							case '0104':
							case '0105':
								$data = array(
									'FlashInfoVersion' => substr($data, 0, 4),
									'FlashSource' => static::$flashInfoSources[ord(substr($data, 4, 1))],
									'ExternalFlashFirmware' => $this->flashFirmwareLookup(ord(substr($data, 6, 1)), ord(substr($data, 7, 1))),
									'ExternalFlashFlags' => $this->externalFlashFlagsLookup(ord(substr($data, 8, 1))),
									'FlashCommanderMode' => (bool)(ord(substr($data, 9, 1)) & 0x80),
									'FlashControlMode' => static::$flashInfoControlModes[ord(substr($data, 9, 1)) & 0x7F],
									'FlashOutput' => (ord(substr($data, 9, 1)) & 0x7F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 10, 1)) / 6) * 100)) : 0,
									'FlashCompensation' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 10, 1), false, true) / 6),
									'FlashFocalLength' => ord(substr($data, 12, 1)), // mm
									'RepeatingFlashRate' => ord(substr($data, 13, 1)), // Hz
									'RepeatingFlashCount' => ord(substr($data, 14, 1)),
									'FlashGNDistance' => static::$flashInfoGNDistances[ord(substr($data, 15, 1))],
									'FlashColorFilter' => static::$flashInfoColorFilters[ord(substr($data, 16, 1))],
									'FlashGroupAControlMode' => static::$flashInfoControlModes[ord(substr($data, 17, 1)) & 0x0F],
									'FlashGroupBControlMode' => static::$flashInfoControlModes[ord(substr($data, 18, 1)) & 0xF0],
									'FlashGroupCControlMode' => static::$flashInfoControlModes[ord(substr($data, 18, 1)) & 0x0F],
									'FlashGroupAOutput' => (ord(substr($data, 17, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 19, 1)) / 6) * 100)) : 0,
									'FlashGroupACompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 19, 1), false, true) / 6),
									'FlashGroupBOutput' => (ord(substr($data, 18, 1)) & 0xF0) >= 0x60 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 20, 1)) / 6) * 100)) : 0,
									'FlashGroupBCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 20, 1), false, true) / 6),
									'FlashGroupCOutput' => (ord(substr($data, 18, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 21, 1)) / 6) * 100)) : 0,
									'FlashGroupCCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 21, 1), false, true) / 6),
									'ExternalFlashCompensation' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 27, 1), false, true) / 6),
									'FlashExposureComp3' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 29, 1), false, true) / 6),
									'FlashExposureComp4' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 39, 1), false, true) / 6),
								);
								break;
							case '0106':
								$data = array(
									'FlashInfoVersion' => substr($data, 0, 4),
									'FlashSource' => static::$flashInfoSources[ord(substr($data, 4, 1))],
									'ExternalFlashFirmware' => $this->flashFirmwareLookup(ord(substr($data, 6, 1)), ord(substr($data, 7, 1))),
									'ExternalFlashFlags' => $this->externalFlashFlagsLookup(ord(substr($data, 8, 1))),
									'FlashCommanderMode' => (bool)(ord(substr($data, 9, 1)) & 0x80),
									'FlashControlMode' => static::$flashInfoControlModes[ord(substr($data, 9, 1)) & 0x7F],
									'FlashFocalLength' => ord(substr($data, 12, 1)), // mm
									'RepeatingFlashRate' => ord(substr($data, 13, 1)), // Hz
									'RepeatingFlashCount' => ord(substr($data, 14, 1)),
									'FlashGNDistance' => self::$flashInfoGNDistances[ord(substr($data, 15, 1))],
									'FlashColorFilter' => static::$flashInfoColorFilters[ord(substr($data, 16, 1))],
									'FlashGroupAControlMode' => static::$flashInfoControlModes[ord(substr($data, 17, 1)) & 0x0F],
									'FlashGroupBControlMode' => static::$flashInfoControlModes[ord(substr($data, 18, 1)) & 0xF0],
									'FlashGroupCControlMode' => static::$flashInfoControlModes[ord(substr($data, 18, 1)) & 0x0F],
									'FlashOutput' => (ord(substr($data, 9, 1)) & 0x7F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 39, 1)) / 6) * 100)) : 0,
									'FlashCompensation' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 39, 1), false, true) / 6),
									'FlashGroupAOutput' => (ord(substr($data, 17, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 40, 1)) / 6) * 100)) : 0,
									'FlashGroupACompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 40, 1), false, true) / 6),
									'FlashGroupBOutput' => (ord(substr($data, 18, 1)) & 0xF0) >= 0x60 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 41, 1)) / 6) * 100)) : 0,
									'FlashGroupBCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 41, 1), false, true) / 6),
									'FlashGroupCOutput' => (ord(substr($data, 18, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 42, 1)) / 6) * 100)) : 0,
									'FlashGroupCCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 42, 1), false, true) / 6),
								);
								break;
							case '0107':
							case '0108':
								$data = array(
									'FlashInfoVersion' => substr($data, 0, 4),
									'FlashSource' => static::$flashInfoSources[ord(substr($data, 4, 1))],
									'ExternalFlashFirmware' => $this->flashFirmwareLookup(ord(substr($data, 6, 1)), ord(substr($data, 7, 1))),
									'ExternalFlashZoomOverride' => (bool)(ord(substr($data, 8, 1)) & 0x80),
									'ExternalFlashStatus' => static::$flashInfoExternalFlashStatuses[ord(substr($data, 8, 1)) & 0x01],
									'ExternalFlashReadyState' => static::$flashInfoExternalFlashReadyStates[ord(substr($data, 9, 1)) & 0x07],
									'FlashCompensation' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 10, 1), false, true) / 6),
									'FlashFocalLength' => ord(substr($data, 12, 1)), // mm
									'RepeatingFlashRate' => ord(substr($data, 13, 1)), // Hz
									'RepeatingFlashCount' => ord(substr($data, 14, 1)),
									'FlashGNDistance' => static::$flashInfoGNDistances[ord(substr($data, 15, 1))],
									'FlashGroupAControlMode' => static::$flashInfoControlModes[ord(substr($data, 17, 1)) & 0x0F],
									'FlashGroupBControlMode' => static::$flashInfoControlModes[ord(substr($data, 18, 1)) & 0xF0],
									'FlashGroupCControlMode' => static::$flashInfoControlModes[ord(substr($data, 18, 1)) & 0x0F],
									'FlashGroupAOutput' => (ord(substr($data, 17, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 40, 1)) / 6) * 100)) : 0,
									'FlashGroupACompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 40, 1), false, true) / 6),
									'FlashGroupBOutput' => (ord(substr($data, 18, 1)) & 0xF0) >= 0x60 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 41, 1)) / 6) * 100)) : 0,
									'FlashGroupBCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 41, 1), false, true) / 6),
									'FlashGroupCOutput' => (ord(substr($data, 18, 1)) & 0x0F) >= 0x06 ? sprintf('%.0f%%', pow(2, (-ord(substr($data, 42, 1)) / 6) * 100)) : 0,
									'FlashGroupCCompensation' => sprintf('%+.1f', -getid3_lib::BigEndian2Int(substr($data, 42, 1), false, true) / 6),
								);
								break;
							case '0300':
								$data = array(
									'FlashInfoVersion' => substr($data, 0, 4),
									'FlashSource' => static::$flashInfoSources[ord(substr($data, 4, 1))],
									'ExternalFlashFirmware' => $this->flashFirmwareLookup(ord(substr($data, 6, 1)), ord(substr($data, 7, 1))),
									'FlashCompensation' => $this->printFraction(-getid3_lib::BigEndian2Int(substr($data, 27, 1), false, true) / 6),
								);
								break;
							default:
								$data = array(
									'FlashInfoVersion' => substr($data, 0, 4),
								);
								break;
						}
						break;
					case 0x020000b1: // HighISONoiseReduction
						$data = isset(static::$highISONoiseReductions[$data]) ? static::$highISONoiseReductions[$data] : $data;
						break;
					case 0x020000b7: // AFInfo2
						$avInfo2Version = substr($data, 0, 4);
						$contrastDetectAF = ord(substr($data, 4, 1));
						$phaseDetectAF = ord(substr($data, 6, 1));
						$rows = array(
							'AFInfo2Version' => $avInfo2Version,
							'ContrastDetectAF' => static::$AFInfo2ContrastDetectAFChoices[$contrastDetectAF],
							'AFAreaMode' => $contrastDetectAF
								? static::$AFInfo2AFAreaModesWithContrastDetectAF[ord(substr($data, 5, 1))]
								: static::$AFInfo2AFAreaModesWithoutContrastDetectAF[ord(substr($data, 5, 1))],
							'PhaseDetectAF' => static::$AFInfo2PhaseDetectAFChoices[$phaseDetectAF],
						);

						if ($avInfo2Version === '0100') {
							$rows['AFImageWidth']            = getid3_lib::BigEndian2Int(substr($data, 16, 2));
							$rows['AFImageHeight']           = getid3_lib::BigEndian2Int(substr($data, 18, 2));
							$rows['AFAreaXPosition']         = getid3_lib::BigEndian2Int(substr($data, 20, 2));
							$rows['AFAreaYPosition']         = getid3_lib::BigEndian2Int(substr($data, 22, 2));
							$rows['AFAreaWidth']             = getid3_lib::BigEndian2Int(substr($data, 24, 2));
							$rows['AFAreaHeight']            = getid3_lib::BigEndian2Int(substr($data, 26, 2));
							$rows['ContrastDetectAFInFocus'] = (bool)ord(substr($data, 28, 1));
						} elseif (strpos($avInfo2Version, '03') === 0) {
							$rows['AFImageWidth']  = getid3_lib::BigEndian2Int(substr($data, 42, 2));
							$rows['AFImageHeight'] = getid3_lib::BigEndian2Int(substr($data, 44, 2));
							if ($contrastDetectAF === 2
								|| ($contrastDetectAF === 1 && $avInfo2Version === '0301')
							) {
								$rows['AFAreaXPosition'] = getid3_lib::BigEndian2Int(substr($data, 46, 2));
								$rows['AFAreaYPosition'] = getid3_lib::BigEndian2Int(substr($data, 48, 2));
							}
							$rows['AFAreaWidth']  = getid3_lib::BigEndian2Int(substr($data, 50, 2));
							$rows['AFAreaHeight'] = getid3_lib::BigEndian2Int(substr($data, 52, 2));
						} elseif ($contrastDetectAF === 1 && $avInfo2Version === '0101') {
							$rows['AFImageWidth']            = getid3_lib::BigEndian2Int(substr($data, 70, 2));
							$rows['AFImageHeight']           = getid3_lib::BigEndian2Int(substr($data, 72, 2));
							$rows['AFAreaXPosition']         = getid3_lib::BigEndian2Int(substr($data, 74, 2));
							$rows['AFAreaYPosition']         = getid3_lib::BigEndian2Int(substr($data, 76, 2));
							$rows['AFAreaWidth']             = getid3_lib::BigEndian2Int(substr($data, 78, 2));
							$rows['AFAreaHeight']            = getid3_lib::BigEndian2Int(substr($data, 80, 2));
							$rows['ContrastDetectAFInFocus'] = (bool) ord(substr($data, 82, 1));
						}

						$data = $rows;
						break;
					case 0x020000c3: // BarometerInfo
						$data = array(
							'BarometerInfoVersion' => substr($data, 0, 4),
							'Altitude' => getid3_lib::BigEndian2Int(substr($data, 6, 4), false, true), // m
						);
						break;
				}
				$tag_name = (isset($NCTGtagName[$record_type]) ? $NCTGtagName[$record_type] : '0x' . str_pad(dechex($record_type), 8, '0', STR_PAD_LEFT));

				$parsed[$tag_name] = $data;
			}
		}

		return $parsed;
	}

	/**
	 * @param int $value          0x80 subtracted
	 * @param string $normalName  'Normal' (0 value) string
	 * @param string|null $format format string for numbers (default '%+d'), 3) v2 divisor
	 * @param int|null $div
	 *
	 * @return string
	 */
	protected function printPC($value, $normalName = 'Normal', $format = '%+d', $div = 1) {
		switch ($value) {
			case 0:
				return $normalName;
			case 0x7f:
				return 'n/a';
			case -0x80:
				return 'Auto';
			case -0x7f:
				return 'User';
		}

		return sprintf($format, $value / $div);
	}

	/**
	 * @param int|float $value
	 *
	 * @return string
	 */
	protected function printFraction($value) {
		if (!$value) {
			return '0';
		} elseif ((int) $value /$value > 0.999) {
			return sprintf("%+d", (int) $value);
		} elseif ((int) ($value * 2) / ($value * 2) > 0.999) {
			return sprintf("%+d/2", (int) ($value * 2));
		} elseif ((int) ($value * 3) / ($value * 3) > 0.999) {
			return sprintf("%+d/3", (int) ($value * 3));
		}

		return sprintf("%+.3g", $value);
	}

	/**
	 * @param int $firstByte
	 * @param int $secondByte
	 *
	 * @return string
	 */
	protected function flashFirmwareLookup($firstByte, $secondByte)
	{
		$indexKey = $firstByte.' '.$secondByte;
		if (isset(static::$flashInfoExternalFlashFirmwares[$indexKey])) {
			return static::$flashInfoExternalFlashFirmwares[$indexKey];
		}

		return sprintf('%d.%.2d (Unknown model)', $firstByte, $secondByte);
	}

	/**
	 * @param int $flags
	 *
	 * @return string[]|string
	 */
	protected function externalFlashFlagsLookup($flags)
	{
		$result = array();
		foreach (static::$flashInfoExternalFlashFlags as $bit => $value) {
			if (($flags >> $bit) & 1) {
				$result[] = $value;
			}
		}

		return $result;
	}

	/**
	 * @param string $data
	 * @param mixed|null $serialNumber
	 * @param mixed|null $shutterCount
	 * @param int $decryptStart
	 *
	 * @return false|string
	 */
	protected function decryptLensInfo(
		$data,
		$serialNumber = null,
		$shutterCount = null,
		$decryptStart = 0
	) {
		if (null === $serialNumber && null === $shutterCount) {
			return false;
		}

		if (!is_int($serialNumber) || !is_int($shutterCount)) {
			if (null !== $serialNumber && null !== $shutterCount) {
				$this->getid3->warning('Invalid '.(!is_int($serialNumber) ? 'SerialNumber' : 'ShutterCount'));
			} else {
				$this->getid3->warning('Cannot decrypt Nikon tags because '.(null === $serialNumber ? 'SerialNumber' : 'ShutterCount').' key is not defined.');
			}

			return false;
		}

		$start = $decryptStart;
		$length = strlen($data) - $start;

		return $this->decrypt($data, $serialNumber, $shutterCount, $start, $length);
	}

	/**
	 * Decrypt Nikon data block
	 *
	 * @param string $data
	 * @param int $serialNumber
	 * @param int $count
	 * @param int $start
	 * @param int $length
	 *
	 * @return string
	 */
	protected function decrypt($data, $serialNumber, $count, $start = 0, $length = null)
	{
		$maxLen = strlen($data) - $start;
		if (null === $length || $length > $maxLen) {
			$length = $maxLen;
		}

		if ($length <= 0) {
			return $data;
		}

		$key = 0;
		for ($i = 0; $i < 4; ++$i) {
			$key ^= ($count >> ($i * 8)) & 0xFF;
		}
		$ci = static::$decodeTables[0][$serialNumber & 0xff];
		$cj = static::$decodeTables[1][$key];
		$ck = 0x60;
		$unpackedData = array();
		for ($i = $start; $i < $length + $start; ++$i) {
			$cj = ($cj + $ci * $ck) & 0xff;
			$ck = ($ck + 1) & 0xff;
			$unpackedData[] = ord($data[$i]) ^ $cj;
		}

		$end = $start + $length;
		$pre = $start ? substr($data, 0, $start) : '';
		$post = $end < strlen($data) ? substr($data, $end) : '';

		return $pre . implode('', array_map('chr', $unpackedData)) . $post;
	}

	/**
	 * Get serial number for use as a decryption key
	 *
	 * @param string $serialNumber
	 * @param string|null $model
	 *
	 * @return int|null
	 */
	protected function serialKey($serialNumber, $model = null)
	{
		if (empty($serialNumber) || ctype_digit($serialNumber)) {
			return (int) $serialNumber;
		}

		if (null !== $model && preg_match('#\bD50$#', $model)) {
			return 0x22;
		}

		return 0x60;
	}
}
