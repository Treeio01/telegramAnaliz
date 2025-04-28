<?php

namespace App\Services;

use libphonenumber\PhoneNumberUtil;

class GeoDetectorService
{
    public static function getGeoFromPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $numberProto = $phoneUtil->parse('+' . $phone, null);
            return $phoneUtil->getRegionCodeForNumber($numberProto);
        } catch (\Exception $e) {
            return null;
        }
    }
}
