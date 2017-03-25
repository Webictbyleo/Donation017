<?php
defined('SAFE')or die();
require_once(__DIR__.'/pz_system.php');


	Class PZ_Matrix_Meter extends PZ_system{
		
		private static $matrix;
		private $matrix_stages;
		const bonus = 0;
		private $admin_fee;
		public function __construct(){
			if(!isset(self::$matrix)){
				$db = mspdo::getInstance();
				$conf = $db->rawquery('SELECT pz_matrix AS m,tmbup_gain AS gain FROM `'.config::get()->Dbprefix.'ponzi_system_configuration` LIMIT 1');
							self::$matrix = array_filter(explode("\n",$conf[0]['m']));
			}
		}
		
		public function getReturnByMatrix($amount){
			if(!is_numeric($amount))return false;
			if(!empty(self::$matrix)){
					$matrix = $this->getReturnAll();
					return isset($matrix[$amount]) ? $matrix[$amount] : false;
			}
		}
		
		public function getReturnByPercent($amount){
			$this->getReturnAll();
		}
		
		public function getReturnAfterAdminFee($amount){
			$this->getReturnAll();
		}
		public function addMatrixCode($code){
			if(is_numeric($code) OR !is_string($code))return false;
			$pi = array_filter(explode('\\',$code));
				if(is_array($pi) AND count($pi) > 2){
					if(is_numeric($pi[0]) AND strpos($pi[1],':') !==false){
							$p = !empty($pi[2]) ? $pi[2] : 'Stage '.count($this->matrix_stages)+1;
							$active = (end($pi)==true);
							$fee = is_numeric($pi[3]) ? $pi[3] : 0;
						return $this->addMatrixObject($pi[0],$pi[1],$p,$fee,$active);
					}
				}
			
			
		}
		public function addMatrixObject($amount,$math,$package,$fee=0,$active=true){
			if(!is_numeric($amount) AND !is_string($math) AND !is_string($package) AND !is_numeric($fee) AND !is_bool($active))return false;
			
			$this->matrix_stages[$amount] = array();
			
				$submat = explode(':',$math);
				$isperc = (($perc=trim($submat[0],'%')) !==$submat[0]);
					if($isperc){
						$this->matrix_stages[$amount]['return'] = (($amount / 100)*$perc)+$amount;
					}else{
						$this->matrix_stages[$amount]['return'] = ($submat[0]*$amount);
					}
					$this->matrix_stages[$amount]['matrix'] = $math;
					$this->matrix_stages[$amount]['stage'] = $package;
					$this->matrix_stages[$amount]['math'] = $submat;
					$this->matrix_stages[$amount]['active'] = $active;
					return true;
		}
		public function getReturnAll(){
			if(isset($this->matrix_stages))return $this->matrix_stages;
			$matrix_bal = array();
		$t = count(self::$matrix);
		if($t < 1)return false;
	for($ii=0;$t > $ii;$ii++){
	$pi = array_filter(explode('\\',self::$matrix[$ii]));
	
	$submat = explode(':',$pi[1]);
	$pi[3] = trim($pi[3]);
	if(!is_numeric($pi[3])){
	$pn = $pi[3];	
			}else{
			$pn = 'Stage '.($ii+1);
		}
		$isperc = (($perc=trim($submat[0],'%')) !==$submat[0]);
					if($isperc){
						$return = (($pi[0] / 100)*$perc)+$pi[0];
					}else{
						$return = ($submat[0]*$pi[0]);
					}
		//Admin fee			
	$matrix_bal[$pi[0]] = array(
		'return'=>$return,
		'matrix'=>$pi[1],
		'stage'=>$pn,
		'math'=>$submat,
		'active'=>(end($pi) == true)
	);
	
	}
		$this->matrix_stages = $matrix_bal;		
		return $this->matrix_stages;
		}
		
		//Set activate matrix
			function activate($amount){
				$i = isset($this->matrix_stages[$amount]) ? $this->matrix_stages[$amount] : false;
					if($i !=false){
						$this->activeMatrix = array($amount => $i);
						
					}
			}
	}
?>