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
 * @category    Magento
 * @package     Mage_Index
 * @subpackage  integration_tests
 * @copyright   Copyright (c) 2012 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Test class for Mage_Index_Model_Process_File
 */
class Mage_Index_Model_Process_FileTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test lock name
     */
    const FILE_NAME = 'index_test.lock';

    /**
     * @var Magento_Test_ObjectManager
     */
    protected $_objectManager;

    /**
     * @var string
     */
    protected $_fileDirectory;

    /**
     * @var resource
     */
    protected $_testFileHandler;

    /**
     * @var Mage_Index_Model_Process_File
     */
    protected $_model;

    protected function setUp()
    {
        $this->_objectManager   = Mage::getObjectManager();
        $this->_model           = $this->_objectManager->create('Mage_Index_Model_Process_File');
        /** @var $configuration Mage_Core_Model_Config */
        $configuration          = $this->_objectManager->get('Mage_Core_Model_Config');
        $this->_fileDirectory   = $configuration->getVarDir('locks');
        $fullFileName           = $this->_fileDirectory . DIRECTORY_SEPARATOR . self::FILE_NAME;
        $this->_testFileHandler = fopen($fullFileName, 'w');
    }

    protected function tearDown()
    {
        unset($this->_objectManager);
        unset($this->_model);
        unset($this->_fileDirectory);
        fclose($this->_testFileHandler);
        unset($this->_testFileHandler);
    }

    /**
     * Open test file
     */
    protected function _openFile()
    {
        $this->_model->cd($this->_fileDirectory);
        $this->_model->streamOpen(self::FILE_NAME);
    }

    /**
     * Get shared lock for test file handler
     *
     * @return bool
     */
    protected function _tryGetSharedLock()
    {
        return flock($this->_testFileHandler, LOCK_SH | LOCK_NB);
    }

    /**
     * Unlock test file handler
     */
    protected function _unlock()
    {
        flock($this->_testFileHandler, LOCK_UN);
    }

    public function testProcessLockNoStream()
    {
        $this->assertFalse($this->_model->processLock());
    }

    /**
     * This test can't check non blocking lock case because its required two parallel test processes
     */
    public function testProcessLockSuccessfulLock()
    {
        $this->_openFile();

        // can't take shared lock if file has exclusive lock
        $this->assertTrue($this->_model->processLock());
        $this->assertFalse($this->_tryGetSharedLock(), 'File must be locked');
        $this->assertAttributeSame(true, '_streamLocked', $this->_model);
        $this->assertAttributeSame(false, '_processLocked', $this->_model);

        $this->_model->processUnlock();
    }

    public function testProcessFailedLock()
    {
        $this->_openFile();

        // can't take exclusive lock if file has shared lock
        $this->assertTrue($this->_tryGetSharedLock(), 'File must not be locked');
        $this->assertFalse($this->_model->processLock());
        $this->assertAttributeSame(true, '_streamLocked', $this->_model);
        $this->assertAttributeSame(true, '_processLocked', $this->_model);

        $this->_unlock();
    }

    public function testProcessUnlock()
    {
        $this->_openFile();
        $this->_model->processLock();

        $this->assertTrue($this->_model->processUnlock());
        $this->assertAttributeSame(false, '_streamLocked', $this->_model);
        $this->assertAttributeSame(null, '_processLocked', $this->_model);
    }

    public function testIsProcessLockedNoStream()
    {
        $this->assertNull($this->_model->isProcessLocked());
    }

    public function testIsProcessLockedStoredFlag()
    {
        $this->_openFile();
        $this->_model->processLock();
        $this->assertFalse($this->_model->isProcessLocked());
        $this->_model->processUnlock();
    }

    public function testIsProcessLockedTrue()
    {
        $this->_openFile();

        $this->assertTrue($this->_tryGetSharedLock(), 'File must not be locked');
        $this->assertTrue($this->_model->isProcessLocked());

        $this->_unlock();
    }

    public function testIsProcessLockedFalseWithUnlock()
    {
        $this->_openFile();

        $this->assertFalse($this->_model->isProcessLocked(true));
        $this->assertTrue($this->_tryGetSharedLock(), 'File must not be locked');
        $this->assertAttributeSame(false, '_streamLocked', $this->_model);

        $this->_unlock();
    }

    public function testIsProcessLockedFalseWithoutUnlock()
    {
        $this->_openFile();

        $this->assertFalse($this->_model->isProcessLocked());
        $this->assertFalse($this->_tryGetSharedLock(), 'File must be locked');
        $this->assertAttributeSame(true, '_streamLocked', $this->_model);

        $this->_model->processUnlock();
    }
}
