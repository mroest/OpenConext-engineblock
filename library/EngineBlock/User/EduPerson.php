<?php

class EngineBlock_User_EduPerson
{
    /**
     * Specifies a person's relationship(s) to the institution in
     * broad categories such as student, faculty, staff, alum, etc.
     *
     * @var string[]
     */
    public $eduPersonAffiliation;

    /**
     * Specifies a person's nickname, or the informal name by which
     * they are accustomed to be hailed.
     *
     * @var string[]
     */
    public $eduPersonNickname;

    /**
     * The distinguished name (DN) of the directory entry
     * representing the institution with which the person
     * is associated.
     *
     * @var string
     */
    public $eduPersonOrgDN;

    /**
     * The distinguished name (DN) of the directory entries representing
     * the person's Organizational Unit(s).
     *
     * @var string
     */
    public $eduPersonOrgUnitDN;

    /**
     * Specifies a person's PRIMARY relationship to the institution
     * in broad categories such as student, faculty, staff, alum, etc.
     *
     * @var string
     */
    public $eduPersonPrimaryAffiliation;

    /**
     * The "NetID" of the person for the purposes of inter-institutional
     * authentication.  Should be stored in the form of user@univ.edu,
     * where univ.edu is the name of the local security domain.
     *
     * @var string
     */
    public $eduPersonPrincipalName;

    /**
     * @var string[]
     */
    public $eduPersonEntitlement;

    /**
     * @var string
     */
    public $eduPersonPrimaryOrgUnitDN;

    /**
     * @see http://www2.surfnet.nl/diensten/ldap/oid/
     *
     * @var string
     */
    public $eduPersonScopedAffiliation;
}
