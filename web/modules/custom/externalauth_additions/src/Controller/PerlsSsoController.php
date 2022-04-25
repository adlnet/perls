<?php

namespace Drupal\externalauth_additions\Controller;

use SimpleSAML\Metadata\Signer;
use SimpleSAML\Metadata\SAMLBuilder;
use SimpleSAML\Utils\Config\Metadata;
use SimpleSAML\Error\Exception;
use SimpleSAML\Utils\Crypto;
use SimpleSAML\Store\SQL;
use SimpleSAML\Module;
use SAML2\Constants;
use SimpleSAML\Store;
use SimpleSAML\Module\saml\Auth\Source\SP;
use SimpleSAML\Error\AuthSource;
use SimpleSAML\Auth\Source;
use SimpleSAML\Configuration;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Returns responses for Perls SSO Configuration routes.
 */
class PerlsSsoController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function downloadMetadata() {

    $config = Configuration::getInstance();
    $sourceId = $this->config('simplesamlphp_auth.settings')->get('auth_source');
    if (!$sourceId) {
      $sourceId = 'perls';
    }
    $source = Source::getById($sourceId);
    if ($source === NULL) {
      throw new AuthSource($sourceId, 'Could not find authentication source.');
    }

    if (!($source instanceof SP)) {
      throw new AuthSource(
        $sourceId,
        'The authentication source is not a SAML Service Provider.'
      );
    }

    $entityId = $source->getEntityId();
    $spconfig = $source->getMetadata();
    $store = Store::getInstance();

    $metaArray20 = [];

    $slosvcdefault = [
      Constants::BINDING_HTTP_REDIRECT,
      Constants::BINDING_SOAP,
    ];

    $slob = $spconfig->getArray('SingleLogoutServiceBinding', $slosvcdefault);
    $slol = Module::getModuleURL('saml/sp/saml2-logout.php/' . $sourceId);

    foreach ($slob as $binding) {
      if ($binding == Constants::BINDING_SOAP && !($store instanceof SQL)) {
        // We cannot properly support SOAP logout.
        continue;
      }
      $metaArray20['SingleLogoutService'][] = [
        'Binding'  => $binding,
        'Location' => $spconfig->getString('SingleLogoutServiceLocation', $slol),
      ];
    }

    $assertionsconsumerservicesdefault = [
      'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
      'urn:oasis:names:tc:SAML:1.0:profiles:browser-post',
      'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
      'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01',
    ];

    if ($spconfig->getString('ProtocolBinding', '') == 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser') {
      $assertionsconsumerservicesdefault[] = 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser';
    }

    $assertionsconsumerservices = $spconfig->getArray('acs.Bindings', $assertionsconsumerservicesdefault);

    $index = 0;
    $eps = [];
    $supported_protocols = [];
    foreach ($assertionsconsumerservices as $services) {
      $acsArray = ['index' => $index];
      switch ($services) {
        case 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST':
          $acsArray['Binding'] = Constants::BINDING_HTTP_POST;
          $acsArray['Location'] = Module::getModuleURL('saml/sp/saml2-acs.php/' . $sourceId);
          if (!in_array(Constants::NS_SAMLP, $supported_protocols, TRUE)) {
            $supported_protocols[] = Constants::NS_SAMLP;
          }
          break;

        case 'urn:oasis:names:tc:SAML:1.0:profiles:browser-post':
          $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:1.0:profiles:browser-post';
          $acsArray['Location'] = Module::getModuleURL('saml/sp/saml1-acs.php/' . $sourceId);
          if (!in_array('urn:oasis:names:tc:SAML:1.1:protocol', $supported_protocols, TRUE)) {
            $supported_protocols[] = 'urn:oasis:names:tc:SAML:1.1:protocol';
          }
          break;

        case 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact':
          $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact';
          $acsArray['Location'] = Module::getModuleURL('saml/sp/saml2-acs.php/' . $sourceId);
          if (!in_array(Constants::NS_SAMLP, $supported_protocols, TRUE)) {
            $supported_protocols[] = Constants::NS_SAMLP;
          }
          break;

        case 'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01':
          $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:1.0:profiles:artifact-01';
          $acsArray['Location'] = Module::getModuleURL(
            'saml/sp/saml1-acs.php/' . $sourceId . '/artifact'
          );
          if (!in_array('urn:oasis:names:tc:SAML:1.1:protocol', $supported_protocols, TRUE)) {
            $supported_protocols[] = 'urn:oasis:names:tc:SAML:1.1:protocol';
          }
          break;

        case 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser':
          $acsArray['Binding'] = 'urn:oasis:names:tc:SAML:2.0:profiles:holder-of-key:SSO:browser';
          $acsArray['Location'] = Module::getModuleURL('saml/sp/saml2-acs.php/' . $sourceId);
          $acsArray['hoksso:ProtocolBinding'] = Constants::BINDING_HTTP_REDIRECT;
          if (!in_array(Constants::NS_SAMLP, $supported_protocols, TRUE)) {
            $supported_protocols[] = Constants::NS_SAMLP;
          }
          break;
      }
      $eps[] = $acsArray;
      $index++;
    }

    $metaArray20['AssertionConsumerService'] = $spconfig->getArray('AssertionConsumerService', $eps);

    $keys = [];
    $certInfo = Crypto::loadPublicKey($spconfig, FALSE, 'new_');
    if ($certInfo !== NULL && array_key_exists('certData', $certInfo)) {
      $hasNewCert = TRUE;

      $certData = $certInfo['certData'];

      $keys[] = [
        'type'            => 'X509Certificate',
        'signing'         => TRUE,
        'encryption'      => TRUE,
        'X509Certificate' => $certInfo['certData'],
      ];
    }
    else {
      $hasNewCert = FALSE;
    }

    $certInfo = Crypto::loadPublicKey($spconfig);
    if ($certInfo !== NULL && array_key_exists('certData', $certInfo)) {
      $certData = $certInfo['certData'];

      $keys[] = [
        'type'            => 'X509Certificate',
        'signing'         => TRUE,
        'encryption'      => ($hasNewCert ? FALSE : TRUE),
        'X509Certificate' => $certInfo['certData'],
      ];
    }
    else {
      $certData = NULL;
    }

    $format = $spconfig->getValue('NameIDPolicy', NULL);
    if ($format !== NULL) {
      if (is_array($format)) {
        $metaArray20['NameIDFormat'] = Configuration::loadFromArray($format)->getString(
          'Format',
          Constants::NAMEID_TRANSIENT
        );
      }
      elseif (is_string($format)) {
        $metaArray20['NameIDFormat'] = $format;
      }
    }

    $name = $spconfig->getLocalizedString('name', NULL);
    $attributes = $spconfig->getArray('attributes', []);

    if ($name !== NULL && !empty($attributes)) {
      $metaArray20['name'] = $name;
      $metaArray20['attributes'] = $attributes;
      $metaArray20['attributes.required'] = $spconfig->getArray('attributes.required', []);

      if (empty($metaArray20['attributes.required'])) {
        unset($metaArray20['attributes.required']);
      }

      $description = $spconfig->getArray('description', NULL);
      if ($description !== NULL) {
        $metaArray20['description'] = $description;
      }

      $nameFormat = $spconfig->getString('attributes.NameFormat', NULL);
      if ($nameFormat !== NULL) {
        $metaArray20['attributes.NameFormat'] = $nameFormat;
      }

      if ($spconfig->hasValue('attributes.index')) {
        $metaArray20['attributes.index'] = $spconfig->getInteger('attributes.index', 0);
      }

      if ($spconfig->hasValue('attributes.isDefault')) {
        $metaArray20['attributes.isDefault'] = $spconfig->getBoolean('attributes.isDefault', FALSE);
      }
    }

    // Add organization info.
    $orgName = $spconfig->getLocalizedString('OrganizationName', NULL);
    if ($orgName !== NULL) {
      $metaArray20['OrganizationName'] = $orgName;

      $metaArray20['OrganizationDisplayName'] = $spconfig->getLocalizedString('OrganizationDisplayName', NULL);
      if ($metaArray20['OrganizationDisplayName'] === NULL) {
        $metaArray20['OrganizationDisplayName'] = $orgName;
      }

      $metaArray20['OrganizationURL'] = $spconfig->getLocalizedString('OrganizationURL', NULL);
      if ($metaArray20['OrganizationURL'] === NULL) {
        throw new Exception('If OrganizationName is set, OrganizationURL must also be set.');
      }
    }

    if ($spconfig->hasValue('contacts')) {
      $contacts = $spconfig->getArray('contacts');
      foreach ($contacts as $contact) {
        $metaArray20['contacts'][] = Metadata::getContact($contact);
      }
    }

    // Add technical contact.
    $email = $config->getString('technicalcontact_email', 'na@example.org');
    if ($email && $email !== 'na@example.org') {
      $techcontact = [
        'emailAddress' => $email,
        'name' => $config->getString('technicalcontact_name', NULL),
        'contactType' => 'technical',
      ];
      $metaArray20['contacts'][] = Metadata::getContact($techcontact);
    }

    // Add certificate.
    if (count($keys) === 1) {
      $metaArray20['certData'] = $keys[0]['X509Certificate'];
    }
    elseif (count($keys) > 1) {
      $metaArray20['keys'] = $keys;
    }

    // Add EntityAttributes extension.
    if ($spconfig->hasValue('EntityAttributes')) {
      $metaArray20['EntityAttributes'] = $spconfig->getArray('EntityAttributes');
    }

    // Add UIInfo extension.
    if ($spconfig->hasValue('UIInfo')) {
      $metaArray20['UIInfo'] = $spconfig->getArray('UIInfo');
    }

    // Add RegistrationInfo extension.
    if ($spconfig->hasValue('RegistrationInfo')) {
      $metaArray20['RegistrationInfo'] = $spconfig->getArray('RegistrationInfo');
    }

    // Add signature options.
    if ($spconfig->hasValue('WantAssertionsSigned')) {
      $metaArray20['saml20.sign.assertion'] = $spconfig->getBoolean('WantAssertionsSigned');
    }
    if ($spconfig->hasValue('redirect.sign')) {
      $metaArray20['redirect.validate'] = $spconfig->getBoolean('redirect.sign');
    }
    elseif ($spconfig->hasValue('sign.authnrequest')) {
      $metaArray20['validate.authnrequest'] = $spconfig->getBoolean('sign.authnrequest');
    }

    $metaArray20['metadata-set'] = 'saml20-sp-remote';
    $metaArray20['entityid'] = $entityId;

    $metaBuilder = new SAMLBuilder($entityId);
    $metaBuilder->addMetadataSP20($metaArray20, $supported_protocols);
    $metaBuilder->addOrganizationInfo($metaArray20);

    $xml = $metaBuilder->getEntityDescriptorText();

    unset($metaArray20['UIInfo']);
    unset($metaArray20['metadata-set']);
    unset($metaArray20['entityid']);

    // Sanitize the attributes array to remove friendly names.
    if (isset($metaArray20['attributes']) && is_array($metaArray20['attributes'])) {
      $metaArray20['attributes'] = array_values($metaArray20['attributes']);
    }

    // Sign the metadata if enabled.
    $xml = Signer::sign($xml, $spconfig->toArray(), 'SAML 2 SP');

    $name = getenv('SIMPLESAML_SSO_SP_METADATA_FILE');
    if (!$name) {
      $name = 'saml_sp_metadata.xml';
    }
    $file_name = 'private://' . $name;
    file_put_contents($file_name, $xml);
    $headers = [
      'Content-Type'     => 'application/xml',
      'Content-Disposition' => 'attachment;filename="' . $name . '"',
    ];
    return new BinaryFileResponse($file_name, 200, $headers, TRUE);

  }

}
