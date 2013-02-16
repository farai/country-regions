<?php


class Farai_Countryregions_Model_Mysql4_Translatedregions extends Mage_Directory_Model_Mysql4_Region
{

    /**
     * @return $this
     */
    public function uploadTranslatedRegions()
    {

        //Selected country code
        $countryCode = Mage::getStoreConfig('shipping/farai_countryregions/the_country_id');

        //Selected locale
        $locale = Mage::getStoreConfig('shipping/farai_countryregions/locale');

        //csv file
        $data = $_FILES['groups']['tmp_name']['farai_countryregions']['fields']['translated_region_names']['value'];

        if (empty($countryCode) || empty($locale) || empty($data)) {
            return $this;
        }

        $csvFile = $data;

        $io = new Varien_Io_File();
        $fileInfo = pathinfo($csvFile);

        $io->open(array('path' => $fileInfo['dirname']));
        $io->streamOpen($fileInfo['basename'], 'r');

        //check file format
        $headers = $io->streamReadCsv();
        if ($headers === false || count($headers) < 2) {
            $io->streamClose();
            Mage::throwException(Mage::helper('shipping')->__('Invalid Translated Region Names File Format'));
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

                $row = $this->_getImportRow($locale, $countryCode, $csvLine, $rowNumber);

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
            Mage::throwException(Mage::helper('shipping')->__('An error occured while importing your Translated Region Names.'));
        }

        $adapter->commit();

        if ($this->_importErrors) {
            $error = Mage::helper('shipping')->__('%1$d translated region name records have been imported. See the following list of errors for each record that has not been imported: %2$s',
                $this->_importedRows, implode(" \n", $this->_importErrors));
            Mage::throwException($error);
        }

        return $this;
    }


    /**
     * CSV format is 2 columns - iso3/iso2 region code and Translated region name
     *
     * @param $locale
     * @param $iso2CountryId
     * @param $row
     * @param int $rowNumber
     * @return array|bool
     */
    protected function _getImportRow($locale, $iso2CountryId, $row, $rowNumber = 0)
    {
        if (count($row) < 2) {
            $this->_importErrors[] = Mage::helper('shipping')->__('Invalid Translated Region Name file format in the Row #%s', $rowNumber);
            return false;
        }

        foreach ($row as $key => $value) {
            $row[$key] = trim($value);
        }

        if (isset($locale)) {
            $chosenLocale = $locale;
        } else {
            $this->_importErrors[] = Mage::helper('shipping')->__('Invalid Locale Selected Or No locale selected');
            return false;
        }

        //get region id for supplied region code
        if (isset($row[0]) && $row[0] != '*' || $row[0] != '') {

            $regionCode = $row[0];

            $regionModel = Mage::getModel('directory/region')->loadByCode($regionCode, $iso2CountryId);
            $id = $regionModel->getId();

            if ($id != null || !empty($id)) {
                $regionId = $id;
            } else {
                $this->_importErrors[] = Mage::helper('shipping')->__('Failed to find Region ID for supplied Region Code "%s" supplied in the Row #%s.', $row[0], $rowNumber);
                return false;
            }

        } else {
            $this->_importErrors[] = Mage::helper('shipping')->__('Invalid Region Code format for "%s" supplied in the Row #%s.', $row[0], $rowNumber);
            return false;
        }

        //get region name
        if (isset($row[1]) && $row[1] != '*' || $row[1] != '') {
            $regionName = $row[1];
        } else {
            $this->_importErrors[] = Mage::helper('shipping')->__('Invalid Region Name format for "%s" supplied in the Row #%s.', $row[1], $rowNumber);
            return false;
        }

        //protect from duplicate
        $hash = sprintf("%s-%s-%s", $chosenLocale, $regionId, $regionName);

        if (isset($this->_importUniqueHash[$hash])) {
            $this->_importErrors[] = Mage::helper('shipping')->__('Duplicate Row #%s (Locale "%s", Region/State Code "%s", Region Name "%s" ).',
                $rowNumber, $chosenLocale, $row[0], $row[1]);
            return false;
        }

        $this->_importUniqueHash[$hash] = true;

        return array(
            $chosenLocale,
            $regionId,
            $regionName
        );
    }

    /**
     * @param array $data
     * @return $this
     */

    protected function _saveImportData(array $data)
    {
        if (!empty($data)) {
            $columns = array('locale', 'region_id', 'name');
            $this->_getWriteAdapter()->insertArray($this->_regionNameTable, $columns, $data);
            $this->_importedRows += count($data);
        }

        return $this;
    }


}