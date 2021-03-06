<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_ImportExport
 * @copyright   Copyright (c) 2012 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Import EAV entity abstract model
 *
 * @category    Mage
 * @package     Mage_ImportExport
 * @author      Magento Core Team <core@magentocommerce.com>
 */
abstract class Mage_ImportExport_Model_Import_Entity_EavAbstract
    extends Mage_ImportExport_Model_Import_EntityAbstract
{
    /**
     * Attribute collection name
     */
    const ATTRIBUTE_COLLECTION_NAME = 'Varien_Data_Collection';

    /**
     * Website manager (currently Mage_Core_Model_App works as website manager)
     *
     * @var Mage_Core_Model_App
     */
    protected $_websiteManager;

    /**
     * Store manager (currently Mage_Core_Model_App works as store manager)
     *
     * @var Mage_Core_Model_App
     */
    protected $_storeManager;

    /**
     * Entity type id
     *
     * @var int
     */
    protected $_entityTypeId;

    /**
     * Attributes with index (not label) value
     *
     * @var array
     */
    protected $_indexValueAttributes = array();

    /**
     * Website code-to-ID
     *
     * @var array
     */
    protected $_websiteCodeToId = array();

    /**
     * All stores code-ID pairs.
     *
     * @var array
     */
    protected $_storeCodeToId = array();

    /**
     * Entity attributes parameters
     *
     *  [attr_code_1] => array(
     *      'options' => array(),
     *      'type' => 'text', 'price', 'textarea', 'select', etc.
     *      'id' => ..
     *  ),
     *  ...
     *
     * @var array
     */
    protected $_attributes = array();

    /**
     * Attributes collection
     *
     * @var Varien_Data_Collection
     */
    protected $_attributeCollection;

    /**
     * Constructor
     *
     * @param array $data
     */
    public function __construct(array $data = array())
    {
        parent::__construct($data);

        $this->_websiteManager = isset($data['website_manager']) ? $data['website_manager'] : Mage::app();
        $this->_storeManager   = isset($data['store_manager']) ? $data['store_manager'] : Mage::app();
        $this->_attributeCollection = isset($data['attribute_collection']) ? $data['attribute_collection']
            : Mage::getResourceModel(static::ATTRIBUTE_COLLECTION_NAME);

        if (isset($data['entity_type_id'])) {
            $this->_entityTypeId = $data['entity_type_id'];
        } else {
            $this->_entityTypeId = Mage::getSingleton('Mage_Eav_Model_Config')
                ->getEntityType($this->getEntityTypeCode())
                ->getEntityTypeId();
        }
    }

    /**
     * Retrieve website id by code or false when website code not exists
     *
     * @param $websiteCode
     * @return bool|int
     */
    public function getWebsiteId($websiteCode)
    {
        if (isset($this->_websiteCodeToId[$websiteCode])) {
            return $this->_websiteCodeToId[$websiteCode];
        }

        return false;
    }

    /**
     * Initialize website values
     *
     * @param bool $withDefault
     * @return Mage_ImportExport_Model_Import_Entity_EavAbstract
     */
    protected function _initWebsites($withDefault = false)
    {
        /** @var $website Mage_Core_Model_Website */
        foreach ($this->_websiteManager->getWebsites($withDefault) as $website) {
            $this->_websiteCodeToId[$website->getCode()] = $website->getId();
        }
        return $this;
    }

    /**
     * Initialize stores data
     *
     * @param bool $withDefault
     * @return Mage_ImportExport_Model_Import_Entity_EavAbstract
     */
    protected function _initStores($withDefault = false)
    {
        /** @var $store Mage_Core_Model_Store */
        foreach ($this->_storeManager->getStores($withDefault) as $store) {
            $this->_storeCodeToId[$store->getCode()] = $store->getId();
        }
        return $this;
    }

    /**
     * Initialize entity attributes
     *
     * @return Mage_ImportExport_Model_Import_Entity_EavAbstract
     */
    protected function _initAttributes()
    {
        /** @var $attribute Mage_Eav_Model_Attribute */
        foreach ($this->_attributeCollection as $attribute) {
            $this->_attributes[$attribute->getAttributeCode()] = array(
                'id'          => $attribute->getId(),
                'code'        => $attribute->getAttributeCode(),
                'table'       => $attribute->getBackend()->getTable(),
                'is_required' => $attribute->getIsRequired(),
                'is_static'   => $attribute->isStatic(),
                'rules'       => $attribute->getValidateRules() ? unserialize($attribute->getValidateRules()) : null,
                'type'        => Mage_ImportExport_Model_Import::getAttributeType($attribute),
                'options'     => $this->getAttributeOptions($attribute)
            );
        }
        return $this;
    }

    /**
     * Entity type ID getter
     *
     * @return int
     */
    public function getEntityTypeId()
    {
        return $this->_entityTypeId;
    }

    /**
     * Returns attributes all values in label-value or value-value pairs form. Labels are lower-cased
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param array $indexAttributes OPTIONAL Additional attribute codes with index values.
     * @return array
     */
    public function getAttributeOptions(Mage_Eav_Model_Entity_Attribute_Abstract $attribute,
        array $indexAttributes = array()
    ) {
        $options = array();

        if ($attribute->usesSource()) {
            // merge global entity index value attributes
            $indexAttributes = array_merge($indexAttributes, $this->_indexValueAttributes);

            // should attribute has index (option value) instead of a label?
            $index = in_array($attribute->getAttributeCode(), $indexAttributes) ? 'value' : 'label';

            // only default (admin) store values used
            $attribute->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);

            try {
                foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                    $value = is_array($option['value']) ? $option['value'] : array($option);
                    foreach ($value as $innerOption) {
                        if (strlen($innerOption['value'])) { // skip ' -- Please Select -- ' option
                            $options[strtolower($innerOption[$index])] = $innerOption['value'];
                        }
                    }
                }
            } catch (Exception $e) {
                // ignore exceptions connected with source models
            }
        }
        return $options;
    }

    /**
     * Get attribute collection
     *
     * @return Varien_Data_Collection
     */
    public function getAttributeCollection()
    {
        return $this->_attributeCollection;
    }
}
