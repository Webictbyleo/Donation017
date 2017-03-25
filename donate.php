<?php
defined('SAFE')or die();
require_once(__DIR__.'/pz_system.php');



	Class PZ_Matrix_donate extends PZ_system{
		const max_donation = 0;
		private $to;
		private $amount;
		
		
		public function pledgeAndFindReceiver(PZ_Donor &$donor,PZ_Matrix_Meter &$matrix){
				if(isset($matrix->activeMatrix) AND !empty($matrix->activeMatrix)){
					if($donor->canDonate()){
					$pak = $matrix->activeMatrix;
					$pka = key($pak);
						//Check if package is activated
						if($pak[$pka]['active'] !=true){
							throw new Exception('Package not available. Choose another');
						}
						$this->to = $donor->get('id');
						$this->amount = $pka;
						$db = mspdo::getInstance();$p = config::get()->Dbprefix;
						$t = $db->rawquery('SELECT COUNT(id) AS t FROM `'.$p.'donationhub`');
						$is_start = ($t[0]['t'] < 1);
							if($is_start){
								//Merge admin
							$receiver = new PZ_Donor(34);
							
							}else{
								//Find a pending 
							//	$receiver = $this->findReceiver($pka);
								
									return $donor->pledge($matrix,$pka);
								
							}
							
							if(isset($receiver) AND $receiver->canReceive()){
								return $donor->donate($matrix,$receiver,$pka);
							}else{
								return $donor->pledge($matrix,$pka);
							}
						
				}else{
					throw new Exception('Donation denied!');
					}
				}
			
		}
		
		private function findReceiver($amount){
			//Get to the queue
			$db = mspdo::getInstance();
			$p = config::get()->Dbprefix;
			$f = $db->rawquery('SELECT cue.id,hub.uid AS receiver,cue.uid AS donation,hub.id AS hubID ,hub.token,fdata.id AS fid FROM `'.$p.'donation_archive` AS cue LEFT JOIN `'.$p.'donationhub` AS hub ON cue.data=hub.id LEFT JOIN `'.$p.'member_settings` AS config ON hub.uid=config.uid LEFT JOIN `'.$p.'formbuilder_data` AS fdata ON hub.id=fdata.store_id  WHERE cue.type = 1000 AND (config.memon IS NULL OR config.memon=0) AND hub.token IS NOT NULL AND fdata.belong_to=3 AND cue.ack !="'.$this->to.'" AND (cue.uid="'.$amount.'" OR cue.uid > "'.$amount.'") ORDER BY cue.uid DESC LIMIT 1');
			
				if(empty($f)){
					//None to receive incoming fund
					return false;
					
				}
				
				require_once(SITE.DELIMITER_DIR.'plugins/plug_ponziguard/libs/pz_donor.php');
				$getter = new pz_donor($f[0]['receiver']);
				return $getter;
			
		}
	}
?>