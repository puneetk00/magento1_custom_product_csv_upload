<?php
class Synapse_Productimport_Adminhtml_TController extends Mage_Adminhtml_Controller_Action
{

	
	/**
	* isAllowed function give permition for allow access to user
	*/
	protected function _isAllowed()
	{
		//return Mage::getSingleton('admin/session')->isAllowed('productimport/productimportbackend');
		return true;
	}
	
	public function IndexAction()
	{
	
		// Read CSV and make array row wise. 							
		$header = array();
		$row = 1;
		
		if(($handle = fopen(Mage::getBaseDir("base")."/alp-import/AllDBInfoALP_PRC_R044_100.csv", "r")) !== FALSE)
		{
		  while (($data = fgetcsv($handle, 5000, ",","\"")) !== FALSE) 
		  {
			if($row==1){ $header = $data; $row++; continue;  }
			$d = array_combine($header, $data);
			$product_data[] = array_combine($header, $data);
			$configproduct_data["{$d['Style']}"] = array_combine($header, $data);
		  }
		  fclose($handle);
		}
		
		// Simple product price udpate only
		foreach($product_data as $value){
			
			if($id = Mage::getModel('catalog/product')->getIdBySku(trim($value["Item Number"]))){
				$product = Mage::getModel('catalog/product')->load($id);
			}else{
				continue;
			}
			
			$new_price = array(
				array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 1, 'price'=>$value["Piece"] ),
				array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 12, 'price'=>$value["Dozen"] ),
				array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 24, 'price'=>$value["Case"] )
			);
						
			//Add tier group price
			$product->setData('tier_price', $new_price);
			$product->setPrice($value["Retail"]);
			
			try{
				$product->save();
			}catch(Exception $e){
				Mage::log($e, null, "stock_update_error.log");
			}
		}
		
		// Configurable product price udpate only
		foreach($configproduct_data as $value){
			if($id = Mage::getModel('catalog/product')->getIdBySku($value["Style"])){
				$product = Mage::getModel('catalog/product')->load($id);
			}else{
				continue;
			}
			
			$new_price = array(
				array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 1, 'price'=>$value["Piece"] ),
				array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 12, 'price'=>$value["Dozen"] ),
				array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 24, 'price'=>$value["Case"] )
			);
						
			//Add tier group price
			$product->setData('tier_price', $new_price);
			$product->setPrice($value["Retail"]);
			
			try{
				$product->save();
			}catch(Exception $e){
				Mage::log($e, null, "stock_update_error.log");
			}
		}
		
		
	
	}
	
	
	
	public function testIndexAction()
	{
	
		echo "<pre>";
		// Read CSV and make array row wise. 							
		$header = array();
		$row = 1;
		
		if(($handle = fopen(Mage::getBaseDir("base")."/alp-import/inventory-alp.txt", "r")) !== FALSE)
		{
		  while (($data = fgetcsv($handle, 5000, ",","\"")) !== FALSE) 
		  {
			if($row==1){ $header = $data; $row++; continue;  }
			$product_data[] = array_combine($header, $data);
		  }
		  fclose($handle);
		}
		
		
		
		$resource = Mage::getSingleton('core/resource');
		$readConnection = $resource->getConnection('core_write');
		
		
		
		foreach($product_data as $stock){
			if($stock["GD"] >= 1){
				$is_instock = 1;
			}else{
				$is_instock = 0;
			}
			$query = "UPDATE `cataloginventory_stock_item` SET 
			`manage_stock` = 1,
			`use_config_manage_stock` = 0,
			`qty` = {$stock["GD"]},
			`is_in_stock` = {$is_instock}
			WHERE `cataloginventory_stock_item`.`product_id` = (SELECT entity_id FROM `catalog_product_entity` WHERE sku = \"{$stock["Item Number"]}\")";
			print_r($query);
			try{
				$readConnection->query($query);
			}catch(Exception $e){
				Mage::log($e, null, "stock_update_error.log");
			}
		}
		print_r($product_data);		
	
	}
	
}