<?php
namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Vendor = 'vendor';
    case Admin = 'admin';
}