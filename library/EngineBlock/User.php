<?php

class EngineBlock_User
{
    /**
     * @var EngineBlock_User_InetOrgPerson
     */
    private $inetOrgPerson;

    /**
     * @var EngineBlock_User_EduPerson
     */
    private $eduPerson;

    /**
     * @var EngineBlock_User_NlEduPerson
     */
    private $nlEduPerson;

    /**
     * @var EngineBlock_User_CollabPerson
     */
    private $collabPerson;

    /**
     * @param EngineBlock_User_InetOrgPerson $inetOrgPerson
     * @param EngineBlock_User_EduPerson     $eduPerson
     * @param EngineBlock_User_NlEduPerson   $nlEduPerson
     * @param EngineBlock_User_CollabPerson  $collabPerson
     */
    public function __construct(
        EngineBlock_User_InetOrgPerson  $inetOrgPerson,
        EngineBlock_User_EduPerson      $eduPerson,
        EngineBlock_User_NlEduPerson    $nlEduPerson,
        EngineBlock_User_CollabPerson   $collabPerson
    ) {
        $this->inetOrgPerson = $inetOrgPerson;
        $this->eduPerson     = $eduPerson;
        $this->nlEduPerson   = $nlEduPerson;
        $this->collabPerson  = $collabPerson;
    }

    public function getCollabPersonUuid()
    {
        return $this->collabPerson->collabPersonUuid;
    }

    public function getUid()
    {
        return $this->inetOrgPerson->uid;
    }

    public function getOrganization()
    {
        return $this->inetOrgPerson->o;
    }

    public function toArray()
    {
        $result = array();
        $result = array_merge($result, get_object_vars($this->inetOrgPerson));
        $result = array_merge($result, get_object_vars($this->eduPerson));
        $result = array_merge($result, get_object_vars($this->nlEduPerson));
        $result = array_merge($result, get_object_vars($this->collabPerson));
        return $result;
    }

    public function toArray()
    {
        $result = array();
        $result = array_merge($result, get_object_vars($this->inetOrgPerson));
        $result = array_merge($result, get_object_vars($this->eduPerson));
        $result = array_merge($result, get_object_vars($this->nlEduPerson));
        $result = array_merge($result, get_object_vars($this->collabPerson));
        return $result;
    }
}
