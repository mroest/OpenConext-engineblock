<?php

class EngineBlock_User_Assembler
{
    const URN_COLLAB_PERSON_NAMESPACE               = 'urn:collab:person';
    const URN_IS_MEMBER_OF                          = 'urn:mace:dir:attribute-def:isMemberOf';

    /**
     * @var Zend_Log
     */
    private $logger;

    /**
     * @var string
     */
    private $guestQualifier;

    public function assemble(array $attributes)
    {
        $inetOrgPerson = $this->assembleInetOrgPerson($attributes);
        $eduPerson = $this->assembleEduPerson($attributes);

        $inetOrgPerson = $this->augmentInetOrgPerson($inetOrgPerson, $eduPerson);

        return new EngineBlock_User(
            $inetOrgPerson,
            $eduPerson,
            $this->assembleNlEduPerson($attributes),
            $this->assembleCollabPerson($inetOrgPerson, $attributes)
        );
    }

    private function assembleInetOrgPerson(array $attributes)
    {
        $inetOrgPerson = new EngineBlock_User_InetOrgPerson();

        // Note that inetOrgPerson says that this can be multi valued, but we need a canonical one.
        $inetOrgPerson->uid         = $this->getSingleAttributeValue(
            $attributes, 'urn:mace:dir:attribute-def:uid'
        );
        $inetOrgPerson->cn          = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:dir:attribute-def:cn'
        );
        $inetOrgPerson->givenName   = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:dir:attribute-def:givenName'
        );
        $inetOrgPerson->sn          = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:dir:attribute-def:sn'
        );
        $inetOrgPerson->displayName = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:dir:attribute-def:displayName'
        );
        $inetOrgPerson->mail        = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:dir:attribute-def:mail'
        );

        // Note that inetOrgPerson says this can be multi valued, but we need a canonical one.
        $inetOrgPerson->o           = $this->getSingleAttributeValue(
            $attributes, 'urn:mace:terena.org:attribute-def:schacHomeOrganization'
        );
        return $inetOrgPerson;
    }

    /**
     * @param EngineBlock_User_InetOrgPerson $inetOrgPerson
     * @param EngineBlock_User_EduPerson $eduPerson
     * @return EngineBlock_User_InetOrgPerson
     * @throws Zend_Log_Exception
     */
    private function augmentInetOrgPerson(
        EngineBlock_User_InetOrgPerson $inetOrgPerson,
        EngineBlock_User_EduPerson $eduPerson
    ) {
        // Try to guess the commonName if it wasn't supplied because the inetOrgPerson schema requires one.
        if (empty($inetOrgPerson->cn)) {
            $inetOrgPerson->cn = $this->guessCommonName($inetOrgPerson);
            $this->logger->log('Guessed the common name ' . var_export($inetOrgPerson->cn, true), Zend_Log::NOTICE);
        }

        // Use the commonName as surName if it wasn't supplied because the inetOrgPerson schema requires one
        if (empty($inetOrgPerson->sn)) {
            $inetOrgPerson->sn = $inetOrgPerson->cn;
            $this->logger->log(
                'Used commonName as the surName ' . var_export($inetOrgPerson->sn, true),
                Zend_Log::NOTICE
            );
        }

        // Use the EPPN local part as UID if a UID wasn't supplied.
        if (empty($inetOrgPerson->uid) && !empty($eduPerson->eduPersonPrincipalName)) {
            list($inetOrgPerson->uid) = explode('@', $eduPerson->eduPersonPrincipalName);
        }

        // Use the EPPN domain part as organization if it wasn't supplied.
        if (empty($inetOrgPerson->o) && !empty($eduPerson->eduPersonPrincipalName)) {
            list(, $inetOrgPerson->o) = explode('@', $eduPerson->eduPersonPrincipalName);
        }

        return $inetOrgPerson;
    }

    /**
     * @param EngineBlock_User_InetOrgPerson $inetOrgPerson
     * @return string[]
     */
    protected function guessCommonName(EngineBlock_User_InetOrgPerson $inetOrgPerson)
    {
        if (!empty($inetOrgPerson->givenName) && !empty($inetOrgPerson->sn)) {
            return array($inetOrgPerson->givenName[0] . ' ' . $inetOrgPerson->sn[0]);
        }

        if (!empty($inetOrgPerson->sn)) {
            return $inetOrgPerson->sn;
        }

        if (!empty($inetOrgPerson->displayName)) {
            return (array) $inetOrgPerson->displayName;
        }

        if (!empty($inetOrgPerson->mail)) {
            return $inetOrgPerson->mail;
        }

        if (!empty($inetOrgPerson->givenName)) {
            return $inetOrgPerson->givenName;
        }

        if (!empty($inetOrgPerson->uid)) {
            return (array) $inetOrgPerson->uid;
        }

        throw new \RuntimeException('Unable to guess required commonName!');
    }

    private function assembleEduPerson(array $attributes)
    {
        $eduPerson = new EngineBlock_User_EduPerson();
        $eduPerson->eduPersonAffiliation        = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonAffiliation'
        );
        $eduPerson->eduPersonNickname           = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonNickname'
        );
        $eduPerson->eduPersonOrgDN              = $this->getSingleAttributeValue(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonOrgDN'
        );
        $eduPerson->eduPersonOrgUnitDN          = $this->getSingleAttributeValue(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonOrgUnitDN'
        );
        $eduPerson->eduPersonPrimaryAffiliation = $this->getSingleAttributeValue(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonPrimaryAffiliation'
        );
        $eduPerson->eduPersonPrincipalName      = $this->getSingleAttributeValue(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonPrincipalName'
        );
        $eduPerson->eduPersonEntitlement        = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonEntitlement'
        );
        $eduPerson->eduPersonPrimaryOrgUnitDN   = $this->getSingleAttributeValue(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonPrimaryOrgUnitDN'
        );
        $eduPerson->eduPersonScopedAffiliation  = $this->getSingleAttributeValue(
            $attributes, 'urn:mace:dir:attribute-def:eduPersonScopedAffiliation'
        );
        return $eduPerson;
    }

    private function assembleNlEduPerson(array $attributes)
    {
        $nlEduPerson = new EngineBlock_User_NlEduPerson();
        $nlEduPerson->nlEduPersonOrgUnit = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:surffederatie.nl:attribute-def:nlEduPersonOrgUnit'
        );
        $nlEduPerson->nlEduPersonStudyBranch = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:surffederatie.nl:attribute-def:nlEduPersonStudyBranch'
        );
        $nlEduPerson->nlStudielinkNummer = $this->getMultipleAttributeValues(
            $attributes, 'urn:mace:surffederatie.nl:attribute-def:nlStudielinkNummer'
        );
        return $nlEduPerson;
    }

    private function assembleCollabPerson(EngineBlock_User_InetOrgPerson $inetOrgPerson, array $attributes)
    {
        $collabPerson = new EngineBlock_User_CollabPerson();
        $collabPerson->collabPersonId       = $this->assembleCollabPersonId($inetOrgPerson, $attributes);
        $collabPerson->collabPersonIsGuest  = $this->assembleCollabPersonIsGuest($attributes);
        return $collabPerson;
    }

    private function assembleCollabPersonId(EngineBlock_User_InetOrgPerson $inetOrgPerson, array $attributes)
    {
        $uid = str_replace('@', '_', $inetOrgPerson->uid);
        return self::URN_COLLAB_PERSON_NAMESPACE . ':' . $inetOrgPerson->o . ':' . $uid;
    }

    /**
     * Figure out of a person with given attributes is a guest user.
     *
     * @param array $attributes
     * @return bool
     */
    private function assembleCollabPersonIsGuest(array $attributes)
    {
        return !isset($attributes[self::URN_IS_MEMBER_OF])
            || !in_array($this->guestQualifier, $attributes[self::URN_IS_MEMBER_OF]);
    }

    private function getMultipleAttributeValues($attributes, $attributeName, $default = array())
    {
        return isset($attributes[$attributeName]) ? $attributes[$attributeName] : $default;
    }

    private function getSingleAttributeValue($attributes, $attributeName, $default = '')
    {
        $attributeValues = $this->getMultipleAttributeValues($attributes, $attributeName);
        if (empty($attributeValues)) {
            return '';
        }

        $this->logger->log('Truncating values: ' . var_export($attributeValues, true), Zend_Log::NOTICE);
        return $attributeValues[0];
    }
}
