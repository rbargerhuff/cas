<?php

/**
 * @file
 * Contains Drupal\Tests\cas\Unit\Controller\ServiceControllerTest.
 */

namespace Drupal\Tests\cas\Unit\Controller;

use Drupal\Tests\UnitTestCase;
use Drupal\cas\Controller\ServiceController;
use Drupal\cas\Exception\CasValidateException;
use Drupal\cas\Exception\CasLoginException;

/**
 * ServiceController unit tests.
 *
 * @ingroup cas
 * @group cas
 *
 * @coversDefaultClass \Drupal\cas\Controller\ServiceController
 */
class ServiceControllerTest extends UnitTestCase {

  /**
   * The mocked CasHelper.
   *
   * @var \Drupal\cas\Service\CasHelper|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casHelper;

  /**
   * The mocked Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $requestStack;

  /**
   * The mocked CasValidator.
   *
   * @var \Drupal\cas\Service\CasValidator|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casValidator;

  /**
   * The mocked CasLogin.
   *
   * @var \Drupal\cas\Service\CasLogin|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casLogin;

  /**
   * The mocked CasLogout.
   *
   * @var \Drupal\cas\Service\CasLogout|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $casLogout;

  /**
   * The mocked Url Generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  protected $serviceController;

  protected $requestBag;

  protected $queryBag;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->casHelper = $this->getMockBuilder('\Drupal\cas\Service\CasHelper')
      ->disableOriginalConstructor()
      ->getMock();
    $this->casValidator = $this->getMockBuilder('\Drupal\cas\Service\CasValidator')
      ->disableOriginalConstructor()
      ->getMock();
    $this->casLogin = $this->getMockBuilder('\Drupal\cas\Service\CasLogin')
      ->disableOriginalConstructor()
      ->getMock();
    $this->casLogout = $this->getMockBuilder('\Drupal\cas\Service\CasLogout')
      ->disableOriginalConstructor()
      ->getMock();
    $this->requestStack = $this->getMock('\Symfony\Component\HttpFoundation\RequestStack');
    $this->urlGenerator = $this->getMock('\Drupal\Core\Routing\UrlGeneratorInterface');

    $request_object = $this->getMock('\Symfony\Component\HttpFoundation\Request');
    $request_bag = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $query_bag = $this->getMock('\Symfony\Component\HttpFoundation\ParameterBag');
    $request_object->query = $query_bag;
    $request_object->request = $request_bag;

    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->will($this->returnValue($request_object));

    $this->serviceController = new ServiceController(
        $this->casHelper,
        $this->casValidator,
        $this->casLogin,
        $this->casLogout,
        $this->requestStack,
        $this->urlGenerator
    );
    $this->requestBag = $request_bag;
    $this->queryBag = $query_bag;
  }

  /**
   * Tests a single logout request.
   *
   * @dataProvider parameterDataProvider
   */
  public function testSingleLogout($returnto, $cas_temp_disable) {
    $this->setupRequestParameters(
      // returnto.
      $returnto,
      // cas_temp_disable.
      $cas_temp_disable,
      // logoutRequest.
      TRUE,
      // ticket.
      FALSE
    );

    $this->casLogout->expects($this->once())
      ->method('handleSlo')
      ->with($this->equalTo('<foobar/>'));

    $response = $this->serviceController->handle();
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('', $response->getContent());
  }

  /**
   * Tests that we redirect to the homepage when no service ticket is present.
   *
   * @dataProvider parameterDataProvider
   */
  public function testMissingTicketRedirectsHome($returnto, $cas_temp_disable) {
    $this->setupRequestParameters(
      // returnto.
      $returnto,
      // cas_temp_disable.
      $cas_temp_disable,
      // logoutRequest.
      FALSE,
      // ticket.
      FALSE
    );

    if ($returnto) {
      $this->assertDestinationSetFromReturnTo();
    }

    $this->assertRedirectedToFrontPageOnHandle();
    if ($cas_temp_disable) {
      $this->assertEquals(TRUE, $_SESSION['cas_temp_disable']);
    }
  }

  /**
   * Tests that validation and logging in occurs when a ticket is present.
   *
   * @dataProvider parameterDataProvider
   */
  public function testSuccessfulLogin($returnto, $cas_temp_disable) {
    $this->setupRequestParameters(
      // returnto.
      $returnto,
      // cas_temp_disable.
      $cas_temp_disable,
      // logoutRequest.
      FALSE,
      // ticket.
      TRUE
    );

    if ($returnto) {
      $this->assertDestinationSetFromReturnTo();
    }

    $this->assertSuccessfulValidation($returnto, $cas_temp_disable);

    // Login should be called.
    $this->casLogin->expects($this->once())
      ->method('loginToDrupal')
      ->with($this->equalTo('testuser'), $this->equalTo('ST-foobar'));

    $this->assertRedirectedToFrontPageOnHandle();
    if ($cas_temp_disable) {
      $this->assertEquals(TRUE, $_SESSION['cas_temp_disable']);
    }
  }

  /**
   * Tests that a user is validated and logged in with Drupal acting as proxy.
   *
   * @dataProvider parameterDataProvider
   */
  public function testSuccessfulLoginProxyEnabled($returnto, $cas_temp_disable) {
    $this->setupRequestParameters(
      // returnto.
      $returnto,
      // cas_temp_disable.
      $cas_temp_disable,
      // logoutRequest.
      FALSE,
      // ticket.
      TRUE
    );

    if ($returnto) {
      $this->assertDestinationSetFromReturnTo();
    }

    // Cas Helper should indicate Drupal is a proxy.
    $this->casHelper->expects($this->once())
      ->method('isProxy')
      ->will($this->returnValue(TRUE));

    $this->assertSuccessfulValidation($returnto, $cas_temp_disable, TRUE);

    // Login should be called.
    $this->casLogin->expects($this->once())
      ->method('loginToDrupal')
      ->with($this->equalTo('testuser'), $this->equalTo('ST-foobar'));

    // PGT should be saved.
    $this->casHelper->expects($this->once())
      ->method('storePGTSession')
      ->with($this->equalTo('testpgt'));

    $this->assertRedirectedToFrontPageOnHandle();
    if ($cas_temp_disable) {
      $this->assertEquals(TRUE, $_SESSION['cas_temp_disable']);
    }
  }

  /**
   * Tests for a potential validation error.
   *
   * @dataProvider parameterDataProvider
   */
  public function testTicketValidationError($returnto, $cas_temp_disable) {
    $this->setupRequestParameters(
      // returnto.
      $returnto,
      // cas_temp_disable.
      $cas_temp_disable,
      // logoutRequest.
      FALSE,
      // ticket.
      TRUE
    );

    if ($returnto) {
      $this->assertDestinationSetFromReturnTo();
    }

    // Validation should throw an exception.
    $this->casValidator->expects($this->once())
      ->method('validateTicket')
      ->will($this->throwException(new CasValidateException()));

    // Login should not be called.
    $this->casLogin->expects($this->never())
      ->method('loginToDrupal');

    $this->assertRedirectedToFrontPageOnHandle();
    if ($cas_temp_disable) {
      $this->assertEquals(TRUE, $_SESSION['cas_temp_disable']);
    }
  }

  /**
   * Tests for a potential login error.
   *
   * @dataProvider parameterDataProvider
   */
  public function testLoginError($returnto, $cas_temp_disable) {
    $this->setupRequestParameters(
      // returnto.
      $returnto,
      // cas_temp_disable.
      $cas_temp_disable,
      // logoutRequest.
      FALSE,
      // ticket.
      TRUE
    );

    if ($returnto) {
      $this->assertDestinationSetFromReturnTo();
    }

    $this->assertSuccessfulValidation($returnto, $cas_temp_disable);

    // Login should throw an exception.
    $this->casLogin->expects($this->once())
      ->method('loginToDrupal')
      ->will($this->throwException(new CasLoginException()));

    $this->assertRedirectedToFrontPageOnHandle();
    if ($cas_temp_disable) {
      $this->assertEquals(TRUE, $_SESSION['cas_temp_disable']);
    }
  }

  /**
   * Provides different query string params for tests.
   *
   * We want most test cases to behave accordingly for the matrix of
   * query string parameters that may be present on the request. This provider
   * will turn those params on or off.
   */
  public function parameterDataProvider() {
    return array(
      // "returnto" not set, "cas_temp_disable" not set.
      array(FALSE, FALSE),
      // "returnto" set, "cas_temp_disable" not set.
      array(TRUE, FALSE),
      // "returnto" not set, "cas_temp_disable" set.
      array(FALSE, TRUE),
      // "returnto" set, "cas_temp_disable" set.
      array(TRUE, TRUE),
    );
  }

  /**
   * Assert user redirected to homepage when controller invoked.
   */
  private function assertRedirectedToFrontPageOnHandle() {
    // URL Generator will generate a path to the homepage.
    $this->urlGenerator->expects($this->once())
      ->method('generate')
      ->with('<front>')
      ->will($this->returnValue('http://example.com/front'));
    $response = $this->serviceController->handle();
    $this->assertTrue($response->isRedirect('http://example.com/front'));
  }

  /**
   * Assert that the destination query param is set when returnto is present.
   */
  private function assertDestinationSetFromReturnTo() {
    $this->queryBag->expects($this->once())
      ->method('set')
      ->with('destination')
      ->will($this->returnValue('node/1'));
  }

  /**
   * Asserts that validation is executed.
   */
  private function assertSuccessfulValidation($returnto, $cas_temp_disable, $for_proxy = FALSE) {
    $service_params = array();
    if ($returnto) {
      $service_params['returnto'] = 'node/1';
    }
    if ($cas_temp_disable) {
      $service_params['cas_temp_disable'] = TRUE;
    }

    $validation_data = array('username' => 'testuser');
    if ($for_proxy) {
      $validation_data['pgt'] = 'testpgt';
    }

    // Validation service should be called for that ticket.
    $this->casValidator->expects($this->once())
      ->method('validateTicket')
      ->with(NULL, $this->equalTo('ST-foobar'), $this->equalTo($service_params))
      ->will($this->returnValue($validation_data));
  }

  /**
   * Mock our request and query bags for the provided parameters.
   *
   * This method accepts each possible parameter that the Sevice Controller
   * may need to deal with. Each parameter passed in should just be TRUE or
   * FALSE. If it's TRUE, we also mock the "get" method for the appropriate
   * parameter bag to return some predefined value.
   *
   * @param bool $returnto
   *   If returnto param should be set.
   * @param bool $cas_temp_disable
   *   If cas_temp_disable param should be set.
   * @param bool $logout_request
   *   If logoutRequest param should be set.
   * @param bool $ticket
   *   If ticket param should be set.
   */
  private function setupRequestParameters($returnto, $cas_temp_disable, $logout_request, $ticket) {
    // Request params.
    $map = array(
      array('logoutRequest', $logout_request),
    );
    $this->requestBag->expects($this->any())
      ->method('has')
      ->will($this->returnValueMap($map));

    $map = array();
    if ($logout_request === TRUE) {
      $map[] = array('logoutRequest', NULL, FALSE, '<foobar/>');
    }
    if (!empty($map)) {
      $this->requestBag->expects($this->any())
        ->method('get')
        ->will($this->returnValueMap($map));
    }

    // Query string params.
    $map = array(
      array('returnto', $returnto),
      array('cas_temp_disable', $cas_temp_disable),
      array('ticket', $ticket),
    );
    $this->queryBag->expects($this->any())
      ->method('has')
      ->will($this->returnValueMap($map));

    $map = array();
    if ($returnto === TRUE) {
      $map[] = array('returnto', NULL, FALSE, 'node/1');
    }
    if ($ticket === TRUE) {
      $map[] = array('ticket', NULL, FALSE, 'ST-foobar');
    }
    if (!empty($map)) {
      $this->queryBag->expects($this->any())
        ->method('get')
        ->will($this->returnValueMap($map));
    }

    // Query string "all" method should include all params.
    $all = array();
    if ($returnto) {
      $all['returnto'] = 'node/1';
    }
    if ($cas_temp_disable) {
      $all['cas_temp_disable'] = TRUE;
    }
    if ($ticket) {
      $all['ticket'] = 'ST-foobar';
    }
    $this->queryBag->method('all')
      ->will($this->returnValue($all));
  }
}
