<?php

$config = array(
  // This is a authentication source which handles admin authentication.
  'admin' => array(
    // The default is to use core:AdminPassword, but it can be replaced with
    // any authentication source.
    'core:AdminPassword',
  ),
);

$path = getenv('SIMPLESAML_CONFIG_DIR');
if (!$path) {
  $path = '/var/www/html/private/saml_config';
}
try {
  $file_count = 0;
  foreach (new DirectoryIterator($path) as $fileInfo) {
    if($fileInfo->isDot()) continue;
    $ext = $fileInfo->getExtension();
    if ($ext == 'xml') {
      $xmldata = file_get_contents($path . '/' . $fileInfo->getFilename());
      $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($xmldata);
      if (!empty($entities)) {
        $file_count++;
        $keys = array_unique(array_keys($entities));
      }
    }
  }
  // Set IDP only if we encounter 1 XML file.
  if ($file_count == 1 && !empty($keys)) {
    $idp = reset($keys);
  }
  else {
    // If there are more files, let the user choose the IDP.
    $idp = NULL;
  }
}
// If the path cannot be opened.
catch (\UnexpectedValueException $e) {
  echo "There seems to be an issue with the SSO configuration path. Please contact the administrator.";
}
// If the path is an empty string.
catch (\RuntimeException $e) {
  echo "The SSO configuration could not be loaded. Please contact the administrator.";
}

$authsource = getenv('SIMPLESAML_AUTH_SOURCE') ?? 'default-sp';
$config[$authsource] = array(
  'saml:SP',
  'entityID' => $authsource,
  'idp' => $idp,
  'discoURL' => null,
  'acs.Bindings' => array(
    'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
  ),
  'SingleLogoutServiceBinding' => array(),
  'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
  'name' => 'PERLS',
  'NameIDPolicy' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
  'attributes' => array(
    'mail',
    'sn',
    'givenName',
  ),
  'attributes.required' => array (
    'mail',
  ),
);
