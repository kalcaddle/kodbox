<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/


/**
 * zip解压缩处理类
 * 
 * 
 * http://localhost/kod/kod_dev/?editor&project=/Library/WebServer/Documents/localhost/works/zip64/
 * https://blog.csdn.net/a200710716/article/details/51644421
 * https://github.com/brokencube/ZipStream64/blob/14087549a4914bfc441a396ca02849569145a273/src/ZipStream.php#L808
 * https://pkware.cachefly.net/webdocs/APPNOTE/APPNOTE-6.2.0.txt
 */
class ZipMake{
    const VERSION	        = '0.2.0';
	const ZIP_VERSION	    = 0x000A;
	const ZIP_VERSION_64	= 0x002D;
	const METHOD_STORE	    = 0x00;

    const FILE_HEADER_SIGNATURE         = 0x04034b50;   //'504b0304'
	const CDR_FILE_SIGNATURE            = 0x02014b50;   //'504b0102'
	const CDR_EOF_SIGNATURE             = 0x06054b50;   //'504b0506'
	const DATA_DESCRIPTOR_SIGNATURE     = 0x08074b50;   //'504b0708'
	const ZIP64_CDR_EOF_SIGNATURE       = 0x06064b50;   //'504b0606'
	const ZIP64_CDR_LOCATOR_SIGNATURE	= 0x07064b50;   //'504b0607'

	public $files = array();
	public $cdrOffset = 0;
    public $ofs = 0;
    
	protected $needHeaders;
	protected $outputName;
	public function __construct($name = null){
        $this->outputStream = fopen('php://output', 'w');	
		$this->outputName   = $name;
		$this->needHeaders  = true;
	}

	/**
	 * addFileFromPath
	 * add a file at path to the archive.
	 */
	public function addFile($name, $path){
        $name = $this->filterFilename($name);
		$zipMethod = static::METHOD_STORE;
        $headerLength = $this->addFileHeader($name,$zipMethod);

        $zipLength = $fileLength = filesize_64($path);
        $fh = fopen($path, 'rb');
        while (!feof($fh)) {
			$data = fread($fh, 1048576);
			$this->send($data);
		}
        fclose($fh);
        $crc = hexdec(hash_file('crc32b', $path));
		$this->addFileFooter($name,$zipMethod, $crc, $zipLength, $fileLength, $headerLength);
	}

	/**
	 * addFile_from_stream
	 */
	public function addFileFromStream($name, $stream){
		$name = $this->filterFilename($name);		
		$zipMethod  = static::METHOD_STORE;
        $headerLength = $this->addFileHeader($name,$zipMethod);

        fseek($stream, 0, SEEK_END);
		$zipLength = $fileLength = ftell($stream);	
		rewind($stream);
		$hashCtx = hash_init('crc32b');
		while (!feof($stream)) {
			$data = fread($stream, 1048576);
			hash_update($hashCtx, $data);
			$this->send($data);
		}
		$crc = hexdec(hash_final($hashCtx));
		$this->addFileFooter($name,$zipMethod, $crc, $zipLength, $fileLength, $headerLength);
	}
	
	public function finish(){
		foreach ($this->files as $file){
            $this->addCdrFile($file);
        } 
        $this->addCdr64Eof();
        $this->addCdr64Locator();
		$this->addCdrEof();
		$this->clear();
	}

	/**
	 * Create and send zip header for this file.
	 *
	 * @param String  $name
	 * @param Integer $zipMethod
	 * @return void
	 */
	protected function addFileHeader($name,$zipMethod){
		$name = preg_replace('/^\\/+/', '', $name);
		$nlen = strlen($name);
		$time        = $this->dosTime(time());
        $fields = array(
            array('V', static::FILE_HEADER_SIGNATURE),
            array('v', static::ZIP_VERSION_64),			// 压缩版本
            array('v', 0b00001000),						// General purpose bit flags - data descriptor flag set
            array('v', $zipMethod),							// Compression method
            array('V', $time),							// Timestamp (DOS Format)
            array('V', 0x00000000),						// CRC32 of data (0 -> moved to data descriptor footer)
            array('V', 0xFFFFFFFF),						// zip64时全0
            array('V', 0xFFFFFFFF),						// Length of original data (Forced to 0xFFFFFFFF for 64bit extension)
            array('v', $nlen),							// Length of filename
            array('v', 32),								// Extra data (32 bytes)
        );	
        $fields64 = array(
            array('v', 0x0001),							// 64Bit Extension
            array('v', 28),								// 28bytes of data follows 
            array('P', 0x0000000000000000),				// Length of original data (0 -> moved to data descriptor footer)
            array('P', 0x0000000000000000),				// Length of compressed data (0 -> moved to data descriptor footer)
            array('P', 0x0000000000000000),				// Relative Header Offset
            array('V', 0x00000000)						// Disk number
        );
		$header = $this->packFields($fields);
		$header64 = $this->packFields($fields64);
		$this->send($header . $name . $header64);
		return strlen($header) + $nlen + strlen($header64);
	}

	/**
	 * Create and send data descriptor footer for this file.
	 */	
	protected function addFileFooter($name,$zipMethod, $crc, $zipLength, $fileLength, $headerLength){
        $fields = array(
            array('V', static::DATA_DESCRIPTOR_SIGNATURE),
            array('V', $crc),							// CRC32
            array('P', $zipLength),						// 压缩后大小
            array('P', $fileLength),					// 原始大小
        );

		$footer = $this->packFields($fields);		
		$this->send($footer);
		$totalLength = $headerLength + $zipLength + $flen;		
		$this->addToCdr($name,$zipMethod, $crc, $zipLength, $fileLength, $totalLength);		
	}
   
	/**
	 * Save file attributes for trailing CDR record.
	 *
	 * @param String  $name
	 * @param Integer $zipMethod
	 * @param string  $crc
	 * @param Integer $zipLength
	 * @param Integer $len
	 * @param Integer $rec_len
	 * @return void
	 * @return void
	 */
	private function addToCdr($name,$zipMethod, $crc, $zipLength, $len, $rec_len) {
		$this->files[] = array(
			$name,
			$zipMethod,
			$crc,
			$zipLength,
			$len,
			$this->ofs
		);
		$this->ofs += $rec_len;
	}
	
	/**
	 * Send CDR record for specified file.
	 */
	protected function addCdrFile($args){
		list($name,$zipMethod, $crc, $zipLength, $len, $offset) = $args;
		$comment = '';
		$time = $this->dosTime(time());		
        $fields = array(
            array('V', static::CDR_FILE_SIGNATURE),		// Central file header signature
            array('v', static::ZIP_VERSION_64),			// Made by version
            array('v', static::ZIP_VERSION_64),			// Extract by version
            array('v', 0b00001000),						// General purpose bit flags - data descriptor flag set
            array('v', $zipMethod),							// Compression method
            array('V', $time),							// Timestamp (DOS Format)
            array('V', $crc),							// CRC32
            array('V', 0xFFFFFFFF),						// Compressed Data Length (Forced to 0xFFFFFFFF for 64bit Extension)
            array('V', 0xFFFFFFFF),						// Original Data Length (Forced to 0xFFFFFFFF for 64bit Extension)
            array('v', strlen($name)),					// Length of filename
            array('v', 32),								// Extra data len (32bytes of 64bit Extension)
            array('v', strlen($comment)),				// Length of comment
            array('v', 0),								// Disk number
            array('v', 0),								// Internal File Attributes
            array('V', 32),								// External File Attributes
            array('V', 0xFFFFFFFF)						// Relative offset of local header (Forced to 0xFFFFFFFF for 64bit Extension)
        );			
        $fields64 = array(
            array('v', 0x0001),							// 64Bit Extension
            array('v', 28),								// 28bytes of data follows 
            array('P', $len),							// Length of original data (0 -> moved to data descriptor footer)
            array('P', $zipLength),							// Length of compressed data (0 -> moved to data descriptor footer)
            array('P', $offset),						// Relative Header Offset
            array('V', 0)								// Disk number
        );
		$header = $this->packFields($fields);
		$footer = $this->packFields($fields64);
		
		$ret = $header . $name . $comment . $footer;
		$this->send($ret);
		$this->cdr_ofs += strlen($ret);
	}
	
	/**
	 * Send ZIP64 CDR EOF (Central Directory Record End-of-File) record.
	 */
	protected function addCdr64Eof(){
		$num     = count($this->files);
		$cdrLength = $this->cdr_ofs;
		$cdrOffset = $this->ofs;
		
		$fields = array(
			array('V', static::ZIP64_CDR_EOF_SIGNATURE), 	// ZIP64 end of central file header signature
			array('P', 44),									// Length of data below this header (length of block - 12) = 44
			array('v', static::ZIP_VERSION_64),				// Made by version
			array('v', static::ZIP_VERSION_64),				// Extract by version
			array('V', 0x00), 								// disk number
			array('V', 0x00), 								// no of disks
			array('P', $num),								// no of entries on disk
			array('P', $num),								// no of entries in cdr
			array('P', $cdrLength),							// CDR size
			array('P', $cdrOffset),							// CDR offset
		);
		$ret = $this->packFields($fields);
		$this->send($ret);
	}

	/**
	 * Send ZIP64 CDR Locator (Central Directory Record Locator) record.
	 */
	protected function addCdr64Locator(){
		$num     = count($this->files);
		$cdrLength = $this->cdr_ofs;
		$cdrOffset = $this->ofs;
		$fields = array(
			array('V', static::ZIP64_CDR_LOCATOR_SIGNATURE), // ZIP64 end of central file header signature
			array('V', 0x00),								// Disc number containing CDR64EOF
			array('P', $cdrOffset + $cdrLength),			// CDR offset
			array('V', 1),									// Total number of disks
		);		
		$ret = $this->packFields($fields);
		$this->send($ret);
	}

	/**
	 * Send CDR EOF (Central Directory Record End-of-File) record.
	 */
	protected function addCdrEof(){
		$num     = count($this->files);
		$cdrLength = $this->cdr_ofs;
		$cdrOffset = $this->ofs;
		$comment = '';
        $fields = array(
            array('V', static::CDR_EOF_SIGNATURE), 	// end of central file header signature
            array('v', 0x00), 						// disk number
            array('v', 0x00), 						// no of disks
            array('v', $num),						// no of entries on disk
            array('v', $num),						// no of entries in cdr
            array('V', 0xFFFFFFFF),					// CDR size (Force to 0xFFFFFFFF for Zip64)
            array('V', 0xFFFFFFFF),					// CDR offset (Force to 0xFFFFFFFF for Zip64)
            array('v', strlen($comment)),			// Zip Comment size
        );
		$ret = $this->packFields($fields) . $comment;
		$this->send($ret);
	}
	
	/**
	 * Add CDR (Central Directory Record) footer.
	 */
	protected function addCdr(){
		foreach ($this->files as $file){
            $this->addCdrFile($file);
        }
		$this->addCdrEof();
	}
	
	protected function clear(){
		$this->files   = array();
		$this->ofs     = 0;
		$this->cdr_ofs = 0;
	}

	protected function sendHttpHeaders(){
		$disposition = 'attachment';
		if ($this->outputName) {
			$safeOutput = trim(str_replace(['"', "'", '\\', ';', "\n", "\r"], '', $this->outputName));
            $urlencoded = rawurlencode($safeOutput);
			$disposition .= "; filename*=utf-8''{$urlencoded}";
		}		
		$headers = array(
			'Content-Type'              => 'application/x-zip',
			'Content-Disposition'       => $disposition,
			'Pragma'                    => 'public',
			'Cache-Control'             => 'public, must-revalidate',
			'Content-Transfer-Encoding' => 'binary'
        );
        foreach ($headers as $key => $value) {
            header($key.': '.$value);
        }
	}
	
	/**
	 * Send string, sending HTTP headers if necessary.
	 */
	protected function send($str){
		if ($this->needHeaders) {
			$this->sendHttpHeaders();
		}
		$this->needHeaders = false;		
		fwrite($this->outputStream, $str);
	}
	
	/**
	 * 转换时间戳为dos时间
	 */
	protected final function dosTime($when){
		$d = getdate($when);
		if ($d['year'] < 1980) {
			$d = array(
				'year'      => 1980,
				'mon'       => 1,
				'mday'      => 1,
				'hours'     => 0,
				'minutes'   => 0,
				'seconds'   => 0
			);
		}
		$d['year'] -= 1980;
		return ($d['year'] << 25)  | ($d['mon'] << 21) | ($d['mday'] << 16) | 
			   ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
	}
	protected function packFields($fields){
		$fmt = '';
		$args = array();
		foreach ($fields as $field) {
			$fmt .= $field[0];
			$args[] = $field[1];
		}
		array_unshift($args, $fmt);
		return call_user_func_array('pack', $args);
	}
	protected function filterFilename($filename){
		return str_replace(['\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);
	}
}