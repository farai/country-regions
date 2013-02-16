<?php
/**
 * User: faraikanyepi
 * @package Farai
 */
class Farai_Countryregions_Model_Mysql4_Countryregions extends Mage_Directory_Model_Mysql4_Region
{
    /**
     * Array of unique table rate keys to protect from duplicates
     *
     * @var array
     */
    protected $_importUniqueHash = array();

    /**
     * Errors in import process
     *
     * @var array
     */
    protected $_importErrors = array();

    /**
     * Count of imported table rates
     *
     * @var int
     */
    protected $_importedRows = 0;


    /**
     * Upload and import regions file
     *
     * @throws Mage_Core_Exception
     * @return Farai_Countryregions_Model_Mysql4_Countryregions
     */
    public function uploadRegions()
    {

        $iso2CountryId = Mage::getStoreConfig('shipping/farai_countryregions/the_country_id');
        $data = $_FILES['groups']['tmp_name']['farai_countryregions']['fields']['region_names']['value'];

        if (empty($iso2CountryId) || empty($data)) {
            return $this;
        }

        $csvFile = $data;
        $this->_importUniqueHash = array();
        $this->_importErrors = array();
        $this->_importedRows = 0;

        $io = new Varien_Io_File();
        $fileInfo = pathinfo($csvFile);

        $io->open(array('path' => $fileInfo['dirname']));
        $io->streamOpen($fileInfo['basename'], 'r');

        $headers = $io->streamReadCsv();
        if ($headers === false || count($headers) < 2) {
            $io->streamClose();
            Mage::throwException(Mage::helper('shipping')->__('Invalid Region Names File Format'));
        }

        $adapter = $this->_getWriteAdapter();
        $adapter->beginTransaction();

        try {
            $rowNumber = 1;
            $importData = array();

            while (false !== ($csvLine = $io->streamReadCsv())) {
                $rowNumber++;

                if (empty($csvLine)) {
                    continue;
                }

                $row = $this->_getImportRow($iso2CountryId, $csvLine, $rowNumber);

                if ($row !== false) {
                    $importData[] = $row;
                }
            }

            $this->_saveImportData($importData);
            $io->streamClose();

        } catch (Mage_Core_Exception $e) {
            $adapter->rollBack();
            $io->streamClose();
            Mage::throwException($e->getMessage());
        } catch (Exception $e) {
            $adapter->rollBack();
            $io->streamClose();
            Mage::logException($e);
            Mage::throwException(Mage::helper('shipping')->__('An error occured while importing your Region Names.'));
        }

        $adapter->commit();

        if ($this->_importErrors) {
            $error = Mage::helper('shipping')->__('%1$d region name records have been imported. See the following list of errors for each record that has not been imported: %2$s',
                $this->_importedRows, implode(" \n", $this->_importErrors));
            Mage::throwException($error);
        }

        return $this;
    }

    /**
     * Validate row for import and return region detail array or false
     * Will add Error to _importErrors array
     *
     * @param array $row
     * @param int $rowNumber
     * @return array|false
     **/

    protected function _getImportRow($iso2CountryId, $row, $rowNumber = 0)
    {
        if (count($row) < 2) {
            $this->_importErrors[] = Mage::helper('shipping')->__('Invalid Region Name file format in the Row #%s', $rowNumber);
            return false;
        }

        foreach ($row as $key => $value) {
            $row[$key] = trim($value);
        }

        if (isset($iso2CountryId)) {
            $countryId = $iso2CountryId;
        } else {
            $this->_importErrors[] = Mage::helper('shipper')->__('Invalid Country Selected');
            return false;
        }

        //validate region code
        if (isset($row[0]) && $row[0] != '*' || $row[0] != '') {

            //TODO::Validate supplied region code as ISO2 format
            $regionCode = $row[0];
        } else {
            $this->_importErrors[] = Mage::helper('shipper')->__('Invalid Region Code format for "%s" supplied in the Row #%s.', $row[0], $rowNumber);
            return false;
        }

        //validate region name
        if (isset($row[1]) && $row[1] != '*' || $row[1] != '') {
            $regionName = $row[1];
        } else {
            $this->_importErrors[] = Mage::helper('shipper')->__('Invalid Region Name format for "%s" supplied in the Row #%s.', $row[1], $rowNumber);
            return false;
        }

        //protect from duplicate
        $hash = sprintf("%s-%s-%s", $countryId, $regionCode, $regionName);

        if (isset($this->_importUniqueHash[$hash])) {
            $this->_importErrors[] = Mage::helper('shipping')->__('Duplicate Row #%s (Country "%s", Region/State Code "%s", Region Name "%s" ).',
                $rowNumber, $countryId, $row[0], $row[1]);
            return false;
        }

        $this->_importUniqueHash[$hash] = true;

        return array(
            $countryId,
            $regionCode,
            $regionName
        );
    }

    protected function _saveImportData(array $data)
    {
        if (!empty($data)) {
            $columns = array('country_id', 'code', 'default_name');
            $this->_getWriteAdapter()->insertArray($this->getMainTable(), $columns, $data);
            $this->_importedRows += count($data);
        }

        return $this;
    }
}
