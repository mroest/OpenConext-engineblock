<?php

/**
 * Subset of the InetOrgPerson schema that we use.
 *
 * @see https://tools.ietf.org/html/rfc2798
 */
class EngineBlock_User_InetOrgPerson
{
    /**
     * @var string
     */
    public $uid;

    /**
     * MUST
     *
     * @var string[]
     */
    public $cn;

    /**
     * @var string[]
     */
    public $givenName;

    /**
     * MUST
     *
     * @var string[]
     */
    public $sn;

    /**
     * @var string[]
     */
    public $displayName;

    /**
     * @var string[]
     */
    public $mail;

    /**
     * @var string
     */
    public $o;
}
