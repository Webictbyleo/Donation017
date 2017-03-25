<?php
defined('SAFE')or die();

	 Class PZ_SYSTEM extends options{
			private static $activeConfig;
			const PZ_SYSTEM_TYPE = 'GH';// GH|Donation
			protected function getConfig(){
					if(isset(self::$activeConfig))return self::$activeConfig;
		$db = mspdo::getInstance();
		$conf = $db->rawQuery('SELECT * FROM `'.Config::get()->Dbprefix.'ponzi_system_configuration` LIMIT 1');
		self::$activeConfig = (object)$conf[0];
			return self::$activeConfig;
			}
			
			function pz_system(){
				
			}
			
			
			
	}
?>