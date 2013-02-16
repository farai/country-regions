<?php
/**
 * User: faraikanyepi
 */
class Farai_Countryregions_Model_System_Config_Backend_Shipping_Translatedregions extends Mage_Core_Model_Config_Data
{

    public function _afterSave(){
        Mage::getResourceModel('countryregions/translatedregions')->uploadTranslatedRegions($this);
    }

}
