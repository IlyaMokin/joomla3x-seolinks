<?php
/**
 * seoLinks
 *
 * @version 1.0.25
 * @package seoLinks
 * @author ZyX (allforjoomla.com)
 * @copyright (C) 2010 by ZyX (http://www.allforjoomla.com)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 *
 * If you fork this to create your own project,
 * please make a reference to allforjoomla.com someplace in your code
 * and provide a link to http://www.allforjoomla.com
 **/
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

class  plgSystemSeolinks extends JPlugin{

	public function onContentPrepare($context, &$row, &$params, $page = 0){
		if ($context == 'com_finder.indexer') return true;
		$slType = $this->params->get('slType', 'all_html');
		if($slType!='com_content') return true;
		if($this->isThisSkipPage()) return true;
		$document = JFactory::getDocument();
		if($document->getType()!='html') return;
		if($row->text=='') return true;
		$newBody = $this->processLinks($row->text);
		if(!is_string($newBody)||$newBody=='') return true;
		else $row->text = $newBody;
	}

	
	function onAfterDispatch(){
		$app = JFactory::getApplication();
		if($app->getName()!='site') return;	
		$document = JFactory::getDocument();
		if($document->getType()!='html') return;
		$slType = $this->params->get('slType', 'all_html');
		if($slType=='com_content') return;
		$task = JRequest::getCmd('task','');
		$format = JRequest::getCmd('format','');
		$tmpl = JRequest::getCmd('tmpl','');
		if($task=='edit'||($format!=''&&$format!='html')||$tmpl!='') return;
		if($this->isThisSkipPage()) return true;
		$body = trim($document->getBuffer('component'));
		if($body=='') return;
		else $newBody = $this->processLinks($body);
		if(!is_string($newBody)||$newBody=='') return false;
		$document->setBuffer( $newBody, 'component');
	}
	
	function processLinks($body){
		static $links, $linksOnPage;
		
		$linx = $this->params->get('linx', '');
		$linxCSS = $this->params->get('linx_class', '');
		$numRepl = (int)$this->params->get('numRepl', 1);
		if(is_null($linksOnPage)) $linksOnPage = 0;
		$maxOnPage = (int)$this->params->get('maxOnPage', 100);
		if($numRepl<1) $numRepl = 1;

		if(!is_array($links)){
			$links = array();
			$tmp = explode("\n",$linx);
			if(!is_array($tmp)) return false;
			$wordFormWildCards = array(
				'\\\.'	=>	'#-#',
				'\\\*'	=>	'#--#',
				'.'	=>	'[a-zа-яіїєґ]?',
				'*'	=>	'[a-zа-яіїєґ]*'
			);
			$clearRegs = array(
				'#-#'	=>	'\\.',
				'#--#'	=>	'\\*'
			);
			$uri = &JURI::getInstance();
			$host = str_replace('http://www.','http://',$uri->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query')));
			$hostW = str_replace('http://','http://www.',$host);
			$hostX = $uri->toString(array('path','query'));
			foreach($tmp as $tmp2){
				$tmp2 = trim($tmp2);
				if($tmp2==''||!preg_match('~=~',$tmp2)) continue;
				$link = explode('=',$tmp2,2);
				$words = trim($link[0]);
				$href = trim($link[1]);
				if($href==''||$words=='') continue;
				if($href==$host||$href==$hostW||$href==$hostX) continue;
				$words = str_replace(', ',',',$words);
				$words = str_replace(' ,',',',$words);
				$words = str_replace(',','|',$words);
				$words = str_replace(array_keys($wordFormWildCards),array_values($wordFormWildCards),addslashes($words));
				$words = str_replace(array_keys($clearRegs),array_values($clearRegs),$words);
				preg_match('~\{([0-9]+)\}~i',$href,$mchs);
				$linkNum = (int)@$mchs[1];
				if($linkNum<=0) $linkNum = $numRepl;
				$href = trim(preg_replace('~\{([0-9]+)\}~i','',$href));
				$links[] = array(
					'words' => $words,
					'href' => $href,
					'maxNum' => $linkNum,
					'hasNum' => 0
				);
			}
		}
		if(count($links)==0) return false;
		if($maxOnPage>0){
			$linksOnPage+= substr_count($body,'<a ');
			if($linksOnPage>=$maxOnPage) return $body;
		}
		$body = preg_replace("~(<\!\-\-seoLinks skip\-\->)(.*?)(<\!\-\-\/seoLinks skip\-\->)~sie",'"<:ZyX>".plgSystemSeolinks::maskContent("\\2")."<:ZyX/>"',$body);
		$body = preg_replace("~(<script)(.*?)(<\/script>)~sie",'"<:ZyX>".plgSystemSeolinks::maskContent("$1$2$3")."<:ZyX/>"',$body);
		$body = preg_replace("~(<\!\-\-)(.*?)(\-\-\>)~sie",'"<:ZyX>".plgSystemSeolinks::maskContent("$1$2$3")."<:ZyX/>"',$body);
		$body = preg_replace("~(<style)(.*?)(<\/style>)~sie",'"<:ZyX>".plgSystemSeolinks::maskContent("$1$2$3")."<:ZyX/>"',$body);
		$body = preg_replace("~(<h[1-6])(.*?)(<\/h[1-6]>)~sie",'"<:ZyX>".plgSystemSeolinks::maskContent("$1$2$3")."<:ZyX/>"',$body);
		$body = preg_replace("~(<a)(.*?)(<\/a>)~sie",'"<:ZyX>".plgSystemSeolinks::maskContent("$1$2$3")."<:ZyX/>"',$body);
		$body = preg_replace("~(<[a-z])(.*?)(>)~sie",'"<:ZyX>".plgSystemSeolinks::maskContent("$1$2$3")."<:ZyX/>"',$body);
		for($i=0;$i<count($links);$i++){
			if($maxOnPage>0 && $linksOnPage>=$maxOnPage) break;
			$link = $links[$i];
			if($link['hasNum']>=$link['maxNum']) continue;
			$replace = '<a'.($linxCSS!=''?' class="'.$linxCSS.'"':'').' href="'.$link['href'].'">$2</a>';
			if($linxCSS!='') $replace = '<span class="'.$linxCSS.'">'.$replace.'</span>';
			$replace = '$1'.$replace.'$3';
			$search = "~([\s\.\,\;\!\?\:\>\(\)\'\"\*\/«])(".$link['words'].")([\*\/\'\"\(\)\<\s\.\,\;\!\?\:»])~siu";
			$body = preg_replace("~(<a)(.*?)(?=<\/a>)(<\/a>)~sie",'"<:ZyX>".plgSystemSeolinks::maskContent("$1$2$3")."<:ZyX/>"',$body);
			$body = preg_replace($search, $replace, $body,$link['maxNum']);
			if($body=='') return false;
			$placedLinks = substr_count($body,'<a ');
			$link['hasNum']+= $placedLinks;
			$linksOnPage+= $placedLinks;
			$links[$i] = $link;
		}
		$body = preg_replace("~<\:ZyX>(.*?)(?=<\:ZyX\/>)<\:ZyX\/>~sie",'plgSystemSeolinks::unmaskContent("$1")',$body);
		$body = preg_replace("~<\:ZyX>(.*?)(?=<\:ZyX\/>)<\:ZyX\/>~sie",'plgSystemSeolinks::unmaskContent("$1")',$body);
		return $body;
	}
	
	function isThisSkipPage(){
		static $result;
		if(is_bool($result)) return $result;
		$skipPages = trim($this->params->get('skipPages', ''));
		$uri = &JURI::getInstance();
		$host = str_replace('http://www.','http://',$uri->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query')));
		$hostW = str_replace('http://','http://www.',$host);
		$hostX = $uri->toString(array('path','query'));
		if($skipPages!=''){
			$skipPagesArray = explode("\n",$skipPages);
			foreach($skipPagesArray as $skipPage){
				$skipPage = trim($skipPage);
				if($skipPage=='') continue;
				if(substr($skipPage,0,1)=='~'){if(preg_match($skipPage,$hostX)){$result = true;return true;}}
				else if($skipPage==$hostX){$result = true;return true;}
			}
		}
		$result = false;
		return $result;
	}
	
	function maskContent($txt){
		$txt = str_replace("\'","'",$txt);
		$result = base64_encode($txt);
		return $result;
	}
	
	function unmaskContent($txt){
		$result = base64_decode($txt);
		return $result;
	}
}
