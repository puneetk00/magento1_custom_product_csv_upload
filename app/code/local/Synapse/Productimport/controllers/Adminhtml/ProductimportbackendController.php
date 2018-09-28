<?php
class Synapse_Productimport_Adminhtml_ProductimportbackendController extends Mage_Adminhtml_Controller_Action
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
				$returnData["product_id"] = $data[$param]["catalog_id"];
			}catch(Exception $e){
				$returnData["product_id"] = $data[$param]["catalog_id"];
				$returnData['error'] = $e->getMessage();
				$returnData["message"] = "done";
			}
			
		}else{
			try{
				$this->createconfigurableproduct($data[$param]);
				$returnData["message"] = "continue";
				$returnData["product_id"] = $data[$param]["catalog_id"];
			}catch(Exception $e){
				$returnData["product_id"] = $data[$param]["catalog_id"];
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
			Mage::getSingleton("adminhtml/session")->addSuccess("Ready to import"); 
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
		
		if(($handle = fopen($this->getBaseUrl()."test.csv", "r")) !== FALSE)
		{
		  while (($data = fgetcsv($handle, 5000, ",","\"")) !== FALSE) 
		  {
			if($row==1){ $header = $data; $row++; continue;  }
			$product_data[] = array_combine($header, $data);
		  }
		  fclose($handle);
		}
		
		//throw Mage::exception("Synapse_Productimport_Exception", 'foo!');
		foreach ($product_data as $key => $value){
			$check_sku[] = $value["catalog_id"];
			if($value["catalog_id"] == ""){
				$error_empty[] = $count;
			}
			// if($id = Mage::getModel('catalog/product')->getIdBySku($value["catalog_id"])){
				// $errors[] = $value["catalog_id"];
				
			// }
			$count++;
		}
		
		$duplicates = $duplicates = array_unique(array_diff_assoc($check_sku, array_unique( $check_sku )));

				
		if(count($duplicates) > 0){
				Mage::throwException("Duplicate catalog id: " . implode(", ", $duplicates));
		}elseif (count($error_empty) > 0){
				Mage::throwException("Empty Catalog id row number: " . implode(", ", $error_empty));
		}
		
		if(count($errors) > 0){
			Mage::throwException($this->__('Already exist these catalog_id '. implode(", ", $errors)));
			return;
		}
		
		$error_config = false;
		
		$setcsv = Mage::getSingleton("adminhtml/session");
		// array_pop($product_data);
		// array_pop($product_data);
		$setcsv->setCustomCsv($product_data);
		
		// foreach ($product_data as $key => $value){
			// try{
				// $this->createconfigurableproduct($value);
				// //$this->assigncat($value);
			// }catch(Exception $e){
				// $error[] = $e->getMessage();
				// $error_config = true;
			// }
		// }
		
		if($error_config){
			Mage::throwException(implode(", ",$error));
		
		}
	}
	
	
	/**
	* Create configurable product
	*/	
	private function createconfigurableproduct($value)
	{
			
			$media_dir = Mage::getBaseDir('media').DS."import".DS;
			Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
			$productArr = array();
			$simpleProductArr = array();
			$colors = array();
			$sizes = array();
			$value["sku"] = "";
			$configurableattribute = array();
			$color_index = '';
			$size_index = '';
			
			
		
			
				//$skuArr[] = $value[0];
				$attributeColorCode = 'color';
				$attributeSizeCode = 'size';
				
				// explode size and color by newline
				if($value["options1"] != ""){
					$colors = explode(PHP_EOL, $value["options1"]);
					$colors = array_unique($colors);
				}
				
				if($value["options2"] != ""){
					$sizes = explode(PHP_EOL, $value["options2"]);
					$sizes = array_unique($sizes);
				}
				
				// creaate simple product if color and size have variant
				
				// Create product base on color and size
				if(count($colors) > 0 && count($sizes) > 0 )
				{
				$color_index = 0;
				$size_index = 1;
					$createconfigurable = true;
					foreach($colors as $color){
						$value["options1"] = $color;
						$value["sku"] = str_replace(" ", "", $value["catalog_id"].'-'.$color);
						if(count($sizes)>0){
							foreach($sizes as $size)
							{
								$value["options2"] = $size;
								$value["sku"] = str_replace(" ", "", $value["catalog_id"].'-'.$color.'-'.$size);
								try{
									$simpleProductArr[] = $this->createsimpleproduct($value);
								}catch(Exception $e){
									$error[] = $e->getMessage();
								}
							}	
						}
					}
				}// Create product base on only size
				elseif(count($colors) == 0 && count($sizes) > 0 ){
				$size_index = 0;
					
					$createconfigurable = true;
					foreach($sizes as $size)
					{
						$value["options2"] = $size;
						$value["sku"] = str_replace(" ", "", $value["catalog_id"].'-'.$size);
						try{
							$simpleProductArr[] = $this->createsimpleproduct($value);
						}catch(Exception $e){
							$error[] = $e->getMessage();
						}
					}
				}// Create product base on only Color
				elseif(count($colors) > 0 && count($sizes) == 0 ){
				$color_index = 0;
					$createconfigurable = true;
					foreach($colors as $color)
					{
						$value["options1"] = $color;
						$value["sku"] = str_replace(" ", "", $value["catalog_id"].'-'.$color);
						try{
							$simpleProductArr[] = $this->createsimpleproduct($value);
						}catch(Exception $e){
							$error[] = $e->getMessage();
						}
					}
				}// create product simple
				else{
					$createconfigurable = false;
					try{
						$value["sku"] = $value["catalog_id"];
						$simpleProductArr[] = $this->createsimpleproduct($value,4);
					}catch(Exception $e){
						$error[] = $e->getMessage();
					}
				}
				
				
				
				
				
				if(count($error)>0){
					Mage::throwException(implode(" ", array_unique($error)));
				}
				
				if(!$createconfigurable){
					return;
				}
				
				
				//return function to getting the attribute color id
				if($value["options1"] != ""){
					$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attributeColorCode);
					$attribute = $attribute_details->getData();
					$attribute_colorid = $attribute['attribute_id'];
					$configurableattribute[] = $attribute['attribute_id'];
				}

				//return function to getting the attribute size id
				if($value["options2"] != ""){
					$attribute_details = Mage::getSingleton("eav/config")->getAttribute('catalog_product', $attributeSizeCode);
					$attribute = $attribute_details->getData();
					$attribute_sizeid = $attribute['attribute_id'];
					$configurableattribute[] = $attribute['attribute_id'];
				}
				
				
				
				

				
				

				if(count($simpleProductArr) > 0)
				{
					
					if($id = Mage::getModel('catalog/product')->getIdBySku($value["catalog_id"])){
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
						->setSku($value["catalog_id"]) //SKU
						->setName($value["product_name"]) //product name
						->setWeight($value["weight"])
						->setStatus(1) //product status (1 - enabled, 2 - disabled)
						->setTaxClassId(0) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
						->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH) //catalog and search visibility
						->setNewsFromDate('') //product set as new from
						->setNewsToDate('') //product set as new to
						->setPrice($value["price"]) //price in form 11.22
						->setSpecialPrice('') //special price in form 11.22
						->setSpecialFromDate('') //special price from (MM-DD-YYYY)
						->setSpecialToDate('') //special price to (MM-DD-YYYY)
						->setMetaTitle($value["seo_title"])
						->setMetaKeyword($value["keywords"])
						->setMetaDescription($value["seo_blurb"])
						->setDescription($value["blurb1"])
						->setProductId($value["product_id"])
						->setShortDescription($value["blurb1"]);
						
						if(!empty($value["product_image1"]))
						{
						
							$galleryData[] = $value["product_image1"];
							$galleryData[] = $value["product_image2"];
							$galleryData[] = $value["product_image3"];
							krsort($galleryData);
							$configProduct->setMediaGallery (array('images'=>array (), 'values'=>array ()));
							foreach($galleryData as $gallery_img) 
							{
								if ($gallery_img){
									$configProduct->addImageToMediaGallery($media_dir.$gallery_img, array ('image','small_image','thumbnail'), false, false);
								}
							}
						}
						
						$configProduct->setStockData(array(
						'use_config_manage_stock' => 0, //'Use config settings' checkbox
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
						
						if($value["options1"] != "")
						$simpleProducts->addAttributeToSelect('color');
						if($value["options2"] != "")
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
							if($value["options1"] != ""){
								$productData = array(
								'label' => $simple->getAttributeText('color'),
								'attribute_id' => $attribute_colorid,
								'value_index' => (int) $simple->getColor(),
								'is_percent' => 0
								//'pricing_value' => $simple->getPrice()
								);
								$configurableProductsData[$simple->getId()] = $productData;
								$configurableAttributesData[$color_index]['values'][] = $productData;
							}
							if($value["options2"] != ""){
								$productData = array(
								'label' => $simple->getAttributeText('size'),
								'attribute_id' => $attribute_sizeid,
								'value_index' => (int) $simple->getSize(),
								'is_percent' => 0
								//'pricing_value' => $simple->getPrice()
								);
								$configurableProductsData[$simple->getId()] = $productData;
								$configurableAttributesData[$size_index]['values'][] = $productData;
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
							
							if($value["options1"] != "")
							$simpleProducts->addAttributeToSelect('color');
							
							if($value["options2"] != "")
							$simpleProducts->addAttributeToSelect('size');
							

							$configurableProductsData = array();
							
							
							
							$configurableAttributesData = $configurable->getTypeInstance()->getConfigurableAttributesAsArray();

							foreach ($simpleProducts as $simple) {
							
								if($value["options1"] != ""){
									$productData = array(
									'label' => $simple->getAttributeText('color'),
									'attribute_id' => $attribute_colorid,
									'value_index' => (int) $simple->getColor(),
									'is_percent' => 0
									//'pricing_value' => $simple->getPrice()
									);

									$configurableProductsData[$simple->getId()] = $productData;
									$configurableAttributesData[$color_index]['values'][] = $productData;
								}
							
							
						
								if($value["options2"] != ""){	
										$productData = array(
										'label' => $simple->getAttributeText('size'),
										'attribute_id' => $attribute_sizeid,
										'value_index' => (int) $simple->getSize(),
										'is_percent' => 0
										//'pricing_value' => $simple->getPrice()
										);

										$configurableProductsData[$simple->getId()] = $productData;
										$configurableAttributesData[$size_index]['values'][] = $productData;
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
		
		$id = Mage::getModel('catalog/product')->getIdBySku($value["sku"]);
		
		if($id){
			return $id;
			//$simpleProduct = Mage::getModel('catalog/product')->load($id);
		}else{
			$simpleProduct = Mage::getModel('catalog/product');
		}
		
		
		// Synapse_Productimport_Helper_Data object using
		if($value["options1"] != "")
		$optionColorId = Mage::helper('productimport')->getOptionId($attributeColorCode, $value["options1"]);
		
		if($value["options2"] != "")
		$optionSizeId = Mage::helper('productimport')->getOptionId($attributeSizeCode, $value["options2"]);
		
		if($value["brand"] != "")
		$brand = Mage::helper('productimport')->getOptionId($attributeBrand, $value["brand"]);
		//$cat = Mage::helper('productimport')->getCategoryNameById($value[3]);/* Configurable Product Insert Section */
		
		try {

				$simpleProduct
				->setWebsiteIds(array(1)) //website ID the product is assigned to, as an array
				->setAttributeSetId(4) //ID of a attribute set named 'default'
				->setTypeId("simple") //product type
				->setCreatedAt(strtotime('now')) //product creation time
				->setSku($value["sku"]) //SKU = catalog_id
				->setName($value["product_name"]) //product name = product_name
				->setWeight($value["weight"]) //weight = weight
				->setStatus(1) //product status (1 - enabled, 2 - disabled)
				->setTaxClassId(0) //tax class (0 - none, 1 - default, 2 - taxable, 4 - shipping)
				->setVisibility($visiblity) //catalog and search visibility
				->setNewsFromDate('') //product set as new from
				->setNewsToDate('') //product set as new to
				->setPrice($value["price"]) //price in form 11.22
				->setSpecialPrice('') //special price in form 11.22
				->setSpecialFromDate('') //special price from (MM-DD-YYYY)
				->setSpecialToDate('') //special price to (MM-DD-YYYY)
				->setMetaTitle($value["seo_title"]) //MetaTitle = seo_title
				->setMetaKeyword($value["keywords"]) // keywords = keywords
				->setMetaDescription($value["seo_blurb"]) //metaDescription = seo_blurb
				->setDescription($value["blurb1"]) // Description = blurb1
				->setShortDescription($value["blurb1"]);
				
				
				if($value["options1"] != "")
				$simpleProduct->setColor($optionColorId); // color = options1
				if($value["options2"] != "")
				$simpleProduct->setSize($optionSizeId); // size = options2
				if($value["brand"] != "")
				$simpleProduct->setBrand($brand); // size = options2
				
				if(!empty($value["product_image1"]))
				{
					$galleryData[] = $value["product_image1"];
					$galleryData[] = $value["product_image2"];
					$galleryData[] = $value["product_image3"];
					
					$simpleProduct->setMediaGallery (array('images'=>array (), 'values'=>array ()));
					krsort($galleryData);
					foreach($galleryData as $gallery_img) 
					{
						if ($gallery_img){
							$simpleProduct->addImageToMediaGallery($media_dir . $gallery_img, array ('image','small_image','thumbnail'), false, false);
						}
					}
				}
				
				$simpleProduct->setStockData(array(
				'use_config_manage_stock' => 0, //'Use config settings' checkbox
				'manage_stock' => 0, //manage stock
				'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
				'max_sale_qty' => 0, //Maximum Qty Allowed in Shopping Cart
				'is_in_stock' => 1, //Stock Availability
				'qty' => 1 //$value[11] //qty
				)
				);
				// $cat = $this->getCategoryNameById($value[3]);
				//->setCategoryIds($cat); //assign product to categories
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