<?php

class Html{
	public static function clean($data){
		$data = $data ? str_replace("\\","\\\\",$data):'';
		require_once SDK_DIR.'HtmlPurifier/HTMLPurifier.auto.php';
		$config = HTMLPurifier_Config::createDefault();
		$config->set('Core.Encoding', 'UTF-8');
		$config->set('CSS.AllowTricky', true);
		$config->set('Attr.EnableID', true); 	// 允许id
		$config->set('HTML.Trusted', true);		// 影响iframe过滤;
		
		$config->set('Cache.SerializerPath', TEMP_PATH);// 缓存目录;
		$config->set('Cache.DefinitionImpl', null);
		$config->set('HTML.TargetBlank', FALSE);
		$config->set('HTML.TargetNoreferrer', FALSE);
		$config->set('HTML.TargetNoopener', FALSE);
		// $config->set('HTML.SafeIframe',true);// iframe包含页面白名单;
		// $config->set('URI.SafeIframeRegexp', '%^https://(*)%');
		
		// 设置保留的标签
		$config->set('HTML.Allowed','*[id|style|class|title|border|width|height|title|alt|type|data-exp|rowspan|colspan],div,b,strong,i,em,a[href|target],ul,ol,ol[start],li,p,br,span,img[src],pre,hr,code,h1,h2,h3,h4,h5,h6,blockquote,del,table,thead,tbody,tr,th,td,s,sub,sup,ins,del,address,iframe[frameborder|src|allowfullscreen|scrolling],var,mark,wbr,section,nav,article,aside,header,footer,hgroup,figure,figcaption,video[src|controls|poster],source[src]');
		// data|data-exp|contenteditable|aria-hidden 

		// 不限制css的key;
		// $config->set('CSS.AllowedProperties','font,font-size,font-weight,font-style,font-family,margin,width,height,text-decoration,padding-left,padding-top,padding-right,padding-top,padding-bottom,line-height,color,background,background-color,text-align,border-collapse,list-style-type,list-style,top,left,right,bottom,position');
		
		$def = $config->getHTMLDefinition(true);
		$def->addElement('section', 'Block', 'Flow', 'Common');
		$def->addElement('nav',     'Block', 'Flow', 'Common');
		$def->addElement('article', 'Block', 'Flow', 'Common');
		$def->addElement('aside',   'Block', 'Flow', 'Common');
		$def->addElement('header',  'Block', 'Flow', 'Common');
		$def->addElement('footer',  'Block', 'Flow', 'Common');
		$def->addElement('hgroup',  'Block', 'Required: h1 | h2 | h3 | h4 | h5 | h6','Common');
		$def->addElement('figure',  'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow','Common');
		$def->addElement('figcaption', 'Inline', 'Flow', 'Common');
		$def->addElement('var',  'Inline', 'Flow', 'Common');
		$def->addElement('mark', 'Inline', 'Flow', 'Common');
		$def->addElement('wbr',  'Inline', 'Flow', 'Core');
		$def->addElement('source','Block', 'Flow', 'Common',array('src'=>'URI','type'=>'Text'));
		$def->addElement('video', 'Block', 'Flow', 'Common',array(
			'src' => 'URI','type' => 'Text','width' => 'Length','height' => 'Length',
			'poster' => 'URI','preload' => 'Enum#auto,metadata,none','controls' => 'Bool',
		));
		$def->addAttribute('a','target','Text');
		$def->addAttribute('iframe','allowfullscreen','Text');
		$def->addAttribute('div', 'data-exp','Text');
		$def->addAttribute('span','data-exp','Text');
		$def->addAttribute('div', 'style','Text');
		$def->addAttribute('span','style','Text');// 重定义style属性; 不过滤style内容;
		
		$def->addAttribute('td','style','Text');
		$def->addAttribute('td','rowspan','Text');
		$def->addAttribute('td','colspan','Text');

		$cleanObj = new HTMLPurifier($config);
		return $cleanObj->purify($data);
	}
	
	public static function onlyImage($data){
		require_once SDK_DIR.'HtmlPurifier/HTMLPurifier.auto.php';
		$config = HTMLPurifier_Config::createDefault();
		
		$config->set('Cache.SerializerPath', TEMP_PATH);// 缓存目录;
		$config->set('Cache.DefinitionImpl', null);
		$config->set('HTML.Allowed','img[src|alt]');
		$cleanObj = new HTMLPurifier($config);
		return $cleanObj->purify($data);
	}
	
	
	public static function clearSVG($content){
		return self::removeXXS($content);
	}
	
	// 正则替换掉onload,等属性;  style无法保留
	public static function removeXXS($val){
		// remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed  
		// this prevents some character re-spacing such as <java\0script>  
		// note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs  
		$val = $val ? str_replace("\\","\\\\",$val):'';
		$val = preg_replace('/([\x00-\x08\x0b-\x0c\x0e-\x19])/', '', $val);// 去除逗号;

		// straight replacements, the user should never need these since they're normal characters  
		// this prevents like <IMG SRC=@avascript:alert('XSS')>  
		$search = 'abcdefghijklmnopqrstuvwxyz';
		$search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$search .= '1234567890!@#$%^&*()';
		$search .= '~`";:?+/={}[]-_|\'\\';
		for ($i = 0; $i < strlen($search); $i++) {
			// ;? matches the ;, which is optional 
			// 0{0,7} matches any padded zeros, which are optional and go up to 8 chars 
			// @ @ search for the hex values 
			$val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val); // with a ; 
			// @ @ 0{0,7} matches '0' zero to seven times  
			$val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val); // with a ; 
		}

		// now the only remaining whitespace attacks are \t, \n, and \r 
		$ra1 = array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
		
		$ra1 =  array('javascript', 'vbscript', 'expression','script');// 过多,误判
		$ra2 = array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
		$ra = array_merge($ra1, $ra2);

		$found = true; // keep replacing as long as the previous round replaced something 
		while ($found == true) {
			$val_before = $val;
			for ($i = 0; $i < sizeof($ra); $i++) {
				$pattern = '/';
				for ($j = 0; $j < strlen($ra[$i]); $j++) {
					if ($j > 0) {
						$pattern .= '(';
						$pattern .= '(&#[xX]0{0,8}([9ab]);)';
						$pattern .= '|';
						$pattern .= '|(&#0{0,8}([9|10|13]);)';
						$pattern .= ')*';
					}
					$pattern .= $ra[$i][$j];
				}
				$pattern .= '/i';
				$replacement = substr($ra[$i], 0, 2) . '_' . substr($ra[$i], 2); // add in <> to nerf the tag  
				$val = preg_replace($pattern, $replacement, $val); // filter out the hex tags  
				if ($val_before == $val) {
					// no replacements were made, so exit the loop  
					$found = false;
				}
			}
		}
		
		//屏蔽危险data-url, iframe/a...; src="data:text/html; href="data:text/html,...
		$val = preg_replace("/\s+(src|href)\s*=(\s*[\"']\s*data\s*:\s*(text|application)\/)/i",' _$1_=$2', $val);		
		$val = preg_replace("/\s+srcdoc\s*=/i",' _srcdoc_=', $val);// 屏蔽iframe srcdoc
		return $val;
	}
}
