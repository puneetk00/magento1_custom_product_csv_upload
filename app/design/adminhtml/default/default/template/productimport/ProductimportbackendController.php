<?php
class Synapse_Productimport_Adminhtml_ProductimportbackendController extends Mage_Adminhtml_Controller_Action
{

	protected function _isAllowed()
	{
		//return Mage::getSingleton('admin/session')->isAllowed('productimport/productimportbackend');
		return true;
	}

	public function indexAction()
    {
       $this->loadLayout();
	   $this->_title($this->__("Backend Page Title"));
	   $this->renderLayout();
    }
	
	public function importAction()
    {
	
		
		$this->__redirect("*/*/*");
       
    }
}