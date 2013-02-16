<?php
/**
 * @package Farai
 * @category Farai
 */
class Farai_Countryregions_Model_System_Config_Backend_Shipping_Countryregions extends Mage_Core_Model_Config_Data
{
    /**
     * @return Farai_Countryregions_Model_System_Config_Backend_Shipping_CountryRegions
     *
     **/
    public function _afterSave(){

       Mage::getResourceModel('countryregions/countryregions')->uploadRegions($this);
    }
}