<?php

class EngineBlock_Corto_Module_Service_IdpsMetadata extends EngineBlock_Corto_Module_Service_Abstract
{
    public function serve($serviceName)
    {
        // Fetch SP Entity Descriptor for the SP Entity ID that is fetched from the request
        $request = EngineBlock_ApplicationSingleton::getInstance()->getHttpRequest();
        $spEntityId = $request->getQueryParameter('sp-entity-id');
        $alternateKeys = false;
        if ($spEntityId) {
            // See if an sp-entity-id was specified for which we need to use alternate keys (key rollover)
            try {
                $spEntity = $this->_server->getRemoteEntity($spEntityId);
            } catch (EngineBlock_Corto_ProxyServer_UnknownRemoteEntityException $e) {
                $spEntityId = EngineBlock_ApplicationSingleton::getInstance()->getHttpRequest()->getQueryParameter('sp-entity-id');
                $this->_server->redirect(
                    '/authentication/feedback/unknown-service-provider?entity-id=' . urlencode($spEntityId),
                    "Unknown SP!");
                return;
            }

            // Check if an alternative Public key has been set for the requesting SP
            // If yes, use these in the metadata of EngineBlock
            if (isset($spEntity['AlternatePublicKey']) && isset($spEntity['AlternatePrivateKey'])) {
                $entityDetails['certificates'] = array(
                    'public' => $spEntity['AlternatePublicKey'],
                    'private' => $spEntity['AlternatePrivateKey'],
                );
                $alternateKeys = $entityDetails['certificates'];
            }
        }

        // Get the configuration for EngineBlock in it's IdP role.
        $entityDetails = $this->_server->getCurrentEntity('idpMetadataService');

        $idpEntities = array();
        // Note that Shibboleth likes to see it's self in the metadata, so if an sp-entity-id was passed along
        // we make sure the first thing is the Service Provider
        if (isset($spEntity)) {
            $idpEntities[] = $spEntity;
        }
        foreach ($this->_server->getRemoteEntities() as $entityId => $entity) {
            // Don't add ourselves
            if ($entity['EntityID'] === $entityDetails['EntityID']) {
                continue;
            }

            // Only add entities that have a SSO service registered
            if (!isset($entity['SingleSignOnService'])) {
                continue;
            }

            // Use EngineBlock certificates
            $entity['certificates'] = $entityDetails['certificates'];

            // Ignore the NameIDFormats the IdP supports, any requests made on this endpoint will use EngineBlock
            // NameIDs, so advertise that.
            unset($entity['NameIDFormat']);
            $entity['NameIDFormats'] = $entityDetails['NameIDFormats'];

            // Generate a URL that points to EngineBlock, but with the given IdP preselected.
            $transparentSsoUrl = $this->_server->getUrl('singleSignOnService', $entity['EntityID']);
            $entity['SingleSignOnService']['Location'] = $transparentSsoUrl;
            $entity['SingleSignOnService']['Binding']  = $entityDetails['SingleSignOnService']['Binding'];

            $entity['ContactPersons'] = $entityDetails['ContactPersons'];

            $idpEntities[] = $entity;
        }

        // Map the IdP configuration to a Corto XMLToArray structured document array
        $mapper = new EngineBlock_Corto_Mapper_Metadata_EdugainDocument(
            $this->_server->getNewId(),
            $this->_server->timeStamp($this->_server->getConfig('metadataValidUntilSeconds', 86400))
        );
        $document = $mapper->setEntities($idpEntities)->map();

        // Sign the document
        $document = $this->_server->sign(
            $document,
            ($alternateKeys  ? $alternateKeys['public']  : null),
            ($alternateKeys  ? $alternateKeys['private']  : null)
        );

        // Convert the document to XML
        $xml = EngineBlock_Corto_XmlToArray::array2xml($document);

        /* Test */
        $xmlCreatedWithSerializer = $this->createXmlWithSerializer();
        die($xmlCreatedWithSerializer);
        /* end test */

        // If debugging is enabled then validate it according to the schema
        if ($this->_server->getConfig('debug', false)) {
            $validator = new EngineBlock_Xml_Validator(
                'http://docs.oasis-open.org/security/saml/v2.0/saml-schema-metadata-2.0.xsd'
            );
            $validator->validate($xml);
        }

        // The spec dictates we use a custom mimetype, but debugging is easier with a normal mimetype
        // also no single SP / IdP complains over this.
        //$this->_server->sendHeader('Content-Type', 'application/samlmetadata+xml');
        $this->_server->sendHeader('Content-Type', 'application/xml');
        $this->_server->sendOutput($xml);
    }

    /**
     * Disable VO context and do something, then reinstate the VO context.
     *
     * @param callable $callbackFn Callback function to execute when no VO Context is set
     * @todo does not seem to be used, remove?
     */
    protected function _withNoVoContext($callbackFn)
    {
        $voContext = $this->_server->getVirtualOrganisationContext();
        $this->_server->setVirtualOrganisationContext(null);

        $callbackFn();

        $this->_server->setVirtualOrganisationContext($voContext);
    }

    /**
     * Proof of concept code using JMS serializer to replace Corto XML to Array
     */
    private function createXmlWithSerializer()
    {
        header('Content-Type: text/plain');

        // Create entities descriptor document
        $entitiesDescriptor = new EngineBlock_XmlDocument_MetaData_EntitiesDescriptor();
        $entitiesDescriptor->setValidUntil('2013-02-08T14:04:37Z');
        $entitiesDescriptor->setId('CORTO11bf3363d5d4bed6d06794bae147c1ee4c6e3b3c');

        // Create signature
        $signature = new EngineBlock_XmlDocument_Dsig_Signature();
        $signedInfo = new EngineBlock_XmlDocument_Dsig_Signature_SignedInfo();
        $signature->setSignedInfo($signedInfo   );
        $entitiesDescriptor->setSignature($signature);

        // Create entity descriptors
        $entityDescriptorList = array();
        for ($i = 1; $i <= 4; $i++) {
            $entityDescriptor = new EngineBlock_XmlDocument_MetaData_EntitiesDescriptor_EntityDescriptor();
            $entityDescriptor->setEntityId('EntityNr' . $i);
            $entityDescriptorList[] = $entityDescriptor;
        }
        $entitiesDescriptor->setEntityDescriptors($entityDescriptorList);

        // Get serializer
        $diContainer = EngineBlock_ApplicationSingleton::getInstance()->getDiContainer();
        /** @var $serializer \JMS\SerializerBundle\Serializer\Serializer */
        $serializer = $diContainer[$diContainer::SERIALIZER];

        // Try to serialize document to xml
        try {
            return $serializer->serialize($entitiesDescriptor, 'xml');
        } catch (Exception $ex) {
            die('Serializer error (' . get_class($ex) . ') ' . $ex->getMessage());
        }
    }
}