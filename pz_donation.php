<?php
defined('SAFE')or die();
require_once(SITE.DELIMITER_DIR.'plugins/plug_ponziguard/libs/matrix_meter.php');
require_once(SITE.DELIMITER_DIR.'plugins/plug_ponziguard/libs/notification.php');
		class pz_donation extends pz_system{
			
			private $fid;
			private $hubid;
			private $hasQue;
			private $updated = array();
			private $isSplit;
			private $sharers;
			private $temp_donation;
			function confirm(PZ_donor &$getter){
				if(!$this->isloaded())return false;
				if($getter->get('id') == $this->uid){
					throw new Exception('Operation access denied. Cannot confirm self');
				}
				
				if($getter->get('id') != $this->paid AND $getter->user->ingroups(array(400,500))){
					throw new Exception('Operation access denied. Only the receiving party can confirm');
				}
				$db = mspdo::getInstance();
				if($this->isSplit AND count($this->sharers) > 0){
					array_pop($this->sharers);
					//Next getter
					$db->rawquery('UPDATE `'.config::get()->Dbprefix.'donation_archive` SET data="'.implode('|',$this->sharers).'" WHERE type=222 AND ack='.$this->fid.' ');
					$this->paid = current($this->sharers);
					$this->save();
					return;
				}
				$hq = $this->hasReturnQue();
				$isadmin = $getter->user->ingroups(array(400,500));
				
				$w = $db->rawquery('SELECT SUM(uid) AS amount,ack FROM `'.config::get()->Dbprefix.'donation_archive` WHERE type=1000 AND ack="'.$this->paid.'" OR ack="'.$this->uid.'" GROUP BY ack LIMIT 2');
				
				$wallets = array();
					if(!empty($w)){
			foreach($w as $i => $v){
				$wallets[$v['ack']] = $v['amount'];
			}
					}
					
				//Make sire we aint paying twice
				if($hq AND !$isadmin){
					
					$w = $wallets[$this->paid];
					//Check that incming donation can fit
						if(!empty($w) AND $w > $this->donation){
							
						}else{
							//Canntot proceed with payment
							//Purge
							$this->state = 0;
							$this->paid = NULL;
							$this->save();
							throw new Exception('Receiving party cannot receive payment at the moment. Empty Duplicate payment not allowed or Not enough fund in Wallet');
							return;
						}
				}
				
				//Get the return matrix
			$matrix = new pz_matrix_meter;
			$c = $this->getConfig();
			$m = $matrix->getReturnAll();
			if($c->loose_matrix){
				$proto = current($m);
				$matrix->addMatrixObject($this->donation,$proto['matrix'],'Free Donation');
				$m = $matrix->getReturnAll();
			}
			
			$crtd = date('Y-m-d H:i:s');
			//Log event;
			$notify = new pz_notification;
			$notify->subject = $this->uid;
			$notify->logEvent('paid');
			
				
					
				$this->state = 4;
				$this->save();
				//Update queue;
				if($hq){
					//This is a return payment
					//Load queue
					
					
					
						$wallets[$this->paid] = ($wallets[$this->paid] - $this->donation);
							if($wallets[$this->paid] < 1){
								//If queue amount is finished, remove
								$db->rawquery('DELETE FROM `'.config::get()->Dbprefix.'donation_archive` WHERE type=1000 AND ack='.$this->paid.'');
							}else{
								//Pay and move down
								$db->rawquery('UPDATE `'.config::get()->Dbprefix.'donation_archive` SET uid="'.$wallets[$this->paid].'",created="'.$crtd.'" WHERE type=1000 AND ack='.$this->paid.'');
								
							}
						
					
				}
				
					//If donor has no wallet create one
					if(array_key_exists($this->uid,$wallets)){
						$db->rawquery('UPDATE `'.config::get()->Dbprefix.'donation_archive` SET uid="'.($wallets[$this->uid]+$m[$this->donation]['return']).'",created="'.$crtd.'" WHERE type=1000 AND ack='.$this->uid.'');
					}else{
				$values = '('.$this->hubid.',"'.$m[$this->donation]['return'].'","'.$crtd.'",1000,"'.$this->uid.'")';
				$db->rawquery('INSERT INTO `'.config::get()->Dbprefix.'donation_archive` (data,uid,created,type,ack) VALUES '.$values);
					}
					//If giver has referer
					$giver = new pz_donor($this->uid);
					
					if($c->use_ref_bonus AND $giver->hasReferer()){
						$ref_gain = $c->ref_gain;
						$isperc = (($perc=trim($ref_gain,'%')) !==$ref_gain);
						if($isperc){
							$ref_gain = (($this->donation / 100)*$perc);
						}
							if(is_numeric($ref_gain)){
						$db->rawquery('UPDATE `'.config::get()->Dbprefix.'donation_archive` SET data=data+'.$ref_gain.' WHERE type=300 AND uid='.$giver->referer.' ');
							}
						
					}
					//If donation is split and giver has paid all
					if($this->isSplit){
						$db->rawquery('DELETE FROM `'.config::get()->Dbprefix.'donation_archive` WHERE type=222 AND ack='.$this->fid.' ');
					}
				$db->getConnection()->commit();
				
			}
			function cantPay(PZ_donor &$giver){
				if(!$this->isloaded())return false;
				if($giver->get('id') !== $this->uid AND !$user->user->ingroups(array(400,500))){
					throw new Exception('Operation access denied');
				}
				
				$this->state = 0;
				$this->paid = NULL;
				$this->save();
				$db = mspdo::getInstance();
				$db->rawquery('UPDATE `'.config::get()->Dbprefix.'formbuilder_data` SET created="'.date('Y-m-d H:i:s').'" WHERE id='.$this->fid.' ');
				$db->getConnection()->commit();
				//Purge donation
			}
			
			function mergeWith(PZ_donor &$getter,array $multi_getters=array()){
				if(!$getter->canReceive())return false;
				if(!$this->isloaded())return false;
				
					if($getter->get('id') == $this->uid){
						throw new Exception('Merging to self rejected!');
					}
					$db = mspdo::getInstance();
					$crtd = date('Y-m-d H:i:s');
					
					$this->state = 1;
					$this->paid = $getter->get('id');
					$this->save();
					
					
				$db->rawquery('UPDATE `'.config::get()->Dbprefix.'formbuilder_data` SET created="'.$crtd.'" WHERE id='.$this->fid.' ');
				if(!empty($multi_getters)){
						
					$db->rawquery('INSERT INTO `'.config::get()->Dbprefix.'donation_archive` (data,uid,created,type,ack) VALUES ("'.implode('|',$multi_getters).'",'.$this->uid.',"'.$crtd.'","222","'.$this->id.'")');
				}
				$db->getConnection()->commit();
					$link = 'https://'.SYS_DOMAIN_HOST.'/index.php?app=com_formbuilder&view=item&ack='.$this->id;
				$msg = '<div style="text-align:center;color:black;font-size:24px"><p>Please follow the <a href="'.$link.'">link </a> to  proceed</p><p>Transaction AMOUNT: <strong>'.$this->donation.'</strong></p></div>';
	$notify = new pz_notification;
	$notify->from = config::get()->siteName;
	$notify->to = $this->uid;
	$notify->subject = 'paired';
	$notify->message = $msg;
	$notify->sendMessage(true);
					return true;
			}
			function hasReturnQue(){
				if(!$this->isloaded())return false;
					if(isset($this->hasQue))return $this->hasQue;
				$db = mspdo::getInstance();
				$g = $db->rawquery('SELECT COUNT(id) AS t FROM `'.config::get()->Dbprefix.'donation_archive` WHERE type=1000 AND ack="'.$this->paid.'"');
				return ($this->hasQue = ($g[0]['t'] > 0));
			}
			
			function expire(){
				if(!$this->isloaded())return false;
				
				$this->state = 0;
				$this->paid = NULL;
				$this->save();
				//Log an expired event against donor
				$notify = new pz_notification;
				$notify->subject = $this->uid;
				$db = mspdo::getInstance();
				$db->rawquery('UPDATE `'.config::get()->Dbprefix.'formbuilder_data` SET created="'.date('Y-m-d H:i:s').'" WHERE id='.$this->fid.' ');
				$db->getConnection()->commit();
				$notify->logEvent('expired');
				
			}
			function flag(PZ_donor &$getter){
				if(!$this->isloaded())return false;
				if($getter->get('id') != $this->uid AND $getter->user->ingroups(array(400,500))){
					throw new Exception('Operation access denied. Only the receiving party can flag');
				}
				$this->state = 3;
				$this->save();
				$notify = new pz_notification;
				$notify->subject = $this->uid;
				$notify->logEvent('fraudrise');
				//Log
			}
			
			function expired(){
				
				if(!$this->isloaded())return false;
				$c = $this->getConfig();
				director::profile('timepro');
				$time = new timepro;
				$date = $time->date('Y-m-d H:i:s',strtotime($this->created));
				$time->modify($c->time_left2_pay.' minutes');
				return ($time->isEarlier(date('Y-m-d H:i:s')) ==false);
			}
			
			private function save(){
				$db = mspdo::getInstance();
					if(!empty($this->updated)){
				$do = $db->where('id',$this->hubid)->update(config::get()->Dbprefix.'donationhub',$this->updated);
				$db->getConnection()->commit();
				}
			}
			
			function loadArray(array $a){
				
				$this->setProto('id','int',$a['id'],true);
				$this->setProto('paid',NULL,$a['paid']);
				$this->setProto('uid','int',$a['uid'],true);
				$this->setProto('state','int',$a['state']);
				$this->setProto('donation','int',$a['donation']);
				$this->setProto('created','string',$a['created'],true);
				$this->hubid = $a['hubID'];
				$this->fid = $a['id'];
					if($a['state'] > 0 AND $a['state'] !=4){
				$db = mspdo::getInstance();
				$get = $db->rawquery('SELECT data FROM `'.config::get()->Dbprefix.'donation_archive` WHERE type=222 AND ack='.$this->fid.'');
					if(!empty($get)){
						$this->isSplit = true;
						$sh = array();
						foreach($get as $k => $v){
							$sh = array_merge($sh,explode('|',$v['data']));
						}
						
						$this->sharers = array_unique($sh);
							
						
						//Find temporary donation amount for a shared user
						$get = $db->rawquery('SELECT cue.uid AS amount FROM `'.config::get()->Dbprefix.'donation_archive` AS cue WHERE cue.type=1000 AND cue.ack = '.$a['paid'].' LIMIT 1');
						if(!empty($get)){
							$this->temp_donation = $get[0]['amount'];
						}
						
					}
						}
				
				$this->setFromArray($a);
				parent::setCallback('onBeforeSet','pz_donation::resolveUpdate');
				parent::setCallback('onBeforeGet','pz_donation::refresh');
				return $this;
			}
			
			function loadID($id){
				if(is_numeric($id)){
					$db = mspdo::getInstance();
					$pl = $db->rawquery('SELECT fdata.id,hub.paid,hub.uid,hub.state,hub.donation,fdata.created,hub.id AS hubID FROM `'.config::get()->Dbprefix.'formbuilder_data` AS fdata LEFT JOIN `'.config::get()->Dbprefix.'donationhub` AS hub ON fdata.store_id=hub.id WHERE fdata.belong_to=3 AND fdata.id='.$id.' ');
						if(!empty($pl)){
							$this->loadArray($pl[0]);
						}
						return $this;
				}
				return $this;
			}
			
			function isloaded(){
				return isset($this->hubid);
			}
			public  function resolveUpdate($name,$value){
				
				if(in_array($name,array('created','uid','donation')))return;
				
					
				$this->updated[$name] = $value;
					
			}
			
			public function refresh($name){
				if($name =='donation' AND $this->isSplit){
					$this->donation = $this->temp_donation;
				}
			}
			function followRedirect(){
				$link = director::protocolAndHost().'/index.php?app=com_formbuilder&view=item&ack='.$this->id;
				director::force_redirect($link);
			}
			
			function trash(PZ_donor &$user){
					if(!$this->isloaded())return false;
				$db = mspdo::getInstance();
				$db->rawquery('DELETE FROM `'.config::get()->Dbprefix.'donationhub` WHERE id='.$this->hubid.'');
				$db->rawquery('DELETE FROM `'.config::get()->Dbprefix.'formbuilder_data` WHERE id='.$this->id.'');
				$db->getConnection()->commit();
			}
			
		}
?>