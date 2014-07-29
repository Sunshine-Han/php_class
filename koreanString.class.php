<?
/****
	한글 특수 처리 클래스
	작 성 일 : 2014-07-29
	작 성 자 : 한햇빛
	설      명 : 1.종성에 따른 [을,를] [이,가] [은,는] [와,과] 구분함수
					2.초성검색 where절 구성
****/
class koreanClass{
	var $cho = array("ㄱ","ㄲ","ㄴ","ㄷ","ㄸ","ㄹ","ㅁ","ㅂ","ㅃ","ㅅ","ㅆ","ㅇ","ㅈ","ㅉ","ㅊ","ㅋ","ㅌ","ㅍ","ㅎ");					//초성
	var $jung = array("ㅏ","ㅐ","ㅑ","ㅒ","ㅓ","ㅔ","ㅕ","ㅖ","ㅗ","ㅘ","ㅙ","ㅚ","ㅛ","ㅜ","ㅝ","ㅞ","ㅟ","ㅠ","ㅡ","ㅢ","ㅣ");  //중성
	var $jong = array("","ㄱ","ㄲ","ㄳ","ㄴ","ㄵ","ㄶ","ㄷ","ㄹ","ㄺ","ㄻ","ㄼ","ㄽ","ㄾ","ㄿ","ㅀ","ㅁ","ㅂ","ㅄ","ㅅ","ㅆ","ㅇ","ㅈ","ㅊ","ㅋ"," ㅌ","ㅍ","ㅎ"); //종성

	private function utf8_strlen($str) { return mb_strlen($str, 'UTF-8'); }
	private function utf8_charAt($str, $num) { return mb_substr($str, $num, 1, 'UTF-8'); }
	private function utf8_ord($ch) {
		$len = strlen($ch);
		if($len <= 0) return false;
		$h = ord($ch{0});
		if ($h <= 0x7F) return $h;
		if ($h < 0xC2) return false;
		if ($h <= 0xDF && $len>1) return ($h & 0x1F) <<  6 | (ord($ch{1}) & 0x3F);
		if ($h <= 0xEF && $len>2) return ($h & 0x0F) << 12 | (ord($ch{1}) & 0x3F) << 6 | (ord($ch{2}) & 0x3F);          
		if ($h <= 0xF4 && $len>3) return ($h & 0x0F) << 18 | (ord($ch{1}) & 0x3F) << 12 | (ord($ch{2}) & 0x3F) << 6 | (ord($ch{3}) & 0x3F);
		return false;
	}

	private function utf8_last_jong($str){
		$code = $this->utf8_ord($this->utf8_charAt($str,$this->utf8_strlen($str)-1))-44032;
		if($code > -1 && $code < 11172) {         
			$jong_idx = $code % 28;
			if(empty($this->jong[$jong_idx])) return false;
			else return true;
		}else return false;
	}


	//모음 구성 여부 판단
	private function linear_hangul_check($str) {
			if(empty($str) || $str=="" ) return false;
			for ($i=0; $i<$this->utf8_strlen($str); $i++) {
			$code = $this->utf8_ord($this->utf8_charAt($str, $i)) - 44032;
			if ($code > -1 && $code < 11172) {
				return false;
			}
		}
		return true;
	}

	private function getWhere_hangul($colum,$word,$index=1){
		$where="";
		if($word == 'ㄱ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^(ㄱ|ㄲ)' OR ( substring(".$colum.",".$index.",1) >= '가'and  substring(".$colum.",".$index.",1) < '나' )) "; 
		}else if($word == 'ㄴ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㄴ' OR (  substring(".$colum.",".$index.",1) >= '나'and  substring(".$colum.",".$index.",1) < '다' )) "; 
		}else if($word == 'ㄷ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^(ㄷ|ㄸ)' OR (  substring(".$colum.",".$index.",1) >= '다'and  substring(".$colum.",".$index.",1) < '라' )) "; 
		}else if($word == 'ㄹ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㄹ' OR (  substring(".$colum.",".$index.",1) >= '라'and  substring(".$colum.",".$index.",1) < '마' )) "; 
		}else if($word == 'ㅁ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㅁ' OR (  substring(".$colum.",".$index.",1) >= '마'and  substring(".$colum.",".$index.",1) < '바' )) "; 
		}else if($word == 'ㅂ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㅂ' OR (  substring(".$colum.",".$index.",1) >= '바'and  substring(".$colum.",".$index.",1) < '사' )) "; 
		}else if($word == 'ㅅ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^(ㅅ|ㅆ)' OR (  substring(".$colum.",".$index.",1) >= '사'and  substring(".$colum.",".$index.",1) < '아' )) "; 
		}else if($word == 'ㅇ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㅇ' OR (  substring(".$colum.",".$index.",1) >= '아'and  substring(".$colum.",".$index.",1) < '자' )) "; 
		}else if($word == 'ㅈ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^(ㅈ|ㅉ)' OR (  substring(".$colum.",".$index.",1) >= '자'and  substring(".$colum.",".$index.",1) < '차' )) "; 
		}else if($word == 'ㅊ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㅊ' OR (  substring(".$colum.",".$index.",1) >= '차'and  substring(".$colum.",".$index.",1) < '카' )) "; 
		}else if($word == 'ㅋ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㅋ' OR (  substring(".$colum.",".$index.",1) >= '카'and  substring(".$colum.",".$index.",1) < '타' )) "; 
		}else if($word == 'ㅌ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㅌ' OR (  substring(".$colum.",".$index.",1) >= '타'and  substring(".$colum.",".$index.",1) < '파' )) "; 
		}else if($word == 'ㅍ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㅍ' OR (  substring(".$colum.",".$index.",1) >= '파'and  substring(".$colum.",".$index.",1) < '하' )) "; 
		}else if($word == 'ㅎ'){ 
			$where = " and (substring(".$colum.",".$index.",1) RLIKE '^ㅎ' OR (  substring(".$colum.",".$index.",1) >= '하')) "; 
		}else{
			$where = " and ".$colum." like '%".$word."%'";
		}
		return $where;
	}
	
	//초성검색
	public function han_where($colum,$str){
		$whereStr="";
		if(empty($str) || $str=="" ) return $whereStr;
		$str = trim($str);
		if($this->linear_hangul_check($str)){
			$wordsize = mb_strlen($str,'UTF-8');
			for($mm=1;$mm<=$wordsize;$mm++){
				$whereStr.= $this->getWhere_hangul($colum,$this->utf8_charAt($str,$mm-1), $mm);
			}
		}else{
			$whereStr = ' and '.$colum.' like "%'. $str.'%"';
		}
		return $whereStr;
	}

	//을,를 구분
	public function han_el($string)
	{ if ($this->utf8_last_jong($string)) return $string."을"; else return $string."를"; }

	//이,가 구분
	public function han_iga($string)
	{ if ($this->utf8_last_jong($string)) return $string."이"; else return $string."가"; }

	//은,는 구분
	public function han_enun($string)
	{ if ($this->utf8_last_jong($string)) return $string."은"; else return $string."는"; }

	//과,와 구분
	public function han_gwawa($string)
	{ if ($this->utf8_last_jong($string)) return $string."과"; else return $string."와"; }


}
?>