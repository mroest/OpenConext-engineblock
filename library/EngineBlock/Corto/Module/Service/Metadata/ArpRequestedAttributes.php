<?php
use OpenConext\Component\EngineBlockMetadata\RequestedAttribute;
use OpenConext\Component\EngineBlockMetadata\Entity\AbstractRole;
use OpenConext\Component\EngineBlockMetadata\Entity\ServiceProvider;

/**
 * Add the RequestedAttributes for the AttributeConsumingService section in the SPSSODescriptor based on the ARP of the SP
 */

class EngineBlock_Corto_Module_Service_Metadata_ArpRequestedAttributes
{
    public function addRequestAttributes(AbstractRole $entity)
    {
        if (!$entity instanceof ServiceProvider) {
            return $entity;
        }

        $arp = $this->getMetadataRepository()->fetchServiceProviderArp($entity);
        if (!$arp) {
            return $entity;
        }

        $attributeNames = $arp->getAttributeNames();

        $entity->requestedAttributes = array();
        foreach ($attributeNames as $attributeName) {
            $entity->requestedAttributes[] = new RequestedAttribute($attributeName);
        }

        return $entity;
    }


    protected function getMetadataRepository()
    {
        return EngineBlock_ApplicationSingleton::getInstance()->getDiContainer()->getMetadataRepository();
    }
}
