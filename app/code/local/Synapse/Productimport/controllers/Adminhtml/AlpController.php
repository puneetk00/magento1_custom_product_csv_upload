<?php
class Synapse_Productimport_Adminhtml_AlpController extends Mage_Adminhtml_Controller_Action
{

	
	/**
	* isAllowed function give permition for allow access to user
	*/
	protected function _isAllowed()
	{
		//return Mage::getSingleton('admin/session')->isAllowed('productimport/productimportbackend');
		return true;
	}
	

	/*
	* Index Action
	*/
	public function indexAction()
    {
       $this->loadLayout();
	   $this->_title($this->__("Import Custom Product CSV"));
	   $this->renderLayout();
    }
	
	public function importajaxAction(){
		$this->loadLayout();
		$this->_title($this->__("Import Product CSV"));
		$this->renderLayout();
	}
	
	public function importcountajaxAction(){
		
		$param = Mage::app()->getRequest()->getParam("upload_pr");
		$setcsv = Mage::getSingleton("adminhtml/session");
		$data = $setcsv->getCustomCsv();
		if($param >= count($data)-1){
			try{
				$this->createconfigurableproduct($data[$param]);
				$returnData["message"] = "done";
				$returnData["product_id"] = $data[$param]["Style"];
			}catch(Exception $e){
				$returnData["product_id"] = $data[$param]["Style"];
				$returnData['error'] = $e->getMessage();
				$returnData["message"] = "done";
			}
			
		}else{
			try{
				$this->createconfigurableproduct($data[$param]);
				$returnData["message"] = "continue";
				$returnData["product_id"] = $data[$param]["Style"];
			}catch(Exception $e){
				$returnData["product_id"] = $data[$param]["Style"];
				$returnData['error'] = $e->getMessage();
				$returnData["message"] = "continue";
			}
			
		}
		
		
		echo json_encode($returnData);
		
	}
	
	
	/**
	* Import Action 
	*/
	public function importAction()
    {
		try{
			$this->createproduct();
			Mage::getSingleton("adminhtml/session")->addSuccess("Ready do import"); 
		}catch(Exception $e){
			Mage::getSingleton("adminhtml/session")->addError("Error ". $e->getMessage()); 
		}
		catch(Synapse_Productimport_Exception $e){
			Mage::getSingleton("adminhtml/session")->addError("Error s". $e->getMessage()); 
		}
		
		$this->_redirect("*/*/importajax");
    }
	
	
	/*
	*	creaeproduct function would create configurable product direct use csv data
	*/ 
	private function createproduct()
	{
		// Read CSV and make array row wise. 							
		$header = array();
		$row = 1;
		$errors = array();
		$error_empty = array();
		$check_sku = array();
		$error = array();
		$count = 2;
		
		
		$resource = Mage::getSingleton('core/resource');
		$readConnection = $resource->getConnection('core_read');
		$query = 'SELECT `A`.* , `B`.`Retail`, `B`.`Case Qty`, `B`.`Piece`, `B`.`Case`, `B`.`Dozen` FROM `AllDBInfoALO_Prod` A left join `AllDBInfoALP_PRC_R044` B on `A`.`Item Number` = `B`.`Item Number` group by `A`.`Style`';
		$product_data = $readConnection->fetchAll($query);
		
		
		
		// print_r($product_data); 
		
		// die;
		
		/* if(($handle = fopen($this->getBaseUrl()."test.csv", "r")) !== FALSE)
		{
		  while (($data = fgetcsv($handle, 5000, ",","\"")) !== FALSE) 
		  {
			if($row==1){ $header = $data; $row++; continue;  }
			$product_data[] = array_combine($header, $data);
		  }
		  fclose($handle);
		} */
		
		//throw Mage::exception("Synapse_Productimport_Exception", 'foo!');
		foreach ($product_data as $key => $value){
			$check_sku[] = $value["Item Number"];
			if($value["Item Number"] == ""){
				$error_empty[] = $count;
			}
			$count++;
		}
		
		$duplicates = $duplicates = array_unique(array_diff_assoc($check_sku, array_unique( $check_sku )));

				
		if(count($duplicates) > 0){
				Mage::throwException("Duplicate catalog id: " . implode(", ", $duplicates));
		}elseif (count($error_empty) > 0){
				Mage::throwException("Empty Catalog id row number: " . implode(", ", $error_empty));
		}
				
		$error_config = false;
		
		$setcsv = Mage::getSingleton("adminhtml/session");
		$setcsv->setCustomCsv($product_data);
		
		return;
	}
	
	
	/**
	* Create configurable product
	*/	
	private function createconfigurableproduct($value)
	{
			
			$media_dir = Mage::getBaseDir('media').DS."import".DS;
			Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
			$simpleProductArr = array();
			$configurableattribute = array();
		
			
				
				$attributeColorCode = 'color';
				$attributeSizeCode = 'size';
				
				
				$resource = Mage::getSingleton('core/resource');
				$readConnection = $resource->getConnection('core_read');
				$query = "SELECT `A`.* , `B`.`Retail`, `B`.`Case Qty`, `B`.`Piece`, `B`.`Case`, `B`.`Dozen` FROM `AllDBInfoALO_Prod` A left join `AllDBInfoALP_PRC_R044` B on `A`.`Item Number` = `B`.`Item Number` where `A`.`Style` = \"{$value['Style']}\"";
				$simple_product_forconfig = $readConnection->fetchAll($query);
				
				$createconfigurable = true;
				// creating simple product and collect ids for configurable product
				foreach($simple_product_forconfig as $product_value)
				{
					try{
					$simpleProductArr[] = $this->createsimpleproduct($product_value);
					}catch(Exception $e){
					$error[] = $e->getMessage();
					}
				}
				
				
				// error throw if any error occure while creating simple product
				if(count($error)>0){
					Mage::throwException(implode(" ", array_unique($error)));
				}
				
				// return function if do not create configurable product
				if(!$createconfigurable){
					return;
				}
				
				
				//return function to getting the attribute color id
				if($value["Color Name"] != ""){
					$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attributeColorCode);
					$attribute = $attribute_details->getData();
					$attribute_colorid = $attribute['attribute_id'];
					$configurableattribute[] = $attribute['attribute_id'];
				}

				//return function to getting the attribute size id
				if($value["Size"] != ""){
					$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attributeSizeCode);
					$attribute = $attribute_details->getData();
					$attribute_sizeid = $attribute['attribute_id'];
					$configurableattribute[] = $attribute['attribute_id'];
				}
				
				
				
				

				
				

				if(count($simpleProductArr) > 0)
				{
					
					if($id = Mage::getModel('catalog/product')->getIdBySku($value["Style"])){
						Mage::throwException($this->__("Product %s Already created", $id));
					}else{
						$configProduct = Mage::getModel('catalog/product');
					}
					
				
					try {
						$configProduct
						// ->setStoreId(1) //you can set data in store scope
						->setWebsiteIds(array(1)) //website ID the product is assigned to, as an array
						->setAttributeSetId(4) //ID of a attribute set named 'default'
						->setTypeId("configurable") //product type
						->setCreatedAt(strtotime('now')) //product creation time
						// ->setUpdatedAt(strtotime('now')) //product update time
						->setSku($value["Style"]) //SKU
						->setName($value["Mill Name"].' '.$value["Mill Name"]) //product name
						->setWeight($value["weight"])
						->setStatus(1) //product status (1 - enabled, 2 - disabled)
						->setTaxClassId(0) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
						->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH) //catalog and search visibility
						->setNewsFromDate('') //product set as new from
						->setNewsToDate('') //product set as new to
						->setPrice($value["Retail"]) //price in form 11.22
						->setSpecialPrice('') //special price in form 11.22
						->setSpecialFromDate('') //special price from (MM-DD-YYYY)
						->setSpecialToDate('') //special price to (MM-DD-YYYY)
						//->setMetaTitle($value["seo_title"])
						//->setMetaKeyword($value["keywords"])
						//->setMetaDescription($value["seo_blurb"])
						->setDescription($value["Full Feature Description"]) // Description = blurb1
						->setShortDescription($value["Short Description"]);
						
						//create array for new customer price group
						$new_price = array(
							array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 1, 'price'=>$value["Piece"] ),
							array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 12, 'price'=>$value["Dozen"] ),
							array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 24, 'price'=>$value["Case"] )
						);
						
						//Add new customer group price
						$configProduct->setData('tier_price', $new_price);
						
						// if(!empty($value["Front of Image Name"]))
						// {
							// $galleryData[] = $value["Front of Image Name"];
							// $galleryData[] = $value["Back of Image Name"];
							// $galleryData[] = $value["Side of Image Name"];
							
							// $configProduct->setMediaGallery (array('images'=>array (), 'values'=>array ()));
							// krsort($galleryData);
							// foreach($galleryData as $gallery_img) 
							// {
								// if ($gallery_img){
									// $configProduct->addImageToMediaGallery($media_dir . $gallery_img, array ('image','small_image','thumbnail'), false, false);
								// }
							// }
						// }
						
						$configProduct->setStockData(array(
						'use_config_manage_stock' => 1, //'Use config settings' checkbox
						'manage_stock' => 0, //manage stock
						'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
						'max_sale_qty' => 0, //Maximum Qty Allowed in Shopping Cart
						'is_in_stock' => 1, //Stock Availability
						'qty' => 1 //qty
						));
						
						// $cat = $this->getCategoryNameById($value[3]);
						//->setCategoryIds($cat); //assign product to categories 

						$simpleProducts = Mage::getResourceModel('catalog/product_collection')
						->addIdFilter($simpleProductArr)
						->addAttributeToSelect('price');
						
						if($value["Color Name"] != "")
						$simpleProducts->addAttributeToSelect('color');
						if($value["Size"] != "")
						$simpleProducts->addAttributeToSelect('size');
						
						$configProduct->setCanSaveConfigurableAttributes(true);
						$configProduct->setCanSaveCustomOptions(true);

						$configProduct->getTypeInstance()->setUsedProductAttributeIds($configurableattribute); //attribute ID of attribute 'color' in my store
						$configurableAttributesData = $configProduct->getTypeInstance()->getConfigurableAttributesAsArray();
						$configProduct->setCanSaveConfigurableAttributes(true);
						
						$configProduct->setConfigurableAttributesData($configurableAttributesData);
						$configurableProductsData = array();
						
						
						
						foreach ($simpleProducts as $simple) 
						{
							
							if($value["Color Name"] != ""){
								$productData = array(
								'label' => $simple->getAttributeText('color'),
								'attribute_id' => $attribute_colorid,
								'value_index' => (int) $simple->getColor(),
								'is_percent' => 0
								//'pricing_value' => $simple->getPrice()
								);
								$configurableProductsData[$simple->getId()] = $productData;
								$configurableAttributesData[0]['values'][] = $productData;
							}
							
							if($value["Size"] != ""){
								$productData = array(
								'label' => $simple->getAttributeText('size'),
								'attribute_id' => $attribute_sizeid,
								'value_index' => (int) $simple->getSize(),
								'is_percent' => 0
								//'pricing_value' => $simple->getPrice()
								);
								$configurableProductsData[$simple->getId()] = $productData;
								$configurableAttributesData[1]['values'][] = $productData;
							}
						}
						
						
						$configProduct->setConfigurableProductsData($configurableProductsData);
						$configProduct->setConfigurableAttributesData($configurableAttributesData);
						$configProduct->setCanSaveConfigurableAttributes(true);
						$configProduct->save();
						
						$confId = $configProduct->getId();
						
						//saving the configurable option attribute price value
						if($configProduct->getId()!= '') 
						{
							$configurable = Mage::getModel('catalog/product')->load($confId);
						
							$simpleProducts = Mage::getResourceModel('catalog/product_collection')
							->addIdFilter($simpleProductArr)
							->addAttributeToSelect('price');
							
							if($value["Color Name"] != "")
							$simpleProducts->addAttributeToSelect('color');
							
							if($value["Size"] != "")
							$simpleProducts->addAttributeToSelect('size');
							

							$configurableProductsData = array();
							
							
							
							$configurableAttributesData = $configurable->getTypeInstance()->getConfigurableAttributesAsArray();

							foreach ($simpleProducts as $simple) {
							
								if($value["Color Name"] != ""){
									$productData = array(
									'label' => $simple->getAttributeText('color'),
									'attribute_id' => $attribute_colorid,
									'value_index' => (int) $simple->getColor(),
									'is_percent' => 0
									//'pricing_value' => $simple->getPrice()
									);

									$configurableProductsData[$simple->getId()] = $productData;
									$configurableAttributesData[0]['values'][] = $productData;
								}
							
							
						
								if($value["Size"] != ""){	
										$productData = array(
										'label' => $simple->getAttributeText('size'),
										'attribute_id' => $attribute_sizeid,
										'value_index' => (int) $simple->getSize(),
										'is_percent' => 0
										//'pricing_value' => $simple->getPrice()
										);

										$configurableProductsData[$simple->getId()] = $productData;
										$configurableAttributesData[1]['values'][] = $productData;
								}
								
							}

							$configurable->setConfigurableProductsData($configurableProductsData);
							$configurable->setConfigurableAttributesData($configurableAttributesData);
							$configurable->setCanSaveConfigurableAttributes(true);
							$configurable->save();
							
						}
					}catch(Exception $e){
						Mage::throwException($e->getMessage());
					}
				}
				
				
	}
	
	
	/**
	*	function createsimpleproduct would create simple products as per object
	*/
	private function createsimpleproduct($value,$visiblity=1)
	{
		// print_r($value["sku"]); return;
		//$skuArr[] = $value[0];
		$attributeColorCode = 'color';
		$attributeSizeCode = 'size';
		$attributeBrand = 'brand';
		$media_dir = Mage::getBaseDir('media').DS."import".DS;
		
		$id = Mage::getModel('catalog/product')->getIdBySku($value["Item Number"]);
		
		if($id){
			return $id;
			//$simpleProduct = Mage::getModel('catalog/product')->load($id);
		}else{
			$simpleProduct = Mage::getModel('catalog/product');
		}
		
		
		// Synapse_Productimport_Helper_Data object using
		if($value["Color Name"] != "")
		$optionColorId = Mage::helper('productimport')->getOptionId($attributeColorCode, $value["Color Name"]);
		
		if($value["Size"] != "")
		$optionSizeId = Mage::helper('productimport')->getOptionId($attributeSizeCode, $value["Size"]);
		
		
		$brand = Mage::helper('productimport')->getOptionId($attributeBrand, "Alpha");
		//$cat = Mage::helper('productimport')->getCategoryNameById($value[3]);/* Configurable Product Insert Section */
		
		try {

				$simpleProduct
				->setWebsiteIds(array(1)) //website ID the product is assigned to, as an array
				->setAttributeSetId(4) //ID of a attribute set named 'default'
				->setTypeId("simple") //product type
				->setCreatedAt(strtotime('now')) //product creation time
				->setSku($value["Item Number"]) //SKU = catalog_id
				->setName($value["Mill Name"].' '.$value["Mill Name"]) //product name = product_name
				->setWeight($value["Weight"]) //weight = weight
				->setGtin($value["Gtin"]) //weight = weight
				->setHexcode($value["Hex Code"]) //weight = weight
				->setStatus(1) //product status (1 - enabled, 2 - disabled)
				->setTaxClassId(0) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
				->setVisibility($visiblity) //catalog and search visibility
				->setNewsFromDate('') //product set as new from
				->setNewsToDate('') //product set as new to
				->setPrice($value["Retail"]) //price in form 11.22
				->setSpecialPrice('') //special price in form 11.22
				->setSpecialFromDate('') //special price from (MM-DD-YYYY)
				->setSpecialToDate('') //special price to (MM-DD-YYYY)
				//->setMetaTitle($value["seo_title"]) //MetaTitle = seo_title
				//->setMetaKeyword($value["keywords"]) // keywords = keywords
				//->setMetaDescription($value["seo_blurb"]) //metaDescription = seo_blurb
				->setDescription($value["Full Feature Description"]) // Description = blurb1
				->setShortDescription($value["Short Description"]);
				
				
				if($value["Color Name"] != "")
				$simpleProduct->setColor($optionColorId); // color = options1
				if($value["Size"] != "")
				$simpleProduct->setSize($optionSizeId); // size = options2
				
				$simpleProduct->setBrand($brand); // size = options2
				
				// if(!empty($value["Front of Image Name"]))
				// {
					// $galleryData[] = $value["Front of Image Name"];
					// $galleryData[] = $value["Back of Image Name"];
					// $galleryData[] = $value["Side of Image Name"];
					
					// $simpleProduct->setMediaGallery (array('images'=>array (), 'values'=>array ()));
					// krsort($galleryData);
					// foreach($galleryData as $gallery_img) 
					// {
						// if ($gallery_img){
							// $simpleProduct->addImageToMediaGallery($media_dir . $gallery_img, array ('image','small_image','thumbnail'), false, false);
						// }
					// }
				// }
				
				$simpleProduct->setStockData(array(
				'use_config_manage_stock' => 1, //'Use config settings' checkbox
				'manage_stock' => 1, //manage stock
				'min_sale_qty' => 0, //Minimum Qty Allowed in Shopping Cart
				'use_config_min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
				'max_sale_qty' => 0, //Maximum Qty Allowed in Shopping Cart
				'use_config_max_sale_qty' => 1, //Maximum Qty Allowed in Shopping Cart
				'is_in_stock' => 1, //Stock Availability
				'qty' => 1 //$value[11] //qty
				)
				);
				// $cat = $this->getCategoryNameById($value[3]);
				//->setCategoryIds($cat); //assign product to categories
				
				$simpleProduct->setData('tier_price',array());
				
				//$simpleProduct->save();
				
				//create array for new customer price group
				$new_price = array(
					array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 1, 'price'=>$value["Piece"] ),
					array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 12, 'price'=>$value["Dozen"] ),
					array ('website_id'=>0, 'cust_group'=>2, 'price_qty' => 24, 'price'=>$value["Case"] )
				);
				
				//Add new customer group price
				$simpleProduct->setData('tier_price', $new_price);
				$simpleProduct->save();

				return $simpleProduct->getId();

				//$simpleProductArr[] = $productId;

		}catch (Exception $e)
		{
			Mage::throwException( $e->getMessage() . ' ' . $value["catalog_id"] );
		}
	}
	
	
	private function getBaseUrl(){
		
		return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
	}
	
	
	/**
	* Create configurable product
	*/	
	private function assigncat($value)
	{
				
					
					if($id = Mage::getModel('catalog/product')->getIdBySku($value["catalog_id"])){
						$configProduct = Mage::getModel('catalog/product')->load($id);
					}else{
						return;
					}
					
					try {
						$configProduct
						// $cat = $this->getCategoryNameById($value[3]);
						->setCategoryIds($value['main_cat_id']); //assign product to categories 
						$configProduct->save();
						
						
						
					}catch(Exception $e){
						Mage::throwException($e->getMessage());
					}
				
				
				
	}
	
	
	
	
	
}