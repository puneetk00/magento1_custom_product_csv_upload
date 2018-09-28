<?php
	
	# File: app/code/local/MyCompany/MyModule/Exception.php
class Synapse_Productimport_Exception_Exception extends Mage_Core_Exception
{
	public static function exception($module = 'Mage_Core', $message = '', $code = 0)
	{
		$className = $module . '_Exception';
		return new $className($message, $code);
	}
}