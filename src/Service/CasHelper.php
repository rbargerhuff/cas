<?php

namespace Drupal\cas\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Database\Connection;

class CasHelper {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param Connection $database_connection
   *   The database service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator, Connection $database_connection) {
    $this->configFactory = $config_factory;
    $this->urlGenerator = $url_generator;
    $this->connection = $database_connection;

    $this->settings = $config_factory->get('cas.settings');
  }

  /**
   * Return the login URL to the CAS server.
   *
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   * @param bool $gateway
   *   TRUE if this should be a gateway request.
   *
   * @return string
   *   The fully constructed server login URL.
   */
  public function getServerLoginUrl($service_params = array(), $gateway = FALSE) {
    $login_url = $this->getServerBaseUrl() . 'login';

    $params = array();
    if ($gateway) {
      $params['gateway'] = TRUE;
    }
    $params['service'] = $this->getCasServiceUrl($service_params);

    return $login_url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Return the validation URL used to validate the provided ticket.
   *
   * @param string $ticket
   *   The ticket to validate.
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   *
   * @return string
   *   The fully constructed validation URL.
   */
  public function getServerValidateUrl($ticket, $service_params = array()) {
    $validate_url = $this->getServerBaseUrl();
    $path = '';
    switch ($this->getCasProtocolVersion()) {
      case "1.0":
        $path = 'validate';
        break;

      case "2.0":
        if ($this->canBeProxied()) {
          $path = 'proxyValidate';
        }
        else {
          $path = 'serviceValidate';
        }
        break;
    }
    $validate_url .= $path;

    $params = array();
    $params['service'] = $this->getCasServiceUrl($service_params);
    $params['ticket'] = $ticket;
    if ($this->isProxy()) {
      $params['pgtUrl'] = $this->formatProxyCallbackURL();
    }
    return $validate_url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Return the version of the CAS server protocol.
   *
   * @return mixed|null
   *   The version.
   */
  public function getCasProtocolVersion() {
    return $this->settings->get('server.version');
  }

  /**
   * Return CA PEM file path.
   *
   * @return mixed|null
   *   The path to the PEM file for the CA.
   */
  public function getCertificateAuthorityPem() {
    return $this->settings->get('server.cert');
  }

  /**
   * Return the service URL.
   *
   * @param array $service_params
   *   An array of query string parameters to append to the service URL.
   *
   * @return string
   *   The fully constructed service URL to use for CAS server.
   */
  private function getCasServiceUrl($service_params = array()) {
    return $this->urlGenerator->generate('cas.service', $service_params, TRUE);
  }

  /**
   * Construct the base URL to the CAS server.
   *
   * @return string
   *   The base URL.
   */
  public function getServerBaseUrl() {
    $url = 'https://' . $this->settings->get('server.hostname');
    $port = $this->settings->get('server.port');
    if (!empty($port)) {
      $url .= ':' . $this->settings->get('server.port');
    }
    $url .= $this->settings->get('server.path');
    $url = rtrim($url, '/') . '/';

    return $url;
  }

  /**
   * Determine whether this client is configured to act as a proxy.
   *
   * @return bool
   *   TRUE if proxy, FALSE otherwise.
   */
  public function isProxy() {
    return $this->settings->get('proxy.initialize') == TRUE;
  }

  /**
   * Format the pgtCallbackURL parameter for use with proxying.
   *
   * @return string
   *   The pgtCallbackURL, fully formatted.
   */
  private function formatProxyCallbackURL() {
    return $this->urlGenerator->generateFromPath('casproxycallback', array(
      'absolute' => TRUE,
      'https' => TRUE,
    ));
  }

  /**
   * Lookup a PGT by PGTIOU.
   *
   * @param string $pgt_iou
   *   A pgtIou to use a key for the lookup.
   *
   * @return string
   *   The PGT value.
   */
  private function lookupPgtByPgtIou($pgt_iou) {
    return $this->connection->select('cas_pgt_storage', 'c')
      ->fields('c', array('pgt'))
      ->condition('pgt_iou', $pgt_iou)
      ->execute()
      ->fetch();
  }

  /**
   * Store the PGT in the user session.
   *
   * @param string $pgt_iou
   *   A pgtIou to identify the PGT.
   */
  public function storePGTSession($pgt_iou) {
    $pgt = $this->lookupPgtByPgtIou($pgt_iou);
    $_SESSION['cas_pgt'] = $pgt;
    // Now that we have the pgt in the session,
    // we can delete the database mapping.
    $this->deletePgtMappingByPgtIou($pgt_iou);
  }

  /**
   * Delete a PGT/PGTIOU mapping from the database.
   *
   * @param string $pgt_iou
   *   A pgtIou string to use as the deletion key.
   */
  private function deletePgtMappingByPgtIou($pgt_iou) {
    $this->connection->delete('cas_pgt_storage')
      ->condition('pgt_iou', $pgt_iou)
      ->execute();
  }

  /**
   * Determine whether this client is allowed to be proxied.
   *
   * @return bool
   *   TRUE if it can be proxied, FALSE otherwise.
   */
  public function canBeProxied() {
    return $this->settings->get('proxy.can_be_proxied') == TRUE;
  }

  /**
   * Return the allowed proxy chains list.
   *
   * @return string
   *   A newline delimited list of proxy chains.
   */
  public function getProxyChains() {
    return $this->settings->get('proxy.proxy_chains');
  }
}