<?php

class EngineBlock_User_UserLdapHydrator
{
    /**
     * Extract values from an object
     *
     * @param EngineBlock_User $user
     * @return array
     */
    public function extract(EngineBlock_User $user)
    {
        return $user->toArray();
    }

    /**
     * Hydrate $object with the provided $data.
     *
     * @param  array $data
     * @return EngineBlock_User
     */
    public function hydrate(array $data)
    {

    }
}
