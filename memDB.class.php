<?php
/**********************
	pdoClass with memcached
	파 일 명 : memDB.class.php
	작 성 일 : 2014.07.24
	작 성 자 : 한햇빛
*** 개발중 고려 사항*******

	1. PDO state($ps)를 캐쉬데이터로  저장하고 싶으나,
		[You cannot serialize or unserialize PDOStatement instances ..]
		> 처리 : serialize가 안됨... 현재는 fetch 결과 array로 저장 및 반환..
			
	2.쿼리 자체를 key로 쓰게 되면 memcached 키 길이가 250자 제한에 걸릴수 있음 
		> 처리 : md5로 해결.

	3.insert,upadte,delete 문 실행시 캐싱된 데이터가 출력된 경우 최신캐시로 갱신
	> 기존 캐시 초기화 cache_flush_all()

	4.memcached 다중 서버환경에서의 처리 - moxi 

**********************/
class dbClass{
	private $cache;
	private $memObj;
	private $db_conn=NULL;
	private $rowcount=0;

	# config
	var $key_prefix="syl_cache_";		//cache 키값
	var $memhost="localhost";			//memcache 호스트
	var $memPort = 11211;				//memcache 포트
	var $memEnable = TRUE;			//캐싱 사용여부
	var $cachetime = 60;					//캐싱 유지시간(number of seconds)
	var $cachezlip      = FALSE;			//캐싱 압축 여부(false 추천)	
	var $chageFlush  = FALSE;			// select이외의 쿼리 동작시 캐시 초기화
	
	function __construct(){
		if($this->memEnable) $this->memConnect();
	}
	
	//memcache 접속
	private function memConnect(){
		try{
			$this->cache = new Memcache;
			@$this->memObj = $this->cache->connect($this->memhost, $this->memPort);
			if(!$this->memObj){
				//연결 실패시 db only
				$this->memEnable = FALSE;
			}
		}catch(Exception $e){
			//error 발생시 db only
			$this->memEnable = FALSE;
		}
	}
	
	//database 접속
	private function db_connect($database){
		//dbConnect
		$db_host = $database['db_host'];
		$db_port = $database['db_port'];
		$db_user = $database['db_user'];
		$db_pass = $database['db_pass'];
		$db_name = $database['db_name'];
		$db_characterset = $database['db_characterset'];
		try {
			$this->db_conn=new PDO('mysql:host='.$db_host.';dbname='.$db_name.'; port='.$db_port , $db_user, $db_pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
			$this->db_conn->exec('SET NAMES "'.$db_characterset.'"');
		}catch (Exception $exc) {
			$this->show_err('Connection Error', $exc->getMessage());
			exit();
		}
	}
	
	/**
	@pdo_query
	{Array}  database  : 접속정보 배열
	{String} sql	 	  	  :  쿼리 문자열
	{Array}  bind_data: 바인딩 배열
	*/
	public function pdo_query($database,$sql,$bind_data = array()){
		if(empty($sql)){
			$this->show_err('Query Error', "none query");
			exit;
		}
		
		$this->db_connect($database);
	
		//memCache 사용여부에 따라 처리
		if($this->memEnable===TRUE){
			if($this->is_select($sql)){
				$cache_sql = $this->interpolateQuery($sql,$bind_data);
				$cache_data = $this->mem_get($cache_sql);
				if(empty($cache_data)){
				/**********************
				CASE1 : 결과반환 후 캐싱처리
				**********************/
					$ps = $this->query(null,$sql,$bind_data);
					$resultArr = array();
					while($data = $ps->fetch(PDO::FETCH_ASSOC)){
						array_push($resultArr,$data);
					}
					$this->mem_set($cache_sql,$resultArr);	//캐싱
					echo "캐싱데이터 없음. 결과반환 후 캐싱<br/>";
					echo "cache key :".$this->key_prefix.md5($cache_sql)."<br/>";
					$this->rowcount = sizeof($resultArr);
					return$resultArr;
				}else{
				/**************************
				CASE2 : 캐싱 데이터에서 바로 출력
				**************************/
					echo "캐싱 메모리에서 불러옴<br/> ";
					echo "cache key :".$this->key_prefix.md5($cache_sql)."<br/>";
					$this->rowcount = sizeof($cache_data);
					return $cache_data;
				}
			}else{
				/********************************************
				CASE3 : 캐싱은 사용하지만 셀렉트 쿼리가 아님(캐싱하지 않음)
				*********************************************/
				//캐싱 초기화
				if($chageFlush){
					$this->cache_flush_all();
					echo "캐싱은 사용하지만 select 쿼리가 아님. 전체 캐싱 초기화";
				}else{
					echo "캐싱은 사용하지만 select 쿼리가 아님.";
				}
				return $this->query(null,$sql,$bind_data);
			}
		}else{
				/**************************
				CASE4 : 캐싱 사용하지 않음
				**************************/
			if($this->is_select($sql)){
				echo "캐싱 사용안함";
				$ps = $this->query(null,$sql,$bind_data);
				$resultArr = array();
				while($data = $ps->fetch(PDO::FETCH_ASSOC)){
						array_push($resultArr,$data);
				}
				return $resultArr;
			}else{
				echo "select 쿼리도 아니고 캐싱도 사용 안함.";
				return $this->query(null,$sql,$bind_data);
			}
		}
	}
	
	/**
		@query
		pdo_query에서 호출 -> 실제 쿼리 수행부분
		특정 쿼리 캐싱없이 바로 pdo_query사용을 위해 public 접근가능
		바로 query 호출시에는 $database 연결정보 배열이 넘어와야 함
	*/
	public function query($database=null,$sql,$bind_data){
		if(!($database===null)){
			$this->db_connect($database);
		}
		try {
				$ps = $this->db_conn->prepare($sql);
				$ps->execute($bind_data);
			} catch (PDOException $e) {	
				$this->show_err('Error', $e->getMessage());
				exit;
			}
			return $ps;
	}

	//전체 캐싱 초기화
	public function cache_flush_all(){
		$this->cache->flush();
	}
	
	private function memClose(){
		$this->cache->close($this->memObj);
	}
	
	 private function mem_set($key,$cache){
		$key=$this->key_prefix.md5($key);
		$this->cache->set($key,$cache,$this->cachezlip,$this->cachetime);
	}

	private function mem_get($key){
		$key=$this->key_prefix.md5($key);
		return $this->cache->get($key);
	}
	
	//셀렉트 쿼리 여부 반환
	private function is_select($query){
		$t_sql = strtolower(ltrim($query));
		if(strpos($t_sql,'select')===0){
			return true;
		}else{
			return false;
		}
	}
	
	//공통 에러 출력
	public function show_err($sql,$sqlerr){
		echo '<div><strong>SQL:</strong> '.$sql.'<br>';
		echo '<strong>SQL Error:</strong> '.$sqlerr.'<div>';
		return FALSE;
	}
	
	//pdo_query 문자열 변환
	private function interpolateQuery($query, $params){
		$keys = array();
		foreach ($params as $key => $value){
			if (is_string($key)) {
				$keys[] = '/:'.$key.'/';
			} else {
				$keys[] = '/[?]/';
			}
		}
		$query = preg_replace($keys, $params, $query, 1, $count);
		return $query;
	}

	 function __desctruct(){
		 $this->db_conn=NULL;
		 if($this->memEnable){
			$this->memClose();
		 }
	}
}
?>