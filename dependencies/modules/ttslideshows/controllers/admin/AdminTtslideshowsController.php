<?php
class AdminTtslideshowsController extends ModuleAdminController
{
	public function __construct() {

     $token = Tools::getAdminTokenLite('AdminModules');
     $currentIndex='index.php?controller=AdminModules&token='.$token.'&configure=ttslideshows&tab_module=front_office_features&module_name=ttslideshows';

     parent::__construct();
     Tools::redirectAdmin($currentIndex);
  }
        
       
    

}
