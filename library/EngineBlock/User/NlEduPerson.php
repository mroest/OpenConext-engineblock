<?php

class EngineBlock_User_NlEduPerson
{
    /**
     * Name of the section.
     * @example array("Faculteit der Letteren", "Bibliotheek", "IT Diensten")
     *
     * @var string[]
     */
    public $nlEduPersonOrgUnit;

    /**
     * Education; numeric string containing CROHO code; empty it it's an unregular education.
     *
     * @example 52734
     * @see http://www.ib-groep.nl/zakelijk/HO/CROHO/Raadplegen_of_downloaden_CROHO.asp
     *
     * @var string[]
     */
    public $nlEduPersonStudyBranch;

    /**
     * Studielinknumber of student as registered at www.studielink.nl
     *
     * @var string[]
     */
    public $nlStudielinkNummer;
}
