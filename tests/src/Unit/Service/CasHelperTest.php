<?php

namespace Drupal\Tests\cas\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\cas\Service\CasHelper;

/**
 * CasHelper unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Service\CasHelper
 */
class CasHelperTest extends UnitTestCase {

  /**
   * The mocked Url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * The mocked logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $loggerFactory;

  /**
   * The mocked log channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $loggerChannel;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->loggerFactory = $this->getMock('\Drupal\Core\Logger\LoggerChannelFactory');
    $this->loggerChannel = $this->getMockBuilder('\Drupal\Core\Logger\LoggerChannel')
      ->disableOriginalConstructor()
      ->getMock();
    $this->loggerFactory->expects($this->any())
      ->method('get')
      ->with('cas')
      ->will($this->returnValue($this->loggerChannel));
  }

  /**
   * Provides parameters and expected return values for testGetServerLoginUrl.
   *
   * @return array
   *   The list of parameters and return values.
   *
   * @see \Drupal\Tests\cas\Unit\CasHelperTest::testGetServerLoginUrl()
   */
  public function getServerLoginUrlDataProvider() {
    return array(
      array(
        array(),
        'https://example.com/client',
      ),
      array(
        array('returnto' => 'node/1'),
        'https://example.com/client?returnto=node%2F1',
      ),
    );
  }

  /**
   * Test constructing the CAS Server base url.
   *
   * @covers ::getServerBaseUrl
   * @covers ::__construct
   */
  public function testGetServerBaseUrl() {
    /** @var \Drupal\Core\Config\ConfigFactory $config_factory */
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'server.hostname' => 'example.com',
        'server.port' => 443,
        'server.path' => '/cas',
      ),
    ));
    $cas_helper = new CasHelper($config_factory, $this->loggerFactory);

    $this->assertEquals('https://example.com/cas/', $cas_helper->getServerBaseUrl());
  }

  /**
   * Test constructing the CAS Server base url with non-standard port.
   *
   * Non-standard ports should be included in the constructed URL.
   *
   * @covers ::getServerBaseUrl
   * @covers ::__construct
   */
  public function testGetServerBaseUrlNonStandardPort() {
    /** @var \Drupal\Core\Config\ConfigFactory $config_factory */
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'server.hostname' => 'example.com',
        'server.port' => 4433,
        'server.path' => '/cas',
      ),
    ));
    $cas_helper = new CasHelper($config_factory, $this->loggerFactory);

    $this->assertEquals('https://example.com:4433/cas/', $cas_helper->getServerBaseUrl());
  }

  /**
   * Test the logging capability.
   *
   * @covers ::log
   * @covers ::__construct
   */
  public function testLoggingOn() {
    /** @var \Drupal\Core\Config\ConfigFactory $config_factory */
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'advanced.debug_log' => TRUE,
      ),
    ));
    $cas_helper = new CasHelper($config_factory, $this->loggerFactory);
    $this->loggerChannel->expects($this->once())
      ->method('log');
    $cas_helper->log('This is a test.');
  }

  /**
   * Test to make sure we don't log when we're not configured to.
   *
   * @covers ::log
   * @covers ::__construct
   */
  public function testLoggingOff() {
    /** @var \Drupal\Core\Config\ConfigFactory $config_factory */
    $config_factory = $this->getConfigFactoryStub(array(
      'cas.settings' => array(
        'advanced.debug_log' => FALSE,
      ),
    ));
    $cas_helper = new CasHelper($config_factory, $this->loggerFactory);
    $this->loggerChannel->expects($this->never())
      ->method('log');
    $cas_helper->log('This is a test, but should not be logged as such.');
  }

}
