<?php

namespace Ngenius\NgeniusCommon;

use megastruktur\PhoneCountryCodes;

class NgeniusUtilities
{
    /*
     * @parameters countrycode
     * returns country prefix e.g. 27
     */
    public function getCountryTelephonePrefix($countryCode): string
    {
        $phoneCodes  = new PhoneCountryCodes();
        $countries   = $phoneCodes->getCodesList();
        $phonePrefix = $countries[$countryCode];

        return substr($phonePrefix, 1);
    }

}
