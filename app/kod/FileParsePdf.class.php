<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/


/**
 * 解析获取pdf文件信息;
 * 
 * pdfparser https://www.pdfparser.org/documentation
 * mpdf编辑: http://mpdf.github.io/
 */
class FileParsePdf{
	public static function parse($filePath){
		$chunkSize	= 32 * 1024;//trailer处理;
		$fileInfo   = array(
			'fp'		=> fopen($filePath,'r'),
			'path'		=> $filePath,
			'size'		=> filesize_64($filePath),
			'chunkSize' => $chunkSize,
		);
		$fileInfo['dataStart'] = StreamWrapperIO::read($filePath,0,$chunkSize);
		$fileInfo['dataEnd']   = StreamWrapperIO::read($filePath,$fileInfo['size'] - $chunkSize,$chunkSize);
		// if($_GET['debug'] == '1'){
		// 	include('/Library/WebServer/Documents/localhost/test/000/test/pdfparser-0.18.1/vendor/autoload.php');
		// 	$parser = new \Smalot\PdfParser\Parser();
		// 	$pdf = $parser->parseFile($filePath);pr($pdf->getDetails());exit;
		// }
		
		$xref = self::decodeXref($fileInfo);
		if($xref){
			$infoKey  = $xref['trailer']['info'];
			$dataInfo = self::getObjectValue($fileInfo,$xref,$infoKey);
		}
		
		$dataInfo = is_array($dataInfo) ? $dataInfo : array();
		// 页面尺寸处理;
		$dataInfo['sizeWidth'] = 0;		
		$theReg = '/[\s]*\/MediaBox[\s]*\[[\s]*([0-9\.]+)[\s]+([0-9\.]+)[\s]+([0-9\.]+)[\s]+([0-9\.]+)[\s]*\]/i';
		preg_match($theReg,$fileInfo['dataStart'],$matches);
		if (!$dataInfo['sizeWidth'] && count($matches) == 5){
			$dataInfo['sizeWidth']  = $matches[3];
			$dataInfo['sizeHeight'] = $matches[4];
		}
		preg_match($theReg,$fileInfo['dataEnd'],$matches);
		if (!$dataInfo['sizeWidth'] && count($matches) == 5){
			$dataInfo['sizeWidth']  = $matches[3];
			$dataInfo['sizeHeight'] = $matches[4];
		}
		preg_match('/%PDF-([0-9\.]+)/',$fileInfo['dataStart'],$matches);
		if($matches){$dataInfo['version'] = $matches[1];}

		// // 页数计算处理; /Count 8
		$dataInfo['pageNumber'] = 0;
		$theReg = "/[\s]*\/Count[\s]+([0-9]+)[\s]*/i";
		
		preg_match_all($theReg,$fileInfo['dataStart'],$matches);
		if($matches[1] && $dataInfo['pageNumber'] < $matches[1][0]){
			$dataInfo['pageNumber'] = $matches[1][0];
		}		
		preg_match_all($theReg,$fileInfo['dataEnd'],$matches);
		if($matches[1] && $dataInfo['pageNumber'] < $matches[1][0]){
			$dataInfo['pageNumber'] = $matches[1][0];
		}
		
		$dataInfo = self::parseInfoItem($dataInfo);
		return $dataInfo;
	}	
	private static function parseInfoItem($dataInfo){
		if(!$dataInfo) return false;
		$picker = array( //数值统一筛选并处理;
			'title' 		 => array('Title',''),				// 标题
			'auther'	 	 => array('Author',''),				// 作者
			'createTime'	 => array('CreationDate','date'),	// 创建日期
			'modifyTime' 	 =>	array('ModDate','date'),		// 修改日期
			'pageNumber'	 => array('pageNumber','int'),		// 页数
			'sizeWidth'		 => array('sizeWidth','int'),		// 页面宽度
			'sizeHeight'	 => array('sizeHeight','int'),		// 页面高度
			'creator'	 	 => array('Creator',''),			// 内容创作者
			'producer'	 	 => array('Producer',''),			// 编码软件
			'pdfVersion'	 => array('version',''),			// PDF 版本;
		);
		
		$result = array();
		foreach ($picker as $key => $info){
			if(!isset($dataInfo[$info[0]])) continue;
			$value = $dataInfo[$info[0]];
			if(!$value || is_array($value)) continue;
			
			switch($info[1]){
				case 'int' :$value = intval($value);break;
				case 'date':
					if(substr($value,0,2) == 'D:') {
						$value = substr($value,2,14);
					}
					if(strtotime($value)){
						$value = date('Y-m-d H:i:s',strtotime($value));
					}
					break;
			}
			$result[$key] = $value;
		}
		// pr($result,$dataInfo);exit;
		return $result;
	}
	
	private static function decodeXref(&$fileInfo){
		$pdfData = $fileInfo['dataEnd'];
		$xref   = array('trailer'=>array(),'xref'=>array());
		$theReg = '/[\r\n]startxref[\s]*[\r\n]*([0-9]+)[\s]*[\r\n]+%%EOF/i';
		if(!preg_match_all($theReg,$pdfData, $matches,PREG_SET_ORDER,0)) return false;

		// 结尾block索引开始位置,比最小block小则加大;
		$startxref = intval($matches[0][1]);
		if($fileInfo['size'] - $startxref > $fileInfo['chunkSize']){
			$chunkSize = 4 * $fileInfo['chunkSize'];
			$fileInfo['chunkSize'] = $chunkSize;
			$fileInfo['dataStart'] = StreamWrapperIO::read($fileInfo['path'],0,$chunkSize);
			$fileInfo['dataEnd']   = StreamWrapperIO::read($fileInfo['path'],$fileInfo['size'] - $chunkSize,$chunkSize);
			$pdfData = $fileInfo['dataEnd'];
		}

		$objNum = 0;
		// preg_match_all('/([0-9]+)[\x20]([0-9]+)[\x20]?([nf]?)(\r\n|[\x20]?[\r\n])/',$pdfData,$matches);
		preg_match_all('/([0-9]+)[\x20]([0-9]+)[\x20]?([nf]?)(\r\n|[\x20]?[\r\n])/',$pdfData,$matches);
		foreach ($matches[3] as $i=>$item){
			if($matches[3][$i] == 'n'){
				$index = $objNum.'_'.intval($matches[2][$i]);
				$xref['xref'][$index] = intval($matches[1][$i]);
                ++$objNum;
            }else if ($matches[3][$i] == 'f') {
                ++$objNum;
            } else {
				// $objNum = intval($matches[1][$i]); //从1开始;
            }
		}
		
		// 没有索引的情况处理; 优先按照的的进行匹配;
		if(preg_match_all('/[\r\n]([0-9]+)[\s]+([0-9]+)[\s]+obj/iU',$pdfData, $matches)){
			$fileOffset = $fileInfo['size'] - $fileInfo['chunkSize'];
			foreach ($matches[0] as $i => $theValue) {
				$key   = $matches[1][$i].'_'.$matches[2][$i];
				$xref['xref'][$key] = strpos($pdfData,$theValue) + $fileOffset + 1;
			}
		}

		if(preg_match_all('/trailer[\s]*<<(.*)>>/isU',$pdfData,$matches)){
			$trailerData = count($matches[1]) == 1 ? $matches[1][0] : $matches[1][1];
		}else{// 兼容没有trailer情况的数据; 直接从文件最后正则匹配查找;
			$trailerData = substr($pdfData, -1024*5);
		}
		if (preg_match('/Size[\s]+([0-9]+)/i', $trailerData, $matches) > 0) {
			$xref['trailer']['size'] = intval($matches[1]);
		}
		if (preg_match('/Root[\s]+([0-9]+)[\s]+([0-9]+)[\s]+R/i', $trailerData, $matches) > 0) {
			$xref['trailer']['root'] = intval($matches[1]).'_'.intval($matches[2]);
		}
		if (preg_match('/Encrypt[\s]+([0-9]+)[\s]+([0-9]+)[\s]+R/i', $trailerData, $matches) > 0) {
			$xref['trailer']['encrypt'] = intval($matches[1]).'_'.intval($matches[2]);
		}
		if (preg_match('/Info[\s]+([0-9]+)[\s]+([0-9]+)[\s]+R/i', $trailerData, $matches) > 0) {
			$xref['trailer']['info'] = intval($matches[1]).'_'.intval($matches[2]);
		}
		if (preg_match('/ID[\s]*[\[][\s]*[<]([^>]*)[>][\s]*[<]([^>]*)[>]/i', $trailerData, $matches) > 0) {
			$xref['trailer']['id'] = [];
			$xref['trailer']['id'][0] = $matches[1];
			$xref['trailer']['id'][1] = $matches[2];
		}
		if(!$xref['trailer']['info']) return false;
		if (preg_match('/Prev[\s]+([0-9]+)/i', $trailerData, $matches) > 0) {
			// $xref = self::decodeXref($pdfData,intval($matches[1]), $xref);
		}
		return $xref;
	}
	
	private static function getObjectValue($fileInfo,$xref,$infoKey){
		$dataInfoRef  = self::getObject($fileInfo,$xref['xref'][$infoKey]);
		// pr($infoKey,$dataInfoRef);
		
		if(is_string($dataInfoRef[1])) return $dataInfoRef[1];
		if(!is_array($dataInfoRef[1])) return array();
		$dataInfo = array();
		for ($i = 0; $i< count($dataInfoRef[1]);$i+=2){
			$itemKey 	= $dataInfoRef[1][$i];
			$itemValue 	= $dataInfoRef[1][$i+1];
			if(count($itemKey) == 3 && $itemKey[0] == '/'){
				$value = false;
				if($itemValue[0] == 'objref'){
					$itemValue = self::getObject($fileInfo,$xref['xref'][$itemValue[1]]);
				}
				$value = $itemValue[1];			
				if($value === false) continue;
				if(is_string($value)){
					$value = self::decodeStr($value);
				}
				$dataInfo[$itemKey[1]] = $value;
			}
		}
		return $dataInfo;
	}
	private static function getObject($fileInfo,$offset){
		$dataIndex = self::getObjectItem($fileInfo,$offset);
		$dataIndex = self::getObjectItem($fileInfo,$dataIndex[2]);
		return $dataIndex;
	}
	private static function getObjectItem($fileInfo,$offset){
		// return self::getRawObject($fileInfo['dataEnd'],$offset);
		$chunkSize  = $fileInfo['chunkSize'];
		$fileOffset = $fileInfo['size'] - $chunkSize;		
		$thePose = $offset >= $fileOffset ? ($offset - $fileOffset) : $offset;
		$theData = $offset >= $fileOffset ? $fileInfo['dataEnd']: $fileInfo['dataStart'];
		if($offset > $chunkSize && $offset <= $fileOffset ){
			$thePose = 0;
			$theData = StreamWrapperIO::read($fileInfo['path'],$offset,$chunkSize);
			// pr("getFile:$offset;$chunkSize",substr($theData,200));
		}
		$dataIndex = self::getRawObject($theData,$thePose);
		
		// 重置offset;
		if($offset >= $fileOffset){
			$dataIndex[2] = $dataIndex[2] + $fileOffset;
		}else if($offset > $chunkSize && $offset <= $fileOffset){
			$dataIndex[2] = $dataIndex[2] + $offset;
		}
		// pr('getObjectItem:',[$offset,$chunkSize,$fileOffset,$thePose],$dataIndex);
		return $dataIndex;
	}
	
	// 解析节点数据;(xxx xx obj)
	private static function decodeStr($text){
		$text = str_replace(
			['\\\\', '\\ ', '\\/', '\(', '\)', '\n', '\r', '\t'],
			['\\',   ' ',   '/',   '(',  ')',  "\n", "\r", "\t"],$text);
			
		$parts = preg_split('/(\\\\\d{3})/s', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $text = '';
        foreach ($parts as $part) {
            if (preg_match('/^\\\\\d{3}$/', $part)) {
                $text .= \chr(octdec(trim($part, '\\')));
            } else {
                $text .= $part;
            }
		}
		$parts = preg_split('/(#\d{2})/s', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $text = '';
        foreach ($parts as $part) {
            if (preg_match('/^#\d{2}$/', $part)) {
                $text .= \chr(hexdec(trim($part, '#')));
            } else {
                $text .= $part;
            }
		}
		
		$parts = preg_split('/(<[a-f0-9]+>)/si', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		$text = '';
        foreach ($parts as $part) {
            if (preg_match('/^<.*>$/s', $part) && false === stripos($part, '<?xml')) {
                $part = preg_replace("/[\r\n]/", '', $part);
                $part = trim($part, '<>');
                $part = pack('H*', $part);
                $text .= $part;
            } else {
                $text .= $part;
            }
		}
		if(preg_match('/^\xFE\xFF/i', $text)) {
            // Strip U+FEFF byte order marker.
            $decode = substr($text, 2);
            $text = '';
            $length = strlen($decode);
            for ($i = 0; $i < $length; $i += 2) {
				$hex   = hexdec(bin2hex(substr($decode, $i, 2)));
				$text .= mb_convert_encoding('&#'.intval($hex).';', 'UTF-8', 'HTML-ENTITIES');
            }
		}
		return $text;
	}
	private static function getRawObject($pdfData, $offset = 0){
        $objtype = ''; // object type to be returned
        $objval = ''; // object value to be returned
        /*
         * skip initial white space chars:
         *      \x00 null (NUL)
         *      \x09 horizontal tab (HT)
         *      \x0A line feed (LF)
         *      \x0C form feed (FF)
         *      \x0D carriage return (CR)
         *      \x20 space (SP)
         */
        $offset += strspn($pdfData, "\x00\x09\x0a\x0c\x0d\x20", $offset);
		$char = $pdfData[$offset];
		// echo "<pre>";var_dump($char,'pos='.$offset.';len='.strlen($pdfData),substr($pdfData,$offset,33));echo "</pre>";
		
        switch ($char) {
            case '%':  // \x25 PERCENT SIGN
                    // skip comment and search for next token
                    $next = strcspn($pdfData, "\r\n", $offset);
                    if ($next > 0) {
                        $offset += $next;
                        return self::getRawObject($pdfData, $offset);
                    }
                    break;
            case '/':  // \x2F SOLIDUS
                    $objtype = $char;
                    ++$offset;
                    $pregResult = preg_match(
                        '/^([^\x00\x09\x0a\x0c\x0d\x20\s\x28\x29\x3c\x3e\x5b\x5d\x7b\x7d\x2f\x25]+)/',
                        substr($pdfData, $offset, 256),
                        $matches
                    );
                    if (1 == $pregResult) {
                        $objval = $matches[1]; // unescaped value
                        $offset += strlen($objval);
                    }
                    break;
            case '(':   // \x28 LEFT PARENTHESIS
            case ')':  // \x29 RIGHT PARENTHESIS
                    // literal string object
                    $objtype = $char;
                    ++$offset;
                    $strpos = $offset;
                    if ('(' == $char) {
                        $open_bracket = 1;
                        while ($open_bracket > 0) {
							if (!isset($pdfData[$strpos])) break;
                            $ch = $pdfData[$strpos];
                            switch ($ch) {
                                case '\\':  // REVERSE SOLIDUS (5Ch) (Backslash)
									// skip next character
									++$strpos;
									break;
                                case '(':  // LEFT PARENHESIS (28h)
									++$open_bracket;
									break;
                                case ')':  // RIGHT PARENTHESIS (29h)
									--$open_bracket;
									break;
                            }
                            ++$strpos;
                        }
                        $objval = substr($pdfData, $offset, ($strpos - $offset - 1));
                        $offset = $strpos;
                    }
                    break;
            case '[':   // \x5B LEFT SQUARE BRACKET
            case ']':  // \x5D RIGHT SQUARE BRACKET
                // array object
                $objtype = $char;
                ++$offset;
                if ('[' == $char) {
                    // get array content
                    $objval = array();
                    do {
                        $oldOffset = $offset;
                        // get element
                        $element = self::getRawObject($pdfData, $offset);
                        $offset = $element[2];
                        $objval[] = $element;
                    } while ((']' != $element[0]) && ($offset != $oldOffset));
                    // remove closing delimiter
                    array_pop($objval);
                }
                break;
            case '<':  // \x3C LESS-THAN SIGN
            case '>':  // \x3E GREATER-THAN SIGN
                if (isset($pdfData[($offset + 1)]) && ($pdfData[($offset + 1)] == $char)) {
                    // dictionary object
                    $objtype = $char.$char;
                    $offset += 2;
                    if ('<' == $char) {
                        $objval = array();
                        do {
                            $oldOffset = $offset;
                            // get element
                            $element = self::getRawObject($pdfData, $offset);
                            $offset = $element[2];
                            $objval[] = $element;
                        } while (('>>' != $element[0]) && ($offset != $oldOffset));
                        // remove closing delimiter
                        array_pop($objval);
                    }
                } else {
                    // hexadecimal string object
                    $objtype = $char;
                    ++$offset;
                    $pregResult = preg_match('/^([0-9A-Fa-f\x09\x0a\x0c\x0d\x20]+)>/iU',substr($pdfData, $offset),$matches);
                    if (('<' == $char) && 1 == $pregResult) {
                        // remove white space characters
                        $objval = strtr($matches[1], "\x09\x0a\x0c\x0d\x20", '');
                        $offset += \strlen($matches[0]);
                    } elseif (false !== ($endpos = strpos($pdfData, '>', $offset))) {
                        $offset = $endpos + 1;
                    }
                }
                break;
            default:
				if ('endobj' == substr($pdfData, $offset, 6)) {
					// indirect object
					$objtype = 'endobj';
					$offset += 6;
				} elseif ('null' == substr($pdfData, $offset, 4)) {
					// null object
					$objtype = 'null';
					$offset += 4;
					$objval = 'null';
				} elseif ('true' == substr($pdfData, $offset, 4)) {
					// boolean true object
					$objtype = 'boolean';
					$offset += 4;
					$objval = 'true';
				} elseif ('false' == substr($pdfData, $offset, 5)) {
					// boolean false object
					$objtype = 'boolean';
					$offset += 5;
					$objval = 'false';
				} elseif ('stream' == substr($pdfData, $offset, 6)) {
					// start stream object
					$objtype = 'stream';
					$offset += 6;
					if (1 == preg_match('/^([\r]?[\n])/isU', substr($pdfData, $offset), $matches)) {
						$offset += strlen($matches[0]);
						$endStreamReg = '/(endstream)[\x09\x0a\x0c\x0d\x20]/isU';
						$pregResult = preg_match($endStreamReg,substr($pdfData, $offset),$matches,PREG_OFFSET_CAPTURE);
						if (1 == $pregResult) {
							$objval = substr($pdfData, $offset, $matches[0][1]);
							$offset += $matches[1][1];
						}
					}
				} elseif ('endstream' == substr($pdfData, $offset, 9)) {
					// end stream object
					$objtype = 'endstream';
					$offset += 9;
				} elseif (1 == preg_match('/^([0-9]+)[\s]+([0-9]+)[\s]+R/iU', substr($pdfData, $offset, 33), $matches)) {
					$objtype = 'objref';
					$offset += strlen($matches[0]);
					$objval = intval($matches[1]).'_'.intval($matches[2]);
				} elseif (1 == preg_match('/^([0-9]+)[\s]+([0-9]+)[\s]+obj/iU', substr($pdfData, $offset, 33), $matches)) {
					$objtype = 'obj';
					$objval = intval($matches[1]).'_'.intval($matches[2]);
					$offset += strlen($matches[0]);
				} elseif (($numlen = strspn($pdfData, '+-.0123456789', $offset)) > 0) {
					$objtype = 'numeric';
					$objval = substr($pdfData, $offset, $numlen);
					$offset += $numlen;
				}
				break;
        }
        return array($objtype, $objval, $offset);
    }
}