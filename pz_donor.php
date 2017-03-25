<?php
defined('SAFE')or die();
require_once(__DIR__.'/pz_system.php');
require_once(SITE.DELIMITER_DIR.'plugins/plug_ponziguard/libs/notification.php');
	 Class PZ_Donor Extends PZ_system {
		 
		
			private static $pz_safe_bin = array();
		
		
		public function __construct($id=NULL){
				
			parent::setCallback('onBeforeSet',function($name,$value)use($id){
					if(isset(self::$pz_safe_bin[$id][$name]))return;
				self::$pz_safe_bin[$id][$name] = $value;
			});
			parent::setCallback('onBeforeGet',function($name)use($id){
					if(!isset(self::$pz_safe_bin[$id][$name]))return;
				$this->{$name} = self::$pz_safe_bin[$id][$name];
			});
				if(array_key_exists($id,self::$pz_safe_bin)){
					
					return;
				}
				$usr = member::user($id);
				
					if($usr instanceof User){
						
			$this->id = $usr->get('id');
			$this->user = $usr;
			$db = mspdo::getInstance();
			$find = $db->rawquery('SELECT logger.type,data,uid FROM `'.config::get()->Dbprefix.'donation_archive` AS logger  WHERE (logger.type IN(101,300,22,33) AND logger.uid=?) OR (type=101 AND data='.$this->id.') GROUP BY data',array($this->id));
			//300 = wallet,22 =refcode,33 = likes
			
			if(!empty($find)){
				
					
						$tt = count($find);
							for($i=0;$tt > $i;$i++){
								$tp = $find[$i]['type'];
								if($tp==22 AND !isset($this->refcode)){
									$this->refcode = $find[$i]['data'];
								}
								if($tp==300){
									$this->wallet = !empty($find[$i]['data']) ? $find[$i]['data'] : 0;
								}
								if($tp==33){
									$this->likes = !empty($find[$i]['data']) ? $find[$i]['data'] : 0;
								}
								if($tp==101 AND $this->id != $find[$i]['uid']){
									$this->referer = $find[$i]['uid'];
								}
								if($tp==101 AND $this->id == $find[$i]['uid']){
									$this->referals++;
									$referelsIDs[] = $find[$i]['data'];
								}
							}
							if(isset($referelsIDs)){
					$this->referelsIDs = $referelsIDs;
							}
			
					
				
			}
				$pl = $db->rawquery('SELECT fdata.id,hub.paid,hub.uid,hub.state,hub.donation,fdata.created,hub.id AS hubID FROM `'.config::get()->Dbprefix.'formbuilder_data` AS fdata LEFT JOIN `'.config::get()->Dbprefix.'donationhub` AS hub ON fdata.store_id=hub.id WHERE fdata.belong_to=3 AND (hub.state IS NULL  OR hub.state < 3) AND (hub.uid='.$this->id.' OR (hub.paid='.$this->id.' OR hub.paid IS NULL))  ORDER BY fdata.id DESC ');
				 
				$d = array();
				require_once(__DIR__.'/pz_donation.php');
					if(!empty($pl)){
						$t = count($pl);
						for($i=0;$t > $i;$i++){
							if($pl[$i]['state'] == NULL OR $pl[$i]['state']==0 AND $pl[$i]['uid'] ==$this->id){
								$this->pending_payment++;
							}
							
								if($pl[$i]['uid'] ==$this->id){
							$pledges[] = $pl[$i];
							$ds = new pz_donation;
							$d[$pl[$i]['id']] = $ds->loadArray($pl[$i]);
								}
							if($pl[$i]['state'] ==4 AND !in_array($pl[$i]['paid'],array(NULL))){
								$this->payins = $this->payins+$pl[$i]['donation'];
							}
							if($pl[$i]['uid'] == $this->id AND $pl[$i]['state']==4){
								$this->payouts = $this->payouts+$pl[$i]['donation'];
							}
							if($pl[$i]['state'] ==1){
								$this->waiting_payment++;
							}
							if($pl[$i]['state'] ==2){
								$this->waiting_confirmation++;
							}
							if($pl[$i]['state'] ==3){
								$this->invalid_payment++;
							}
							if($pl[$i]['paid'] ==$this->id AND $pl[$i]['uid'] !=$this->id AND $pl[$i]['state']!=4){
								$this->incoming_payment++;
								$is = $ds = new pz_donation;
								$in[$pl[$i]['id']] = $ds->loadArray($pl[$i]);
							}
							
						}
							if(isset($in)){
								$this->incoming_donations = $in;
							}
						$this->donations = $d;
						$this->lastpay = $pledges[0];
					}
			$acc = $db->rawquery('SELECT memon,approved,phone,accname,bankname,accno FROM `'.config::get()->Dbprefix.'member_settings` AS config LEFT JOIN `'.config::get()->Dbprefix.'members` AS usr ON config.uid=usr.id WHERE config.uid='.$this->id.' LIMIT 1');
				if(!empty($acc)){
					$this->approved = ($acc[0]['approved']==1);
					$this->locked = ($acc[0]['memon']==1);
					$this->bank = $acc[0]['bankname'];
					$this->accountNo = $acc[0]['accno'];
					$this->accountName = $acc[0]['accname'];
					$this->phone = $acc[0]['phone'];
					
				}
				$in = $db->rawquery('SELECT COUNT(id) AS t FROM `'.config::get()->Dbprefix.'support_messages` WHERE pto='.$this->id.' AND   (`read` IS NULL OR `read` !=1)');
				$this->inbox = $in[0]['t'];
			
					}
					
					
		}
		function islocked(){
			return ($this->locked ==true);
		}
		function isapproved(){
			return ($this->approved ==true);
		}
		function canDonate(){
			
			if($this->islocked())return false;
			$c = $this->getConfig();
			
			if($c->use_matrix_stage){
				
				if(!$this->lastpay)return true;
					return ($this->lastpay['state'] >=4);
			}else{
				return true;
			}
			
			
		}
		
		function canReceive(){
			//Bank details are correct
			return (!empty($this->accountNo) AND !empty($this->accountName) AND $this->bank AND !empty($this->phone));
		}
		
		function canReceiveFrom(PZ_donor &$donor){
			return ($donor->id !=$this->id AND $donor->islocked() ==false);
		}
		
		function hasReferer(){
			return (isset($this->referer) AND !empty($this->referer) AND is_numeric($this->referer));
		}
		public function pledge(pz_matrix_meter &$matrix,$amount){
			if(!is_numeric($amount)){
				throw new Exception('Enter a valid amount');
			}
			$pledge = false;
			
			return $this->donate($matrix,$pledge,$amount);
		}
		function donate(pz_matrix_meter &$matrix,&$receiver,$amount){
				
				if($receiver !==false AND !is_a($receiver,pz_donor)){
					throw new Exception('Invalid donor object');
				}
					if($receiver !==false AND $this->id == $receiver->id){
						throw new Exception('Donation to self rejected!');
					}
						if($this->canDonate() !==true){
							throw new Exception('Cannot donate at the moment. Try later');
						}
					
					$m = $matrix->getReturnByMatrix($amount);
						if($m==false){
							throw new Exception('Amount not available');
						}
						$c = $this->getConfig();
						
						if($c->use_matrix_stage){
							//Check if last donation is fulfiled
							
							if(!empty($this->lastpay)){
								if($this->lastpay['state'] !=4){
									throw new Exception('You are yet to complete your last donation('.number_format($this->lastpay['donation']).') & cannot continue');
								}
							}
						}
						$pledgeOrDonate = 1;
						if($receiver !==false){
							$rc = $receiver->id;
						}else{
							$receiver = NULL;
							$pledgeOrDonate = 0;
						}
						//Donate
						$db = mspdo::getInstance();
						$fdata = $db->table(config::get()->Dbprefix.'formbuilder_data');
						$hub = $db->table(config::get()->Dbprefix.'donationhub');
						$a = array(
							'ack'=>3,
							'donation'=>$amount,
							'token'=>(md5(HTTP_REQUEST_KEY.microtime())),
							'state'=>$pledgeOrDonate,
							'paid'=>$rc,
							'uid'=>$this->id
							);
							$hub->insert($a);
							$hubid= $db->getConnection()->lastInsertId();
							$crtd = date('Y-m-d H:i:s');
						$fdata->insert(array(
							'belong_to'=>3,
							'storage_location'=>'donationhub',
							'author_id'=>$this->id,
							'store_id'=>$hubid,
							'created'=>$crtd,
							'ack'=>'confirm'
							));
								$fid= $db->getConnection()->lastInsertId();
							
						//Load donations
						$db->getConnection()->commit();
						$a['id'] = $fid;
						$a['hubID'] = $hubid;
						$a['created'] = $crtd;
						$fund = new pz_donation;
						$fund->loadArray($a);
						$d = $this->donations;
						$d[$fid] = $fund;
						$this->donations = $d;
						
			if($pledgeOrDonate ===1){
				$link = 'https://'.SYS_DOMAIN_HOST.'/index.php?app=com_formbuilder&view=item&ack='.$fid;
				$msg = '<div style="text-align:center;color:black;font-size:24px"><p>Please follow the <a href="'.$link.'">link </a> to  proceed</p><p>Transaction ID: <strong>'.$a['token'].'</strong></p></div>';
	$notify = new pz_notification;
	$notify->from = config::get()->siteName;
	$notify->to = $this->id;
	$notify->subject = 'paired';
	$notify->message = $msg;
	$notify->email = $this->user->get('email');
	$notify->sendMessage(true);
			}
						//Log events
						return $fund;
			
		}
		
		function prepareDonation(pz_matrix_meter &$matrix,$activate){
			$c = $this->getConfig();
			$m = $matrix->getReturnAll();
			if($c->loose_matrix){
				$proto = current($m);
				$matrix->addMatrixObject($activate,$proto['matrix'],'Free Donation');
				$m = $matrix->getReturnAll();
			}else{
				if(!array_key_exists($activate,$m)){
					throw new Exception('Invalid package. Please choose another package');
				}
			}
				
			$matrix->activate($activate);
			return $matrix;
		}
		
		public function get($n){
			if(isset($this->{$n}))return $this->{$n};
			return parent::get($n);
		}
		
		protected function lockDonor(){
			$db = mspdo::getInstance();
			$db->rawquery('UPDATE `'.config::get()->Dbprefix.'member_settings` SET memon=1 WHERE uid='.$this->id.'');
			$db->getConnection()->commit();
		}
		
		protected function unlockDonor(){
			$db = mspdo::getInstance();
			$db->rawquery('UPDATE `'.config::get()->Dbprefix.'member_settings` SET memon=0 WHERE uid='.$this->id.'');
			$db->getConnection()->commit();
		}
		
		public function demote(){
			$c = $this->getConfig();
			$db = mspdo::getInstance();
			$db->rawquery('UPDATE `'.config::get()->Dbprefix.'donation_archive` SET data=data-'.$c->tmbup_gain.' WHERE type=33 AND uid='.$this->id.'');
			$db->getConnection()->commit();
		}
		
	}
	
?>