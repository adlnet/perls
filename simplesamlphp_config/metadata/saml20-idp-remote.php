<?php
/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * Remember to remove the IdPs you don't use from this file.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote
 */

$path = getenv('SIMPLESAML_CONFIG_DIR');
if (!$path) {
  $path = '/var/www/html/private/saml_config';
}
try {
  foreach (new DirectoryIterator($path) as $fileInfo) {
    if ($fileInfo->isDot()) continue;
    $ext = $fileInfo->getExtension();
    if ($ext == 'xml') {
      $xmldata = file_get_contents($path . '/' . $fileInfo->getFilename());
      $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($xmldata);

      if (!empty($entities)) {
        // Get all metadata for the entities.
        foreach ($entities as &$entity) {
          $entity = [
            'shib13-sp-remote' => $entity->getMetadata1xSP(),
            'shib13-idp-remote' => $entity->getMetadata1xIdP(),
            'saml20-sp-remote' => $entity->getMetadata20SP(),
            'saml20-idp-remote' => $entity->getMetadata20IdP(),
          ];
        }

        // Transpose from $entities[entityid][type] to $output[type][entityid]
        $output = \SimpleSAML\Utils\Arrays::transpose($entities);

        foreach ($output as $type => &$entities) {
          foreach ($entities as $entityId => $entityMetadata) {
            if ($entityMetadata === NULL) {
              continue;
            }

            // Remove the entityDescriptor element because it is unused.
            unset($entityMetadata['entityDescriptor']);

            $metadata[$entityId] = $entityMetadata;
          }
        }
      }
    }
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
