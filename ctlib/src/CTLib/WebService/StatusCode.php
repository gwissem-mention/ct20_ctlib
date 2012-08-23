<?php
namespace CTLib\WebService;

/**
 * Contains web service response status code constants and their internal-use
 * messages.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class StatusCode
{
    const SUCCESS                       = 1;

    const ERROR_PROCESSING_REQUEST      = 900;
    const ERROR_PROCESSING_REQUEST_PART = 901;
    const SKIPPED_REQUEST_PART          = 902;
    const INVALID_REQUEST_PART_TYPE     = 903;
    const INVALID_REQUEST_PART_ID       = 904;
    const INVALID_REQUEST_PART_BUFFER   = 905;
    const INVALID_REQUEST_PART_CONFIG_VERSION = 906;

    const MISSING_PROVISION_ID          = 1000;
    const MISSING_PROVISION_AUTH        = 1001;
    const INVALID_PROVISION_ID          = 1002;
    const INVALID_PROVISION_AUTH        = 1003;

    const MISSING_SITE_ID               = 1010;
    const MISSING_SITE_AUTH             = 1011;
    const INVALID_SITE_ID               = 1012;
    const INVALID_SITE_AUTH             = 1013;
    const MISSING_INTERFACE_AUTH        = 1014;
    const INVALID_INTERFACE_AUTH        = 1015;
    
    const SITE_DISABLED                 = 1020;
    const SITE_MAINTENANCE              = 1021;
    const SITE_NO_VALID_LOCALES         = 1025;

    const MISSING_REQUEST               = 1030;
    const MALFORMED_REQUEST             = 1031;
    const DUPLICATE_REQUEST             = 1032;

    const MISSING_REQUEST_PART          = 1035;
    const MALFORMED_REQUEST_PART        = 1036;
    const DUPLICATE_REQUEST_PART        = 1037;
    
    const DEVICE_DUPLICATE_PHONE        = 1040;
    const DEVICE_DUPLICATE_PIN          = 1041;
    const DEVICE_DUPLICATE_MAC_ADDRESS  = 1042;
    const DEVICE_DUPLICATE_HARDWARE_ID  = 1043;

    const MISSING_DEVICE_ID             = 1050;
    const INVALID_DEVICE_ID             = 1051;

    const DEVICE_DISABLED               = 1060;
    const DEVICE_NOT_REGISTERED_TO_MEMBER = 1061;

    const INVALID_MEMBER_CREDENTIALS    = 1070;
    const MULTIPLE_MEMBER_MATCH         = 1071;

    const MISSING_MEMBER_ID             = 1080;
    const INVALID_MEMBER_ID             = 1081;
    
    const MEMBER_DISABLED               = 1090;
    const MEMBER_ALREADY_HAS_DEVICE     = 1091;
    const MEMBER_HAS_NO_DEVICE          = 1092;
    const MEMBER_NOT_REGISTERED_TO_DEVICE = 1093;
    const MEMBER_LOCKED_FROM_USING_DEVICE = 1094;

    const DUPLICATE_DEVICE_ACTIVITY_ID  = 2000;
    


    public static $messages = array(
        self::ERROR_PROCESSING_REQUEST => 'Error processing request',
        self::ERROR_PROCESSING_REQUEST_PART => 'Error processing request part',
        self::SKIPPED_REQUEST_PART => 'Skipped request part',
        self::INVALID_REQUEST_PART_TYPE => 'Invalid request part type',
        self::INVALID_REQUEST_PART_ID => 'Invalid request part id',
        self::INVALID_REQUEST_PART_BUFFER => 'Invalid request part buffer',
        self::INVALID_REQUEST_PART_CONFIG_VERSION => 'Invalid request part config version',

        self::MISSING_PROVISION_ID => 'Missing Provision ID in querystring',
        self::MISSING_PROVISION_AUTH => 'Missing Provision Auth in header',
        self::INVALID_PROVISION_ID => 'Invalid Provision ID',
        self::INVALID_PROVISION_AUTH => 'Invalid Provision Auth',

        self::MISSING_SITE_ID => 'Missing Site ID in querystring',
        self::MISSING_SITE_AUTH => 'Missing Site Auth in header',
        self::INVALID_SITE_ID => 'Invalid Site ID',
        self::INVALID_SITE_AUTH => 'Invalid Site Auth',
        self::MISSING_INTERFACE_AUTH => 'Missing Interface Auth',
        self::INVALID_INTERFACE_AUTH => 'Invalid Interface Auth',                

        self::SITE_DISABLED => 'Site is disabled',
        self::SITE_MAINTENANCE => 'Site is undergoing maintenance',
        self::SITE_NO_VALID_LOCALES => 'No valid locales for request',

        self::MISSING_REQUEST => 'Request message not in POST',
        self::MALFORMED_REQUEST => 'Malformed request message',
        self::DUPLICATE_REQUEST => 'Duplicate request message',

        self::MISSING_REQUEST_PART => 'Missing request part',
        self::MALFORMED_REQUEST_PART => 'Malformed request part',
        self::DUPLICATE_REQUEST_PART => 'Duplicate request part',

        self::DEVICE_DUPLICATE_PHONE => 'Device Phone Number already exists',
        self::DEVICE_DUPLICATE_PIN => 'Device PIN already exists',
        self::DEVICE_DUPLICATE_MAC_ADDRESS => 'Device MAC address already exists',
        self::DEVICE_DUPLICATE_HARDWARE_ID => 'Device Hardware ID already exists',

        self::MISSING_DEVICE_ID => 'Device ID not set in request',
        self::INVALID_DEVICE_ID => 'Invalid Device ID',

        self::DEVICE_DISABLED => 'Device is disabled',
        self::DEVICE_NOT_REGISTERED_TO_MEMBER => 'Device not registered to member',

        self::INVALID_MEMBER_CREDENTIALS => 'Invalid Member registration credentials',
        self::MULTIPLE_MEMBER_MATCH => 'Multiple Members match registration credentials',

        self::MISSING_MEMBER_ID => 'Member ID not set in request',
        self::INVALID_MEMBER_ID => 'Invalid Member ID',

        self::MEMBER_DISABLED => 'Member is disabled',
        self::MEMBER_ALREADY_HAS_DEVICE => 'Member already has enabled device',
        self::MEMBER_HAS_NO_DEVICE => 'Member not registered to any device',
        self::MEMBER_NOT_REGISTERED_TO_DEVICE => 'Member not registered to this device',
        self::MEMBER_LOCKED_FROM_USING_DEVICE => 'Member locked from using this device',

        self::DUPLICATE_DEVICE_ACTIVITY_ID => 'Duplicate device activity id',

    );

}