<?php

namespace CTLib\Component\ActionLog;

/**
 * Class to hold all possible action constants.
 *
 * @author Mike Turoff <mturoff@celltrak.com>
 */
class ActionCode
{
    /**
     *  Status changes
     */
    //const CHANGE_ACTIVITY_STATUS = 1;
    const CHANGE_ACTIVITY_ALERT_STATUS = 2;
    const CHANGE_MEMBER_STATUS = 3;
    const CHANGE_SCHEDULE_STATUS = 4;

    /**
     * Activity Actions
     */
    //const CREATE_ACTIVITY                       = 1000;
    //const VIEW_CURRENT_ACTIVITIES               = 1001;
    const VIEW_ACTIVITY_DETAIL                  = 1002;

    const UPDATE_ACTIVITY_DURATION              = 1020;
    const UPDATE_ACTIVITY_ADJUSTED_TRAVEL       = 1021;
    const UPDATE_ACTIVITY_FORM_RESPONSE         = 1022;
    const UPDATE_ACTIVITY_EXPORT                = 1023;
    const UPDATE_ACTIVITY_PLACEHOLDER           = 1024;
    const UPDATE_ACTIVITY_STUCK                 = 1025;
    const UPDATE_ACTIVITY_SERVICE_LOCATION      = 1026;
    const DELETE_ACTIVITY                       = 1030;
    const CLOSE_ACTIVITY                        = 1031;
    const CLOSE_ACTIVITY_IN_BATCH               = 1032;
    const EXPORT_PAYROLL                        = 1033;
    const REJECT_DETACHED_FORM                  = 1034;
    const STOP_ACTIVITY_MANUAL                  = 1035;

    //const VIEW_ACTIVITY_ALERTS                  = 1040;
    const ACKNOWLEDGE_ALERT                     = 1041;
    const FIX_ALERT                             = 1042;

    //const VIEW_CLOSED_ACTIVITIES                = 1050;


    /**
     * Schedule Actions
     */
    const UPDATE_SCHEDULE_DETAIL                = 2020;
    const UPDATE_SCHEDULE_MEMBER                = 2021;
    const UPDATE_SCHEDULE_FILTER                = 2022;
    const UPDATE_SCHEDULE_ATTRIBUTE             = 2023;
    const SCHEDULE_IMPORT                       = 2024;
    const SCHEDULE_ADD_ACTIVITY                 = 2025;
    const SCHEDULE_EDIT_ACTIVITY                = 2026;
    const SCHEDULE_SAVE_ACTIVITY                = 2027;
    const SCHEDULE_DELETE_ACTIVITY              = 2028;
    //const VIEW_SCHEDULES                        = 2060;


    /**
     * HCTP Actions
     */
    const VIEW_ASSESSMENTS                      = 5001;
    const VIEW_ASSESSMENT_DETAIL                = 5002;
    const VIEW_CONSOLIDATED_ASSESSMENT          = 5003;
    const VIEW_CLINICAL_SUMMARY                 = 5004;
    const PRINT_ASSESSMENT                      = 5005;
    const PRINT_CONSOLIDATED_ASSESSMENT         = 5006;
    const FORM_APPROVE                          = 5007;
    const VIEW_PROGRESS_NOTES                   = 5100;
    const ADD_PROGRESS_NOTE                     = 5101;
    const EDIT_PROGRESS_NOTE                    = 5102;
    const SAVE_PROGRESS_NOTE                    = 5103;
    const VIEW_COMMUNITY_BOARD                  = 5200;
    const SAVE_TEAM_MESSAGE                     = 5201;
    const VIEW_SCHEDULE                         = 5300;
    const VIEW_SCHEDULE_DETAIL                  = 5301;
    const VIEW_MEMBERS                          = 5400;

    /**
     * Reporting Actions
     */
    const VIEW_PERFORMANCE_SCORECARD            = 7001;


    /**
     * Admin Actions
     */
    // Roles
    const CREATE_ROLE                           = 8000;
    //const VIEW_ROLES                            = 8001;
    //const UPDATE_ROLE                           = 8002;
    const DELETE_ROLE                           = 8003;
    const UPDATE_ROLE_NAME                      = 8004;
    const UPDATE_ROLE_ENTITY_PERMISSIONS        = 8005;
    const UPDATE_ROLE_OBJECT_PERMISSIONS        = 8006;

    //const VIEW_ALERTS                           = 8010;
    const UPDATE_ALERT_SEQUENCE                 = 8011;
    const UPDATE_ALERT_CONFIG                   = 8012;
    const ADD_ALERT_EXTERNAL_NOTIFICATION_TO    = 8013;
    const REMOVE_ALERT_EXTERNAL_NOTIFICATION_TO = 8014;

    //const VIEW_MEMBERS                          = 8020;
    const VIEW_MEMBER_DETAIL                    = 8021;
    const CREATE_MEMBER                         = 8022;
    const UPDATE_MEMBER_GENERAL                 = 8023;
    const UPDATE_MEMBER_CONTACT                 = 8024;
    const UPDATE_MEMBER_FILTERS                 = 8025;
    const UPDATE_MEMBER_ADDRESS                 = 8026;
    //const UPDATE_MEMBER_HCT_RELATION            = 8027;
    const UPDATE_MEMBER_HCT_AUTO_FILTER_GROUP   = 8028;
    const DELETE_MEMBER                         = 8029;

    const UPDATE_MEMBER_ROLE                    = 8030;
    const RESET_MEMBER_PASSWORD                 = 8031;

    const ADD_MEMBER_HCT_RELATION               = 8032;
    const REMOVE_MEMBER_HCT_RELATION            = 8033;

    const RESET_MEMBER_DEVICE                   = 8040;
    const LOCK_MEMBER_DEVICE                    = 8041;
    const UNLOCK_MEMBER_DEVICE                  = 8042;
    const VIEW_MEMBER_AUTH_UNLOCK_CODE          = 8043;

    const MEMBER_CREATE_CARE_PLAN               = 8050;
    const MEMBER_EDIT_CARE_PLAN                 = 8051;
    const MEMBER_DELETE_CARE_PLAN               = 8052;
    const MEMBER_SAVE_CARE_PLAN                 = 8053;
    const MEMBER_IMPORT                         = 8054;

    const MEMBER_SAVE_ALERT_CONFIG              = 8060;
    const MEMBER_VIEW_ALERT_CONFIG              = 8061;
    const MEMBER_EDIT_ALERT_CONFIG              = 8062;
    const MEMBER_DELETE_ALERT_CONFIG            = 8063;

    const MEMBER_LOCATION_ADDRESS_UPDATE        = 8064;
    const MEMBER_LOCATION_ADDRESS_ADD           = 8065;
    const MEMBER_LOCATION_ADDRESS_VALIDATE      = 8066;
    const MEMBER_LOCATION_ADDRESS_INVALID_AUTOVALIDATE = 8067;

    const MEMBER_IVR_PIN_UPDATE                 = 8068;

    /**
     * Login Actions
     */
    const LOGIN                                 = 9001;
    const LOGOUT                                = 9002;
    const TIMEOUT                               = 9003;
    const LOGOUT_MEMBER_DISABLED                = 9004;

    /**
     * Self-Service Actions
     */
    const CHANGE_PASSWORD                       = 9010;
    const CHANGE_CHALLENGE_QUESTIONS            = 9011;
    const CHANGE_EMAIL_ADDRESS                  = 9012;
    const CHANGE_NAME                           = 9013;
    const CHANGE_LOCALIZATION                   = 9014;
    const CHANGE_TIMEZONE                       = 9015;

    /**
     * Import Mappings
     */
    const IMPORT_MAPPING_VIEW                   = 9020;
    const IMPORT_MAPPING_CREATE                 = 8021;
    const IMPORT_MAPPING_DELETE                 = 8022;
    const IMPORT_MAPPING_UPDATE                 = 8023;
    const IMPORT_MAPPING_APPLY                  = 8024;

    /**
     * Get All Action Codes Defined.
     *
     * @return array
     */
    public static function getAllActionCodes()
    {
        $reflection = new \ReflectionClass('CTLib\Component\ActionLog\ActionCode');

        $constants = array_values($reflection->getConstants());

        return $constants;
    }
}
