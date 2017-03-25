<?php
defined('SAFE')or die();

		class Pz_notification extends PZ_SYSTEM{
				var $from;
				var $to;
				var $subject;
				var $message;
				private $events = array(
					'donation',
					'paid',
					'paired',
					'joined',
					'acapproved',
					'fraudrise',
					'demote'
				);
				private $message_templates = array(
				'paired'=>array(
					'subject'=>'You have been merged to fulfil your donation',
					'message'=>'You were recently merged to pay a participant'
					)
				);
				
				public function SendMessage($email=false){
						$isdemo = isset($this->_message_templates[$this->subject]);
							if($isdemo){
								$this->subject = $this->_message_templates[$this->subject]['subject'];
									if(!isset($this->message)){
									$this->message = $this->_message_templates[$this->subject]['subject'];	
									}
							}
							$crtd = date('Y-m-d H:i:s');
					$db = mspdo::getInstance();
					$d2= $db->insert(Config::get()->Dbprefix.'support_messages',array('msg'=>$this->message,'pto'=>$this->to,'ack'=>'5','subject'=>$this->subject,'created'=>$crtd));
					$sqldump[] = '("5","support_messages","'.$this->to.'","support-message","'.$crtd.'","'.$d2.'")';
					$db->rawquery('INSERT INTO `'.Config::get()->Dbprefix.'formbuilder_data` (belong_to,storage_location,author_id,ack,created,store_id) VALUES '.implode(',',$sqldump).' ');
					if($email ==true AND isset($this->email)){
						$mail = new email;
				$mail->Setsubject($this->subject);
				$mail->setRecipient($this->email);
				$mail->setMessage($this->message);
				$mail->push();
					}
				}
				
				public function logEvent($event){
					if(in_array($event,$this->events)){
						$crtd = date('Y-m-d H:i:s');
						$sqldump = '(30,"'.$event.'","'.$crtd.'","'.$this->subject.'")';
						$db = mspdo::getInstance();
						
						$db->rawquery('INSERT INTO `'.Config::get()->Dbprefix.'donation_archive` (type,data,created,uid) VALUES '.$sqldump.' ');
						$db->getConnection()->commit();
					}
				}
				
				
		}
?>