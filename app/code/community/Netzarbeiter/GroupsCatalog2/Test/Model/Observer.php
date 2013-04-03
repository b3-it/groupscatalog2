<?php

/**
 * @see Netzarbeiter_GroupsCatalog2_Model_Observer
 *
 * @loadSharedFixture global.yaml
 * @doNotIndexAll
 */
class Netzarbeiter_GroupsCatalog2_Test_Model_Observer extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @var Mage_Customer_Model_Session
     */
    protected $originalCustomerSession;

    /**
     * Prepare grouscatalog2 index tables
     */
    public static function setUpBeforeClass()
    {
        // Fix SET @SQL_MODE='NO_AUTO_VALUE_ON_ZERO' bugs from shared fixture files
        // With the merge of https://github.com/IvanChepurnyi/EcomDev_PHPUnit/pull/93 this hack isn't required any more
        /** @var $db Varien_Db_Adapter_Interface */
        $db = Mage::getSingleton('core/resource')->getConnection('customer_write');
        $db->update(
            Mage::getSingleton('core/resource')->getTableName('customer/customer_group'),
            array('customer_group_id' => 0),
            "customer_group_code='NOT LOGGED IN'"
        );

        // Rebuild GroupsCatalog2 product index
        Mage::getModel('index/indexer')->getProcessByCode('groupscatalog2_product')->reindexEverything();
    }

    /**
     * Mock customer session singleton and enable frontend events
     */
    protected function setUp()
    {
        // Mock customer session
        $mockSession = $this->getModelMockBuilder('customer/session')
                ->disableOriginalConstructor()
                ->getMock();

        $registryKey = '_singleton/customer/session';
        if (Mage::registry($registryKey)) {
            $this->originalCustomerSession = Mage::registry($registryKey);
            Mage::unregister($registryKey);
        }
        Mage::register($registryKey, $mockSession);

        $this->app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);
    }

    /**
     * Clean up mocked customer session and revert to admin scope
     */
    protected function tearDown()
    {
        $registryKey = '_singleton/customer/session';
        Mage::unregister($registryKey);
        if ($this->originalCustomerSession) {
            Mage::register($registryKey, $this->originalCustomerSession);
            $this->originalCustomerSession = null;
        }
        $this->setCurrentStore('admin');
    }

    /**
     * This will test the event observers triggered by loading a product collection
     * and applying test the groupscatalog filters are applied correctly.
     *
     * @test
     * @param string $storeCode
     * @param int $customerGroupId
     * @dataProvider dataProvider
     */
    public function loadEavProductCollection($storeCode, $customerGroupId)
    {
        // In test fixture setup
        $this->setCurrentStore($storeCode);

        /* @var $session PHPUnit_Framework_MockObject_MockObject Stub */
        $mockSession = Mage::getSingleton('customer/session');
        $mockSession->expects($this->atLeastOnce())
                ->method('getCustomerGroupId')
                ->will($this->returnValue($customerGroupId));


        // Instantiate and load collection
        /** @var $collection Mage_Catalog_Model_Resource_Product_Collection */
        $collection = Mage::getModel('catalog/product')->getCollection()->load();

        $expected = $this->expected("%s-%s", $storeCode, $customerGroupId)->getProductsPresent();
        $actual = array_keys($collection->getItems());
        sort($actual);

        $message = sprintf(
            "Product(s) to be present for group %d in store %s: [%s]. Products found: [%s]",
            $customerGroupId, $storeCode, implode(', ', $expected), implode(', ', $actual)
        );
        $this->assertEquals($expected, $actual, $message);
        /*
        foreach (array(1, 2, 3) as $productId) {
            $isProductPresent = null !== $collection->getItemById($productId);
            $expectedPresent = in_array($productId, $expected);
            $this->assertEquals($expectedPresent, $isProductPresent, $message);
        }
        */
    }
}
