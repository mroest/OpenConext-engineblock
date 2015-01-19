<?php

class EngineBlock_User_UserLdapMapper
{
    const LDAP_CLASS_COLLAB_PERSON                  = 'collabperson';

    const LDAP_ATTR_COLLAB_PERSON_ID                = 'collabpersonid';
    const LDAP_ATTR_COLLAB_PERSON_UUID              = 'collabpersonuuid';
    const LDAP_ATTR_EDUPERSON_EPPN                  = 'edupersonprincipalname';
    const LDAP_ATTR_COLLAB_PERSON_HASH              = 'collabpersonhash';
    const LDAP_ATTR_COLLAB_PERSON_REGISTERED        = 'collabpersonregistered';
    const LDAP_ATTR_COLLAB_PERSON_LAST_ACCESSED     = 'collabpersonlastaccessed';
    const LDAP_ATTR_COLLAB_PERSON_LAST_UPDATED      = 'collabpersonlastupdated';

    /**
     * @var Zend_Log
     */
    private $logger;

    /**
     * @var Zend_Ldap
     */
    private $ldapClient;

    /**
     * @var EngineBlock_User_UserLdapHydrator
     */
    private $hydrator;

    private $OBJECT_CLASSES = array(
        'collabPerson',
        'nlEduPerson',
        'inetOrgPerson',
        'organizationalPerson',
        'person',
        'top',
    );

    public function findUserByCollabPersonId($collabPersonId)
    {
        $entry = $this->findOneEntry(self::LDAP_ATTR_COLLAB_PERSON_ID, $collabPersonId);

        if (!$entry) {
            return null;
        }

        return $this->hydrator->hydrate($entry);
    }

    public function findUserByEduPersonPrincipalName($eduPersonPrincipalName)
    {
        $entry = $this->findOneEntry(self::LDAP_ATTR_EDUPERSON_EPPN, $eduPersonPrincipalName);

        if (!$entry) {
            return null;
        }

        return $this->hydrator->hydrate($entry);
    }

    public function findUserByCollabPersonUuid($collabPersonUuid)
    {
        $entry = $this->findOneEntry(self::LDAP_ATTR_COLLAB_PERSON_UUID, $collabPersonUuid);

        if (!$entry) {
            return null;
        }

        return $this->hydrator->hydrate(
            $entry
        );
    }

    /**
     * @param $fieldName
     * @param $fieldValue
     * @return array|null
     * @throws EngineBlock_Exception
     * @throws Zend_Ldap_Exception
     */
    private function findOneEntry($fieldName, $fieldValue)
    {
        $filter = '(&(objectclass=' . self::LDAP_CLASS_COLLAB_PERSON . ')';
        $filter .= '(' . $fieldName . '=' . Zend_Ldap_Dn::escapeValue($fieldValue) . '))';

        $collection = $this->ldapClient->search(
            $filter,
            null,
            Zend_Ldap::SEARCH_SCOPE_SUB
        );

        if (!$collection) {
            throw new EngineBlock_Exception(
                "LDAP failure trying to find an entry for '$fieldName', '$fieldValue'"
                . ', last error code: ' . $this->ldapClient->getLastErrorCode()
                . ', last error message: ' . $this->ldapClient->getLastError()
            );
        }

        if ($collection->count() === 0) {
            return null;
        }

        if ($collection->count() > 1) {
            return null;
        }

        $collection->rewind();
        return $collection->current();
    }

    public function save(EngineBlock_User $user, $retries = 1)
    {
        try {
            return $this->trySave($user);
        } catch (Zend_Ldap_Exception $e) {
            // Note that during high volumes of logins (like during a performance test) we may see a find
            // not returning a user, then another process registering the user, then the current process failing to
            // add the user because it was already added...
            // So if a user has already been added we simply try again
            if ($retries > 0 && $e->getCode() === Zend_Ldap_Exception::LDAP_ALREADY_EXISTS) {
                $this->logger->log('Retrying save for user: ' . $this->getDnForUser($user), Zend_Log::WARN);
                return $this->save($user, --$retries);
            }

            throw new EngineBlock_Exception("LDAP failure", EngineBlock_Exception::CODE_ALERT, $e);
        }
    }

    private function trySave(EngineBlock_User $user)
    {
        if (!$user->getCollabPersonUuid()) {
            return $this->insert($user);
        }

        return $this->update($user);
    }

    private function insert(EngineBlock_User $user)
    {
        $entry = $user->toArray();

        $this->registerOrganization($entry['o']);

        $entry[self::LDAP_ATTR_COLLAB_PERSON_UUID] = (string)Surfnet_Zend_Uuid::generate();
        $entry[self::LDAP_ATTR_COLLAB_PERSON_HASH] = $this->getCollabPersonHash($entry);

        $now = date(DATE_RFC822);
        $entry[self::LDAP_ATTR_COLLAB_PERSON_REGISTERED]    = $now;
        $entry[self::LDAP_ATTR_COLLAB_PERSON_LAST_ACCESSED] = $now;
        $entry[self::LDAP_ATTR_COLLAB_PERSON_LAST_UPDATED]  = $now;

        $entry['objectClass'] = $this->OBJECT_CLASSES;

        $this->ldapClient->add($this->getDnForUser($user), $entry);

        $hydrator = new EngineBlock_User_UserLdapHydrator();
        return $hydrator->hydrate($entry);
    }

    private function update(EngineBlock_User $user)
    {
        $oldEntry = $this->findOneEntry(self::LDAP_ATTR_COLLAB_PERSON_UUID, $user->getCollabPersonUuid());
        $newEntry = $user->toArray();

        // Verify that something changed by comparing hashes, if nothing chaged we can save on an LDAP write.
        $newEntry[self::LDAP_ATTR_COLLAB_PERSON_HASH] = $this->getCollabPersonHash($newEntry);
        if ($newEntry[self::LDAP_ATTR_COLLAB_PERSON_HASH] === $oldEntry[self::LDAP_ATTR_COLLAB_PERSON_HASH]) {
            $oldEntry[self::LDAP_ATTR_COLLAB_PERSON_LAST_ACCESSED] = date(DATE_RFC822);

            // Note here that we never persist last-accessed, so it's really only interesting for historical reasons.
            // @see https://github.com/OpenConext/OpenConext-engineblock/issues/15

            return $this->hydrator->hydrate($oldEntry);
        }

        $now = date(DATE_RFC822);
        $entry[self::LDAP_ATTR_COLLAB_PERSON_REGISTERED]    = $now;
        $entry[self::LDAP_ATTR_COLLAB_PERSON_LAST_ACCESSED] = $now;
        $entry[self::LDAP_ATTR_COLLAB_PERSON_LAST_UPDATED]  = $now;

        $this->ldapClient->update($this->getDnForUser($user), $entry);

        return $this->hydrator->hydrate($entry);
    }

    public function remove(EngineBlock_User $user)
    {
        $this->ldapClient->delete($this->getDnForUser($user));
    }

    private function getDnForUser(EngineBlock_User $user)
    {
        return 'uid=' . Zend_Ldap_Filter::escapeValue($user->getUid())
            . ',o=' . Zend_Ldap_Filter::escapeValue($user->getOrganization())
            . ',' . $this->ldapClient->getBaseDn();
    }

    private function registerOrganization($organization)
    {
        $dn = 'o=' . $organization . ',' . $this->ldapClient->getBaseDn();

        if ($this->ldapClient->exists($dn)) {
            return;
        }

        $entry = array(
            'o' => $organization ,
            'objectclass' => array(
                'organization' ,
                'top'
            )
        );
        $result = $this->ldapClient->add($dn, $entry);

        if (!$result instanceof Zend_Ldap) {
            throw new EngineBlock_Exception("Unable to add LDAP organization $dn");
        }
    }

    private function getCollabPersonHash($entry)
    {
        return md5(http_build_query($entry));
    }
}
