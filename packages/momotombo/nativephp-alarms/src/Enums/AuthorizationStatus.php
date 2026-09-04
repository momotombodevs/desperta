<?php

namespace Momotombo\NativePHPAlarms\Enums;

enum AuthorizationStatus: string
{
    case NotDetermined = 'not_determined';
    case Authorized = 'authorized';
    case Denied = 'denied';
    case Unsupported = 'unsupported';
}
